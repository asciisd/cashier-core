<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Withdrawals;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\DataObjects\Result;
use Asciisd\CashierCore\DataObjects\WithdrawalRequestData;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\AdminActionRecorded;
use Asciisd\CashierCore\Events\WithdrawalApproved;
use Asciisd\CashierCore\Events\WithdrawalCancelled;
use Asciisd\CashierCore\Events\WithdrawalDebitFailed;
use Asciisd\CashierCore\Events\WithdrawalMarkedPaid;
use Asciisd\CashierCore\Events\WithdrawalRejected;
use Asciisd\CashierCore\Events\WithdrawalRequested;
use Asciisd\CashierCore\Logging\TransactionLogger;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The withdrawal state machine: submission, approve-first admin review,
 * external payout confirmation, and cancellation — every ledger movement
 * claim-gated ({@see TransferClaim}), every admin action audited.
 *
 * The host owns method validation (bank account on file, wallet approved,
 * balance) and all customer communication (via the events). Thin admin-UI
 * actions call approve()/reject()/markPaid() and map the Result.
 */
class WithdrawalWorkflow
{
    /**
     * How long an identical Pending withdrawal blocks a re-submission — long
     * enough to absorb a double-click or an impatient retry, short enough not
     * to block a genuine second withdrawal.
     */
    private const DUPLICATE_WINDOW_SECONDS = 30;

    public function __construct(
        private readonly FundsLedger $ledger,
        private readonly TransferClaim $transferClaim = new TransferClaim,
    ) {}

    /**
     * Create a withdrawal request.
     *
     * approve_first (default): the transaction is created Pending without
     * touching the ledger; an admin approval performs the debit. Legacy mode
     * debits at submit time and rolls the row back if the debit is refused.
     *
     * @throws RuntimeException on double-submission or duplicate window
     */
    public function request(CustomerContract $customer, WithdrawalRequestData $data): Transaction
    {
        $model = Cashier::transactionModel();
        $approveFirst = (bool) config('cashier-core.withdrawals.approve_first', true);

        // One submission at a time per customer, plus a short identical-request
        // window: a double-click or two racing requests must not create two
        // withdrawal rows that each passed the host's balance check.
        $submitLock = Cache::lock("cashier:withdrawal:submit:{$customer->cashierId()}", 10);

        if (! $submitLock->get()) {
            throw new RuntimeException('A withdrawal submission is already in progress.');
        }

        try {
            $recentDuplicate = $model::query()
                ->withoutGlobalScopes($model::cashierBypassedScopes())
                ->where('user_id', $customer->cashierId())
                ->where('type', TransactionType::Withdrawal)
                ->where('status', PaymentStatus::Pending)
                ->where('amount', $data->amount)
                ->where('withdrawal_method', $data->method)
                ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))
                ->exists();

            if ($recentDuplicate) {
                throw new RuntimeException('An identical withdrawal request was just submitted.');
            }

            /** @var Transaction $transaction */
            $transaction = $model::query()->create([
                'user_id' => $customer->cashierId(),
                'trading_account_id' => $data->tradingAccountId,
                'provider' => $data->driver,
                // ULID: (provider, provider_transaction_id) is unique-indexed,
                // and uniqid() can collide across concurrent requests.
                'provider_transaction_id' => 'WD-'.Str::ulid(),
                'type' => TransactionType::Withdrawal,
                'status' => PaymentStatus::Pending,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'description' => $data->description ?? "Withdrawal via {$data->method}",
                'withdrawal_method' => $data->method,
                'withdrawal_details' => $data->details,
                'withdrawal_reason' => $data->reason,
                'metadata' => array_merge($data->metadata, [
                    'approve_first' => $approveFirst,
                    'ledger_account' => $data->ledgerAccount,
                ]),
            ]);

