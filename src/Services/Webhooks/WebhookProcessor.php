<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services\Webhooks;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\DepositFailed;
use Asciisd\CashierCore\Events\DepositHeldForReview;
use Asciisd\CashierCore\Events\DepositSucceeded;
use Asciisd\CashierCore\Events\FeeDriftDetected;
use Asciisd\CashierCore\Events\FundsCredited;
use Asciisd\CashierCore\Events\FundsCreditFailed;
use Asciisd\CashierCore\Events\TransactionStatusChanged;
use Asciisd\CashierCore\Fees\SettledAmountResolver;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\PaymentMethodSnapshotAttributes;
use Asciisd\CashierCore\Support\PayloadSanitizer;
use Asciisd\CashierCore\Support\TransferClaim;
use Illuminate\Support\Facades\DB;

/**
 * The single place a provider update mutates a transaction.
 *
 * Correlation, idempotency and out-of-order guards, the status transition
 * under a row lock, settlement reconciliation, and — on a settled deposit —
 * the ledger credit. Side effects the host owns (invoice mail, notifications,
 * CRM) hang off the events; the package sends nothing itself.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly FundsLedger $ledger,
        private readonly TransferClaim $transferClaim = new TransferClaim,
        private readonly SettledAmountResolver $settledAmounts = new SettledAmountResolver,
        private readonly PayloadSanitizer $sanitizer = new PayloadSanitizer,
    ) {}

    /**
     * Apply a parsed provider webhook to the matching local transaction.
     */
    public function process(string $driver, ?string $providerTransactionId, TransactionWebhookUpdate $update): void
    {
        if ($providerTransactionId === null || $providerTransactionId === '') {
            PaymentLogger::providerWebhookMissingTransactionId($driver);

            return;
        }

        PaymentLogger::providerWebhookReceived($driver, $providerTransactionId, $update->status->value);

        $model = Cashier::transactionModel();

        // Host scopes are bypassed — webhooks arrive with no authenticated
        // user. SoftDeletes stays in force: a transaction the customer deleted
        // must not change status or move funds.
        $transaction = $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->where('provider', $driver)
            ->where('provider_transaction_id', $providerTransactionId)
            ->first();

        if (! $transaction) {
            PaymentLogger::providerWebhookTransactionNotFound($driver, $providerTransactionId);

            return;
        }

        $this->applyUpdate($driver, $transaction, $update);
    }

    /**
     * Apply a normalized provider update to a transaction we already hold.
     *
     * Shared by the webhook pipeline and by admin-initiated sync, so a status
     * recovered through `retrieve()` passes the same guards and triggers the
     * same post-success work instead of only flipping a column.
     *
     * @param  string  $source  `webhook` or `sync`, for log/event context
     * @return bool whether the transaction was updated; false when ignored
     */
    public function applyUpdate(
        string $driver,
        Transaction $transaction,
        TransactionWebhookUpdate $update,
        string $source = 'webhook',
    ): bool {
        $model = Cashier::transactionModel();

        /** @var array{0: Transaction, 1: PaymentStatus, 2: PaymentStatus}|null $applied */
        $applied = DB::transaction(function () use ($model, $driver, $transaction, $update, $source): ?array {
            // Re-read under a row lock: the caller's instance may be stale by
            // the time this runs, and two concurrent deliveries (or a webhook
            // racing an admin sync) must not both pass the guards below. The
            // re-read excludes soft-deleted rows on purpose.
            $locked = $model::query()
                ->withoutGlobalScopes($model::cashierBypassedScopes())
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                PaymentLogger::providerWebhookTransactionNotFound($driver, (string) $transaction->provider_transaction_id);

                return null;
            }

            if ($locked->status === $update->status) {
                PaymentLogger::providerWebhookIgnoredDuplicate($driver, $locked->id, $update->status->value, $source);

                return null;
            }

            // A settled deposit may only move to Canceled (refund/chargeback);
            // ignore late or out-of-order pending/failed callbacks after
            // success.
            if ($locked->status === PaymentStatus::Succeeded && $update->status !== PaymentStatus::Canceled) {
                PaymentLogger::providerWebhookIgnoredOutOfOrder(
                    $driver,
                    $locked->id,
                    $locked->status->value,
                    $update->status->value,
                    $source,
                );

                return null;
            }

            $from = $locked->status;
            $status = $update->status;
            $holdReason = null;

            // A success whose reported figures deviate from the invoice beyond
            // tolerance is held for review instead of credited — a partial
            // settlement must not fund the full invoice, and a currency
            // mismatch must never reach the ledger. Client-controlled rails
            // reconcile instead (their settlement metadata says what actually
            // arrived), so they are exempt from the hold.
            if ($status === PaymentStatus::Succeeded && $locked->type === TransactionType::Deposit) {
                $holdReason = $this->deviationBeyondTolerance($locked, $update);

                if ($holdReason !== null) {
                    $status = PaymentStatus::OnHold;
                }
            }

            $updateData = [
                'status' => $status,
                'provider_payload' => $this->sanitizer->forStorage($driver, $update->processorResponse),
                'metadata' => array_merge(
                    $locked->metadata ?? [],
                    $update->metadata ?? [],
                    $holdReason !== null ? ['hold_reason' => $holdReason] : [],
                ),
            ];

            if ($status === PaymentStatus::Succeeded) {
                $updateData['processed_at'] = now();
                $updateData['failed_at'] = null;
                $updateData['error_message'] = null;
                $updateData['error_code'] = null;
            } elseif ($status === PaymentStatus::Failed || $status === PaymentStatus::Canceled) {
                $updateData['failed_at'] = now();
                $updateData['error_message'] = $update->errorMessage;
                $updateData['error_code'] = $update->errorCode;
            }

            // The provider's instrument overrides the method the user selected
            // before being sent off — but only where it knows something. A
            // snapshot that knows the brand and nothing else must not erase the
            // display name the deposit was created with.
            $updateData = array_merge(
                $updateData,
                PaymentMethodSnapshotAttributes::known($update->paymentMethodSnapshot),
            );

            // Driver-specific columns the caller resolved itself (e.g. the
            // `payment_processor` a sync read out of the retrieved payload).
            $updateData = array_merge($updateData, $update->additionalAttributes);

            // Reconcile before the status lands, so the credit below reads an
            // `amount` that already reflects what was actually paid.
            if ($status === PaymentStatus::Succeeded) {
                $updateData = array_merge($updateData, $this->settlementAttributes($driver, $locked, $update));
            }

            $locked->update($updateData);

            return [$locked, $from, $status];
        });

        if ($applied === null) {
            return false;
        }

        [$appliedTransaction, $from, $status] = $applied;

        // Keep the caller's instance in step with the committed row — callers
        // (sync commands, admin tooling) keep using the instance they passed.
        if ($transaction !== $appliedTransaction) {
            $transaction->setRawAttributes($appliedTransaction->getAttributes(), true);
        }

        TransactionStatusChanged::dispatch($appliedTransaction, $from, $status, $source);

        if (($update->metadata['requires_attention'] ?? false) === true) {
            PaymentLogger::providerHoldRequiresAttention($driver, $appliedTransaction->id, (string) $appliedTransaction->provider_transaction_id);
        }

        if ($status === PaymentStatus::OnHold) {
            PaymentLogger::depositHeldForReview(
                $driver,
                $appliedTransaction->id,
                (string) ($appliedTransaction->metadata['hold_reason'] ?? ''),
            );

            DepositHeldForReview::dispatch(
                $appliedTransaction,
                $update->amount !== null ? (float) $update->amount : null,
                $update->currency,
                (string) ($appliedTransaction->metadata['hold_reason'] ?? ''),
            );

            return true;
        }

        if ($status === PaymentStatus::Failed || $status === PaymentStatus::Canceled) {
            if ($appliedTransaction->type === TransactionType::Deposit) {
                DepositFailed::dispatch($appliedTransaction);
            }

            return true;
        }

        // Side effects run after the status transition committed. Only the one
        // caller whose update actually changed the status reaches this block —
        // its racing duplicate returned false above — so the credit is
        // attempted once per transition.
        if ($status === PaymentStatus::Succeeded) {
            PaymentLogger::providerTransactionConfirmedSuccessful(
                $driver,
                $appliedTransaction->id,
                (string) $appliedTransaction->provider_transaction_id,
                // `amount` casts to decimal, i.e. a string — the logger and the
                // ledger both want a number.
                $update->amount ?? (float) $appliedTransaction->amount,
                $source,
            );

            // Only deposits are credited. Withdrawals reach this method too —
            // sync runs on any external transaction — and crediting one would
            // hand the customer their payout back.
            if ($appliedTransaction->type === TransactionType::Deposit) {
                $this->reportFeeDrift($driver, $appliedTransaction, $update);

                DepositSucceeded::dispatch($appliedTransaction);

                $this->creditLedger($driver, $appliedTransaction);
            }
        }

        return true;
    }

    /**
     * Why this success must be held, or null when it may settle.
     *
     * The reported amount is compared against what we asked the PSP for
     * (`requested_amount`, falling back to `amount`), inside the configured
     * tolerance band. Rails that report their settlement explicitly are exempt
     * — reconciliation, not the hold, is their correctness mechanism.
     */
    private function deviationBeyondTolerance(Transaction $transaction, TransactionWebhookUpdate $update): ?string
    {
        if (isset($update->metadata['settlement_expected_payment'], $update->metadata['settlement_actually_paid'])) {
            return null;
        }

        // A foreign charge is invoiced, reported and reconciled in the charge
        // currency — the account currency never reaches the PSP at all.
        // Comparing the callback against the account-currency figure would put
        // every one of them on hold.
        $hasChargeLeg = $transaction->charge_currency !== null && $transaction->charge_amount !== null;

        $expectedCurrency = $hasChargeLeg ? $transaction->charge_currency : $transaction->currency;

        if ($update->currency !== null && strcasecmp($update->currency, (string) $expectedCurrency) !== 0) {
            return "currency mismatch: webhook reports {$update->currency}, transaction is {$expectedCurrency}";
        }

        if ($update->amount === null) {
            return null;
        }

        $expected = $hasChargeLeg
            ? (float) $transaction->charge_amount
            : (float) ($transaction->requested_amount ?? $transaction->amount);

        if ($expected <= 0.0) {
            return null;
        }

        $tolerance = (float) config('cashier-core.webhooks.amount_tolerance_percent', 1.0) / 100;
        $deviation = abs(((float) $update->amount) - $expected) / $expected;

        if ($deviation > $tolerance) {
            return sprintf(
                'amount deviates %.2f%% from invoiced %.2f (webhook reports %.2f)',
                $deviation * 100,
                $expected,
                (float) $update->amount,
            );
        }

        return null;
    }

    /**
     * Credit the customer's ledger account when the deposit targeted one.
     *
     * The credit is claimed atomically first ({@see TransferClaim}): a ticket
     * means these funds already landed, and a credit is not reversible, so the
     * ticket check and the claim happen under one row lock rather than against
     * a possibly stale instance.
     */
    private function creditLedger(string $driver, Transaction $transaction): void
    {
        try {
            $claimed = $this->transferClaim->acquire($transaction, $driver);

            if (! $claimed) {
                return;
            }

            $account = $claimed->metadata['trading_account_login'] ?? null;

            if (! $account) {
                PaymentLogger::noTradingAccountForProviderTransfer($driver, $claimed->id);
                $this->transferClaim->release($claimed);

                return;
            }

            // Always the transaction's own amount: fees sit on top of it
            // rather than inside it, and the webhook's amount echoes the
            // grossed-up figure we sent the PSP. Client-controlled rails have
            // already had this column reconciled above.
            $amount = (float) $claimed->amount;

            $ticket = $this->ledger->credit(
                account: $account,
                amount: $amount,
                currency: (string) $claimed->currency,
                comment: $claimed->mt5Comment(),
            );

            if ($ticket) {
                $this->transferClaim->settle($claimed, [
                    'mt5_ticket_number' => $ticket->ticket,
                    'executed_at' => now(),
                ]);

                PaymentLogger::providerFundsTransferred($driver, $claimed->id, (int) $account, $amount, (int) $ticket->ticket);

                FundsCredited::dispatch($claimed, $ticket);
            } else {
                // A null result is a definite refusal — safe to release the
                // claim for an immediate retry via sync.
                PaymentLogger::providerFundsTransferFailed($driver, $claimed->id, (int) $account, $amount, (string) $claimed->currency);
                $this->transferClaim->release($claimed);

                FundsCreditFailed::dispatch($claimed, 'ledger refused the credit');
            }
        } catch (\Exception $e) {
            // The claim is deliberately NOT released here: an exception (e.g. a
            // timeout) may mean the credit landed without a response. It
            // expires on its own, leaving a window to check ledger history.
            PaymentLogger::providerFundsTransferException($driver, $transaction->id, $e->getMessage());

            FundsCreditFailed::dispatch($transaction, $e->getMessage());
        }
    }

    /**
     * Reconcile the deposit to what actually arrived, for rails where the
     * customer controls the amount.
     *
     * A crypto invoice is a wallet address: it accepts any amount, so the
     * invoiced figure is a request rather than a fact. Everything else fixes
     * the charge at checkout and returns nothing here.
     *
     * @return array<string, mixed>
     */
    private function settlementAttributes(
        string $driver,
        Transaction $transaction,
        TransactionWebhookUpdate $update,
    ): array {
        $expected = $update->metadata['settlement_expected_payment'] ?? null;
        $paid = $update->metadata['settlement_actually_paid'] ?? null;

        if ($expected === null || $paid === null) {
            return [];
        }

        $settled = $this->settledAmounts->resolve(
            invoicedAmount: (float) $transaction->amount,
            expectedPayment: (float) $expected,
            actuallyPaid: (float) $paid,
        );

        if ($settled === null) {
            return [];
        }

        if (! $settled->requiresReconciliation()) {
            return ['settled_amount' => $settled->amount];
        }

        PaymentLogger::depositSettledOffInvoice(
            $driver,
            $transaction->id,
            (float) $transaction->amount,
            $settled->amount,
            $settled->ratio,
        );

        // `amount` is what reaches the ledger, so moving it here is what makes
        // the credit correct — the transfer reads it back without branching.
        //
        // The fee snapshot moves with it: the invoice held the deposit, our
        // markup and the PSP fee in fixed proportion, so paying a fraction of
        // it paid that fraction of each part. `requested_amount` stays put: it
        // is the record of what we originally asked for.
        return [
            'amount' => $settled->amount,
            'settled_amount' => $settled->amount,
        ] + $this->scaledFeeSnapshot($transaction, $settled->ratio);
    }

    /**
     * The charge and its fees, scaled to the fraction of the invoice that was
     * actually paid. Rows charged before the fee snapshot existed have nothing
     * to scale and are left alone rather than back-filled with a guess.
     *
     * @return array<string, mixed>
     */
    private function scaledFeeSnapshot(Transaction $transaction, float $ratio): array
    {
        if (! $transaction->hasFeeSnapshot()) {
            return [];
        }

        return [
            'charged_amount' => round((float) $transaction->charged_amount * $ratio, 2),
            'psp_fee_amount' => round((float) $transaction->psp_fee_amount * $ratio, 2),
            'markup_amount' => round((float) $transaction->markup_amount * $ratio, 2),
        ];
    }

    /**
     * Compare what the PSP says it settled to us against what the configured
     * fees predicted. Reporting only: a divergence never blocks a credit.
     */
    private function reportFeeDrift(string $driver, Transaction $transaction, TransactionWebhookUpdate $update): void
    {
        $actual = $this->reportedSettlement($update->metadata ?? []);

        if ($actual === null || $transaction->psp_fee_amount === null) {
            return;
        }

        // What should have survived the PSP's cut: the deposit plus our markup.
        $expected = (float) $transaction->amount + (float) $transaction->markup_amount;

        if (abs($expected - $actual) < 0.01) {
            return;
        }

        PaymentLogger::feeReconciliationDrift($driver, $transaction->id, round($expected, 2), $actual);

        FeeDriftDetected::dispatch($transaction, round($expected, 2), $actual);
    }

    /**
     * What the PSP reports crediting us, where it says so at all.
     *
     * Drivers write the normalized `settlement_reported_amount`; the legacy
     * per-driver keys remain readable for historical rows. An absent figure
     * means "not reported" rather than zero.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function reportedSettlement(array $metadata): ?float
    {
        $reported = $metadata['settlement_reported_amount']
            ?? $metadata['payport_merchant_amount']
            ?? $metadata['aps_amount_out']
            ?? $metadata['sticpay_merchant_amount']
            ?? null;

        return $reported === null || $reported === '' ? null : (float) $reported;
    }
}
