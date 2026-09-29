<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Withdrawals;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\DataObjects\Result;
use Asciisd\CashierCore\DataObjects\WithdrawalRequestData;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\AdminActionRecorded;
use Asciisd\CashierCore\Events\PayoutFundsInsufficient;
use Asciisd\CashierCore\Events\WithdrawalApproved;
use Asciisd\CashierCore\Events\WithdrawalCancelled;
use Asciisd\CashierCore\Events\WithdrawalDebitFailed;
use Asciisd\CashierCore\Events\WithdrawalMarkedPaid;
use Asciisd\CashierCore\Events\WithdrawalPayoutFailed;
use Asciisd\CashierCore\Events\WithdrawalPayoutSent;
use Asciisd\CashierCore\Events\WithdrawalRejected;
use Asciisd\CashierCore\Events\WithdrawalRequested;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Asciisd\CashierCore\Logging\TransactionLogger;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
        private readonly ?ConnectionRegistry $connections = null,
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
            fn (Transaction $locked): array => array_merge([
                'status' => PaymentStatus::Succeeded,
                'processed_at' => $locked->processed_at ?? now(),
                'metadata' => array_merge($locked->metadata ?? [], $payoutDetails, [
                    'paid_by_actor_id' => $actor->id,
                    'paid_at' => now()->toIso8601String(),
                ]),
            ], $locked->payout_state !== null ? ['payout_state' => PayoutState::Paid] : []),
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
     * Send a Processing withdrawal's payout through its PSP connection.
     *
     * The balance is checked first; a shortfall sends nothing and leaves the
     * row as it was. A rejection marks the payout failed for an admin to fix
     * and resend. An ambiguous outcome marks it unknown and keeps the claim,
     * so nothing — not a resend, not a cancel — acts until it is resolved.
     * `status` stays Processing throughout; the customer never sees PSP state.
     */
    public function sendPayout(Transaction $transaction, Actor $actor): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if ($transaction->status !== PaymentStatus::Processing) {
            return Result::failed('Only withdrawals in Processing can be sent for payout.');
        }

        if (! $this->canSendPayout($transaction)) {
            return Result::failed('This withdrawal has already been sent for payout.');
        }

        $provider = $this->payoutProvider($transaction);

        if (! $provider) {
            return Result::failed("The {$transaction->provider} connection cannot send payouts.");
        }

        // An unknown send may have landed: resolve it before sending anything.
        if ($transaction->payout_state === PayoutState::Unknown) {
            $existing = $this->lookupPayout($provider, (string) $transaction->provider_transaction_id);

            if ($existing !== null) {
                $this->applyPayoutUpdate($transaction, $existing, 'sync');

                return Result::ok('The earlier payout was found at the provider; its status has been applied.', $transaction->refresh());
            }
        }

        $claimed = $this->transferClaim->acquire(
            $transaction,
            'withdrawal:send-payout',
            expectedStatus: PaymentStatus::Processing,
            requireNoTicket: false,
        );

        if (! $claimed) {
            return Result::failed('This withdrawal is being processed, or an earlier send is still settling. Try again shortly.');
        }

        // Re-check under the lock: the caller's instance may be stale.
        if (! $this->canSendPayout($claimed)) {
            $this->transferClaim->release($claimed);

            return Result::failed('This withdrawal has already been sent for payout.');
        }

        // A failed payment still holds its order id at the PSP, so a resend
        // needs a new one. It is only persisted once a send is attempted.
        $orderId = $claimed->payout_state === PayoutState::Failed
            ? $this->nextAttemptId($claimed)
            : (string) $claimed->provider_transaction_id;

        try {
            $request = $this->payoutRequest($claimed, $orderId);
            $preflight = $provider->preflight($request);
        } catch (PaymentProcessingException $e) {
            $this->transferClaim->release($claimed);

            return Result::failed($e->getMessage());
        } catch (Throwable $e) {
            // Nothing has been sent yet at this point — releasing is always
            // safe, unlike an exception from provider->send() below.
            $this->transferClaim->release($claimed);

            return Result::failed('The payout check failed before anything was sent: '.$e->getMessage());
        }

        if (! $preflight->ok) {
            $this->transferClaim->release($claimed);

            TransactionLogger::withdrawalPayoutPreflightRefused($actor->id, $claimed->id, $preflight->reason, (string) $preflight->message);

            if ($preflight->reason === PayoutPreflight::INSUFFICIENT_FUNDS) {
                PayoutFundsInsufficient::dispatch(
                    $claimed,
                    (string) $preflight->balance,
                    (string) $preflight->required,
                    (string) $preflight->walletCurrency,
                );
            }

            return Result::failed((string) $preflight->message);
        }

        try {
            $receipt = $provider->send($request);
        } catch (PayoutRejectedException $e) {
            $this->transferClaim->settle($claimed, $this->withAttempt($claimed, $orderId, [
                'payout_state' => PayoutState::Failed,
            ], ['payout_error' => $e->getMessage()]));

            $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
                'outcome' => 'rejected', 'order_id' => $orderId, 'error' => $e->getMessage(),
            ]);

            TransactionLogger::withdrawalPayoutRejected($actor->id, $claimed->id, $orderId, $e->getMessage());

            WithdrawalPayoutFailed::dispatch($claimed, $e->getMessage());

            return Result::failed('The provider refused the payout: '.$e->getMessage());
        } catch (PayoutOutcomeUnknownException $e) {
            return $this->markPayoutOutcomeUnknown($claimed, $actor, $orderId, $e->getMessage());
        } catch (PaymentProcessingException $e) {
            $this->transferClaim->release($claimed);

            return Result::failed($e->getMessage());
        } catch (Throwable $e) {
            // An unexpected exception after the POST may still have reached the
            // PSP — treated exactly like an unknown outcome: keep the claim,
            // block both resend and cancel until it is resolved.
            return $this->markPayoutOutcomeUnknown($claimed, $actor, $orderId, $e->getMessage());
        }

        $this->transferClaim->settle($claimed, $this->withAttempt($claimed, $orderId, [
            'payout_state' => PayoutState::Sent,
            'payout_reference' => $receipt->reference,
            'provider_payload' => $receipt->payload,
        ], ['payout_sent_at' => now()->toIso8601String(), 'payout_error' => null]));

        $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
            'outcome' => 'sent', 'order_id' => $orderId, 'reference' => $receipt->reference,
        ]);

        TransactionLogger::withdrawalPayoutSent($actor->id, $claimed->id, $orderId, $receipt->reference);

        WithdrawalPayoutSent::dispatch($claimed, $actor);

        // A "not unique" send resolves to the existing payout, which may
        // already be further along than sent.
        if ($receipt->state !== PayoutState::Sent) {
            $this->applyPayoutUpdate($claimed, $receipt, 'sync');
        }

        return Result::ok('Payout sent.', $claimed->refresh());
    }

    /**
     * Stub until Task 6 replaces it with the full implementation.
     */
    public function applyPayoutUpdate(Transaction $transaction, PayoutReceipt $receipt, string $source = 'webhook'): bool
    {
        return false;
    }

    /**
     * Shared handling for a send whose outcome cannot be trusted — a declared
     * PayoutOutcomeUnknownException, or any other exception raised after the
     * POST. Either way the payout may have landed at the PSP: keep the claim,
     * mark the row Unknown, and refuse to resend or cancel until it is
     * resolved by a lookup, a webhook, or manual review.
     */
    private function markPayoutOutcomeUnknown(Transaction $claimed, Actor $actor, string $orderId, string $error): Result
    {
        $claimed->update($this->withAttempt($claimed, $orderId, [
            'payout_state' => PayoutState::Unknown,
        ], ['payout_error' => $error]));

        $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
            'outcome' => 'unknown', 'order_id' => $orderId, 'error' => $error,
        ]);

        TransactionLogger::withdrawalPayoutOutcomeUnknown($actor->id, $claimed->id, $orderId, $error);

        return Result::failed('Payout outcome unknown. Do not resend; use Check status, or retry after 10 minutes.');
    }

    private function canSendPayout(Transaction $transaction): bool
    {
        return in_array($transaction->payout_state, [null, PayoutState::Failed, PayoutState::Unknown], true);
    }

    private function payoutProvider(Transaction $transaction): ?SendsPayouts
    {
        try {
            $provider = ($this->connections ?? app(ConnectionRegistry::class))
                ->get($transaction->connection ?? $transaction->provider);
        } catch (Throwable) {
            return null;
        }

        return $provider instanceof SendsPayouts ? $provider : null;
    }

    private function lookupPayout(SendsPayouts $provider, string $orderId): ?PayoutReceipt
    {
        try {
            return $provider->lookup($orderId);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * @throws PaymentProcessingException when the host stored no payout destination
     */
    private function payoutRequest(Transaction $transaction, string $orderId): PayoutRequest
    {
        $details = (array) ($transaction->withdrawal_details ?? []);
        $address = trim((string) ($details['payout_address'] ?? ''));
        $currency = trim((string) ($details['payout_currency'] ?? ''));

        if ($address === '' || $currency === '') {
            throw new PaymentProcessingException('This withdrawal has no payout_address / payout_currency in its details.');
        }

        $extraId = trim((string) ($details['payout_extra_id'] ?? ''));
        $email = trim((string) ($details['customer_email'] ?? ''));

        return new PayoutRequest(
            externalOrderId: $orderId,
            customerId: (string) ($transaction->metadata['ledger_account'] ?? $transaction->user_id),
            amount: (string) $transaction->amount,
            currency: (string) $transaction->currency,
            payoutCurrency: strtolower($currency),
            payoutAddress: $address,
            payoutExtraId: $extraId === '' ? null : $extraId,
            customerEmail: $email === '' ? null : $email,
        );
    }

    /**
     * WD-<ULID> → WD-<ULID>-2 → WD-<ULID>-3, always from the first attempt's id.
     */
    private function nextAttemptId(Transaction $transaction): string
    {
        $attempts = $transaction->metadata['payout_attempts'] ?? [];
        $base = (string) ($attempts[0]['order_id'] ?? $transaction->provider_transaction_id);

        return $base.'-'.(count($attempts) + 2);
    }

    /**
     * The attributes for a send outcome; when the attempt used a new order id,
     * the previous attempt is appended to `payout_attempts` and the row moves
     * to the new id so callbacks still correlate.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function withAttempt(Transaction $transaction, string $orderId, array $attributes, array $metadata): array
    {
        $current = $transaction->metadata ?? [];

        if ($orderId !== $transaction->provider_transaction_id) {
            $current['payout_attempts'] = [...($current['payout_attempts'] ?? []), [
                'order_id' => $transaction->provider_transaction_id,
                'state' => $transaction->payout_state?->value,
                'error' => $current['payout_error'] ?? null,
                'at' => now()->toIso8601String(),
            ]];

            $attributes['provider_transaction_id'] = $orderId;
        }

        return array_merge($attributes, ['metadata' => array_merge($current, $metadata)]);
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

        if ($transaction->payout_state?->isInFlight()) {
            return Result::failed('A payout for this withdrawal is in flight at the provider. Check its status before cancelling.');
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

        // Re-check under the lock: a send may have settled since this instance was loaded.
        if ($claimed->payout_state?->isInFlight()) {
            $this->transferClaim->release($claimed);

            return Result::failed('A payout for this withdrawal is in flight at the provider. Check its status before cancelling.');
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