            if (! $approveFirst) {
                $this->debitAtSubmission($transaction, $data);
            }
        } finally {
            $submitLock->release();
        }

        TransactionLogger::withdrawalRequestCreated(
            (int) $customer->cashierId(),
            $transaction->id,
            $transaction->amount,
            $data->method,
            $data->tradingAccountId,
            $data->reason,
        );

        WithdrawalRequested::dispatch($transaction);

        return $transaction;
    }

    /**
     * Approve a pending withdrawal: debit the ledger (unless a legacy
     * submission already did) and move it to Processing for payout.
     */
    public function approve(Transaction $transaction, Actor $actor, int|string|null $ledgerAccount = null): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if ($transaction->status !== PaymentStatus::Pending) {
            return Result::failed('Only pending withdrawals can be approved.');
        }

        $alreadyDebited = ! empty($transaction->mt5_ticket_number);

        if ($alreadyDebited) {
            // Legacy submit-time debit — no ledger call, just the status flip,
            // still guarded against a concurrent action on the same row.
            $flipped = $this->transferClaim->transition($transaction, PaymentStatus::Pending, [
                'status' => PaymentStatus::Processing,
                'processed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            if (! $flipped) {
                return Result::failed('This withdrawal changed state or is being processed by someone else.');
            }

            $this->audit($actor, 'withdrawal.approve', $flipped, PaymentStatus::Pending, PaymentStatus::Processing);

            TransactionLogger::withdrawalApproved((int) $actor->id, $flipped->id, 0, (float) $flipped->amount, (string) $flipped->mt5_ticket_number);

            WithdrawalApproved::dispatch($flipped, $actor);

            return Result::ok('Withdrawal approved.', $flipped);
        }

        $account = $ledgerAccount ?? $transaction->metadata['ledger_account'] ?? null;

        if (! $account) {
            return Result::failed('No ledger account is associated with this withdrawal.');
        }

        // Claim the debit under a row lock: a second admin approving the same
        // row (or the customer cancelling it) either changed the status or
        // holds the claim, and is refused here rather than debiting twice.
        $claimed = $this->transferClaim->acquire(
            $transaction,
            'withdrawal:approve',
            expectedStatus: PaymentStatus::Pending,
        );

        if (! $claimed) {
            return Result::failed('This withdrawal changed state or is being processed by someone else.');
        }

        $ticket = $this->ledger->debit(
            account: $account,
            amount: (float) $claimed->amount,
            currency: (string) $claimed->currency,
            comment: $claimed->mt5Comment(),
        );

        if (! $ticket) {
            TransactionLogger::withdrawalApproveMt5DebitFailed((int) $actor->id, $claimed->id, (int) $account, (float) $claimed->amount);

            // A null result is a definite refusal — release so a retry can
            // claim immediately.
            $this->transferClaim->release($claimed);

            WithdrawalDebitFailed::dispatch($claimed, $actor);

            return Result::failed('Ledger debit failed. The withdrawal remains pending.');
        }

        $this->transferClaim->settle($claimed, [
            'status' => PaymentStatus::Processing,
            'mt5_ticket_number' => $ticket->ticket,
            'executed_at' => now(),
            'processed_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        $this->ledger->refresh($account);

        $this->audit($actor, 'withdrawal.approve', $claimed, PaymentStatus::Pending, PaymentStatus::Processing);

        TransactionLogger::withdrawalApproved((int) $actor->id, $claimed->id, (int) $account, (float) $claimed->amount, $ticket->ticket);

        WithdrawalApproved::dispatch($claimed, $actor);

        return Result::ok('Withdrawal approved and debited.', $claimed);
    }

    /**
     * Reject a pending withdrawal, refunding any prior debit first.
     */
    public function reject(Transaction $transaction, Actor $actor, string $reason, int|string|null $ledgerAccount = null): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if ($transaction->status !== PaymentStatus::Pending) {
            return Result::failed('Only pending withdrawals can be rejected.');
        }

        $failedAttributes = [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'processed_at' => null,
            'error_message' => $reason,
        ];

        if (empty($transaction->mt5_ticket_number)) {
            $flipped = $this->transferClaim->transition($transaction, PaymentStatus::Pending, $failedAttributes);

            if (! $flipped) {
                return Result::failed('This withdrawal changed state or is being processed by someone else.');
            }

            $this->audit($actor, 'withdrawal.reject', $flipped, PaymentStatus::Pending, PaymentStatus::Failed, ['reason' => $reason]);

            TransactionLogger::withdrawalRejected((int) $actor->id, $flipped->id, $reason, false);

            WithdrawalRejected::dispatch($flipped, $actor);

            return Result::ok('Withdrawal rejected.', $flipped);
        }

        // Debited (legacy submission) — claim the refund under a row lock (the
        // debit ticket exists, so the ticket check is waived): a concurrent
        // approve, a second reject, or the customer's own cancel would
        // otherwise race this refund and return the funds twice.
        $claimed = $this->transferClaim->acquire(
            $transaction,
            'withdrawal:reject',
            expectedStatus: PaymentStatus::Pending,
            requireNoTicket: false,
        );

        if (! $claimed) {
            return Result::failed('This withdrawal changed state or is being processed by someone else.');
        }

        $account = $ledgerAccount ?? $claimed->metadata['ledger_account'] ?? null;
        $refunded = false;

        if ($account) {
            $refund = $this->ledger->correct(
                account: $account,
                amount: (float) $claimed->amount,
                currency: (string) $claimed->currency,
                comment: 'Rollback withdrawal',
            );

            if (! $refund) {
                TransactionLogger::withdrawalRejectRefundFailed((int) $actor->id, $claimed->id, (int) $account, (float) $claimed->amount);

                $this->transferClaim->release($claimed);

                return Result::failed('Ledger refund failed. Withdrawal was not rejected.');
            }

            $this->ledger->refresh($account);
            $refunded = true;
        }

        $this->transferClaim->settle($claimed, $failedAttributes);

        $this->audit($actor, 'withdrawal.reject', $claimed, PaymentStatus::Pending, PaymentStatus::Failed, ['reason' => $reason, 'refunded' => $refunded]);

        TransactionLogger::withdrawalRejected((int) $actor->id, $claimed->id, $reason, $refunded);

        WithdrawalRejected::dispatch($claimed, $actor);

        return Result::ok('Withdrawal rejected.', $claimed);
    }

    /**
     * Close a Processing withdrawal as Succeeded once the external payout is
     * confirmed.
     *
     * @param  array<string, mixed>  $payoutDetails  stored on metadata (e.g.
     *                                               payout_reference, note)
     */
    public function markPaid(Transaction $transaction, Actor $actor, array $payoutDetails = []): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if ($transaction->status !== PaymentStatus::Processing) {
            return Result::failed('Only withdrawals in Processing can be marked as paid.');
        }

        // Locked transition: a double-click or a concurrent customer cancel
        // (Processing is customer-cancelable) either changed the status or
        // holds the row, and is refused instead of double-closing.
        $closed = $this->transferClaim->transition(
            $transaction,
            PaymentStatus::Processing,
            fn (Transaction $locked): array => [
                'status' => PaymentStatus::Succeeded,
                'processed_at' => $locked->processed_at ?? now(),
                'metadata' => array_merge($locked->metadata ?? [], $payoutDetails, [
                    'paid_by_actor_id' => $actor->id,
                    'paid_at' => now()->toIso8601String(),
                ]),
            ],
        );

        if (! $closed) {
            return Result::failed('This withdrawal changed state or is being processed by someone else.');
        }

        $this->audit($actor, 'withdrawal.mark-paid', $closed, PaymentStatus::Processing, PaymentStatus::Succeeded, $payoutDetails);

        TransactionLogger::withdrawalMarkedPaid((int) $actor->id, $closed->id, $payoutDetails['payout_reference'] ?? null);

        WithdrawalMarkedPaid::dispatch($closed, $actor);

        return Result::ok('Withdrawal marked as paid.', $closed);
    }

    /**
     * Cancel a pending or processing withdrawal, returning any prior debit.
     *
     * @param  Actor|null  $actor  null when the customer cancels their own
     */
    public function cancel(Transaction $transaction, ?Actor $actor = null, int|string|null $ledgerAccount = null): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if (! in_array($transaction->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
            return Result::failed('This withdrawal can no longer be cancelled.');
        }

        $from = $transaction->status;
        $wasDebited = ! empty($transaction->mt5_ticket_number);

        $claimed = $this->transferClaim->acquire(
            $transaction,
            'withdrawal:cancel',
            expectedStatus: $from,
            requireNoTicket: false,
        );

        if (! $claimed) {
            return Result::failed('This withdrawal is currently being processed. Try again shortly.');
        }

        $cancelTicket = null;

        if ($wasDebited) {
            $account = $ledgerAccount ?? $claimed->metadata['ledger_account'] ?? null;

            if (! $account) {
                $this->transferClaim->release($claimed);

                return Result::failed('No ledger account is associated with this withdrawal.');
            }

            $refund = $this->ledger->correct(
                account: $account,
                amount: (float) $claimed->amount,
                currency: (string) $claimed->currency,
                comment: $claimed->mt5CancelComment(),
            );

            if (! $refund) {
                TransactionLogger::withdrawalCancelMt5ReversalFailed(
                    (int) ($actor->id ?? $claimed->user_id),
                    $claimed->id,
                    (int) $account,
                    (float) $claimed->amount,
                );

                $this->transferClaim->release($claimed);

                return Result::failed('Unable to cancel the withdrawal.');
            }

            $this->ledger->refresh($account);
            $cancelTicket = $refund->ticket;
        }

        $this->transferClaim->settle($claimed, [
            'status' => PaymentStatus::Canceled,
            'failed_at' => now(),
            'processed_at' => null,
            'error_message' => $actor === null ? 'Cancelled by user' : 'Cancelled by admin',
            'metadata' => array_merge($claimed->metadata ?? [], [
                'cancel_ledger_ticket' => $cancelTicket,
                'cancelled_at' => now()->toIso8601String(),
                'cancelled_by' => $actor?->id ?? $claimed->user_id,
            ]),
        ]);

        if ($actor !== null) {
            $this->audit($actor, 'withdrawal.cancel', $claimed, $from, PaymentStatus::Canceled, ['refunded' => $wasDebited]);
        }

        TransactionLogger::withdrawalCancelledByUser(
            (int) ($actor->id ?? $claimed->user_id),
            $claimed->id,
            (float) $claimed->amount,
            $wasDebited,
        );

        WithdrawalCancelled::dispatch($claimed, $actor);

        return Result::ok('Withdrawal cancelled.', $claimed);
    }

    /**
     * Legacy submit-time debit, with rollback when the ledger refuses.
     */
    private function debitAtSubmission(Transaction $transaction, WithdrawalRequestData $data): void
    {
        $account = $data->ledgerAccount;

        if (! $account) {
            $transaction->delete();

            throw new RuntimeException('A ledger account is required to process the withdrawal.');
        }

        $ticket = $this->ledger->debit(
            account: $account,
            amount: (float) $transaction->amount,
            currency: (string) $transaction->currency,
            comment: $transaction->mt5Comment(),
        );

        if (! $ticket) {
            TransactionLogger::mt5WithdrawalFailedForWithdrawalRequest(
                (int) $transaction->user_id,
                (int) $account,
                (float) $transaction->amount,
            );

            $transaction->delete();

            throw new RuntimeException('Unable to deduct funds from the ledger account.');
        }

        $transaction->update([
            'mt5_ticket_number' => $ticket->ticket,
            'executed_at' => now(),
        ]);

        $this->ledger->refresh($account);
    }

    /**
     * Append the audit row (PCI DSS 10.2.1) and announce it.
     *
     * @param  array<string, mixed>  $context
     */
    private function audit(
        Actor $actor,
        string $action,
        Transaction $transaction,
        PaymentStatus $from,
        PaymentStatus $to,
        array $context = [],
    ): void {
        $record = AdminAction::query()->create([
            'actor_id' => (string) $actor->id,
            'actor_guard' => $actor->guard,
            'action' => $action,
            'transaction_id' => $transaction->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'context' => $context,
            'ip' => $actor->ip,
            'created_at' => now(),
        ]);

        AdminActionRecorded::dispatch($record);
    }
}
