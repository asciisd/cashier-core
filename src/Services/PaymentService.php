<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Contracts\ConvertsChargeCurrency;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FeeConfigurationContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\ChargeCreated;
use Asciisd\CashierCore\Events\RefundFailed;
use Asciisd\CashierCore\Events\RefundSucceeded;
use Asciisd\CashierCore\Exceptions\InvalidPaymentDataException;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Fees\FeeBreakdown;
use Asciisd\CashierCore\Fees\FeeCalculator;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Models\Refund;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Support\PayloadSanitizer;
use Asciisd\CashierCore\Support\RefusingCurrencyConverter;
use Illuminate\Support\Facades\DB;

/**
 * Charge orchestration: resolve the connection, price the deposit, let the
 * driver shape its own payload, take the charge, persist the transaction.
 *
 * Provider-specific quirks live in the drivers behind {@see PreparesChargeData}
 * — this class carries no per-driver branches. Account resolution lives behind
 * {@see ResolvesFundingAccount}; the host binds a resolver that understands its
 * own account model.
 */
class PaymentService
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly WebhookProcessor $webhookProcessor,
        private readonly ResolvesFundingAccount $fundingAccounts,
        private readonly FeeCalculator $fees = new FeeCalculator,
        private readonly PayloadSanitizer $sanitizer = new PayloadSanitizer,
        private readonly ConvertsChargeCurrency $currencyConverter = new RefusingCurrencyConverter,
    ) {}

    /**
     * Process a payment for a customer through a named connection.
     *
     * The connection names the PSP account that handles the charge — one entry
     * in `cashier-core.connections`, carrying that account's credentials and
     * routing. Several connections may share a driver, which is why callers
     * should name a connection rather than a driver.
     *
     * @param  array<string, mixed>  $paymentData
     * @param  FeeConfigurationContract|null  $feeConfiguration  the selected
     *         method's pricing; null (a programmatic or admin charge with no
     *         method to price against) passes the amount through at face value
     * @param  array<string, string|null>  $selectedMethodAttributes  the
     *         `payment_method_*` columns implied by the method the user picked
     *         before reaching the PSP; the provider's own snapshot overrides
     *         them where it knows something
     */
    public function processPayment(
        CustomerContract $customer,
        array $paymentData,
        ?string $connection = null,
        ?FeeConfigurationContract $feeConfiguration = null,
        array $selectedMethodAttributes = [],
    ): PaymentResult {
        $connection = $connection
            ?: (string) (config('cashier-core.default_connection') ?? config('transactions.default_provider', 'manual'));

        // The plain string persisted to `transactions.provider` — coarse where
        // the connection is exact, so webhook correlation and historical rows
        // are unaffected by which account took the charge.
        $driver = Connections::driverFor($connection);

        try {
            $paymentProvider = $this->registry->get($connection);

            $paymentData['metadata'] = array_merge(
                $paymentData['metadata'] ?? [],
                [
                    'user_id' => $customer->cashierId(),
                    'user_email' => $customer->cashierEmail(),
                ]
            );

            // Providers that identify the customer by funding account read
            // this, so it is resolved even when the deposit named the account
            // indirectly (by reference) — the bound resolver owns that lookup.
            $ledgerAccount = $this->fundingAccounts->ledgerAccountFor($customer, $paymentData);

            if ($ledgerAccount !== null) {
                $paymentData['metadata']['trading_account_login'] = $ledgerAccount;
            }

            // Always the default currency: callers cannot pick one. A driver
            // that charges in a fixed currency overrides this in its
            // prepareChargeData() hook, and the conversion below reconciles
            // the two.
            $accountCurrency = strtoupper((string) config('cashier-core.currency.default', 'USD'));
            $paymentData['currency'] = $accountCurrency;
            $paymentData['account_currency'] = $accountCurrency;

            // Resolve the fees before charging, and hand the PSP the amount
            // that makes the customer's deposit survive them. Every driver
            // reads `amount` from this array, so this is the only seam that
            // needs to know — but it does mean `$result->amount` from here on
            // is the requested figure, not the deposit.
            $breakdown = $this->resolveFees($feeConfiguration, $paymentData);

            if ($breakdown !== null) {
                $paymentData['amount'] = $breakdown->requestedAmount;
            }

            // Each driver owns its own quirks: routing hints, fixed
            // currencies, billing shape. This hook replaces every
            // provider-specific branch that used to live here.
            if ($paymentProvider instanceof PreparesChargeData) {
                $paymentData = $paymentProvider->prepareChargeData($customer, $connection, $paymentData);
            }

            // A driver that cannot be sent the account's currency declared its
            // own above. Convert here, not earlier: fees are resolved against
            // the account currency and its configuration, and must stay that
            // way — the customer's deposit and every limit it is checked
            // against are denominated in the account currency, not the PSP's.
            // What the customer deposited, in the account currency, before any
            // conversion. `$result->amount` echoes the PSP's figure, which on a
            // foreign charge is denominated in the charge currency — it must
            // never become `amount`, and with no fee configuration to fall back
            // on it otherwise would.
            $paymentData['account_amount'] = (float) ($paymentData['amount'] ?? 0);

            $chargeCurrency = strtoupper((string) ($paymentData['currency'] ?? $accountCurrency));

            if ($chargeCurrency !== $accountCurrency) {
                $conversion = $this->currencyConverter->convert(
                    (float) ($paymentData['amount'] ?? 0),
                    $accountCurrency,
                    $chargeCurrency,
                );

                $paymentData['amount'] = $conversion->amount;
                $paymentData['charge_currency'] = $conversion->currency;
                $paymentData['charge_amount'] = $conversion->amount;
                $paymentData['conversion_rate'] = $conversion->rate;
            }

            $result = $paymentProvider->charge($paymentData);

            $transaction = $this->createTransactionRecord(
                $customer,
                $result,
                $driver,
                $connection,
                $paymentData,
                $breakdown,
                $selectedMethodAttributes,
            );

            ChargeCreated::dispatch($transaction);

            if ($result->isSuccessful()) {
                PaymentLogger::paymentProcessedSuccessfully($customer->cashierId(), $result->transactionId, $result->amount, $driver);
            } elseif ($result->requiresAction()) {
                PaymentLogger::hostedPaymentPageCreated($customer->cashierId(), $result->transactionId, $result->amount, $driver, $result->getRedirectUrl());
            } elseif ($result->status === PaymentStatus::Pending) {
                PaymentLogger::pendingPaymentCreated($customer->cashierId(), $result->transactionId, $result->amount, $driver);
            } else {
                PaymentLogger::paymentFailed($customer->cashierId(), $driver, $result->message);
            }

            return $result;

        } catch (InvalidPaymentDataException $e) {
            PaymentLogger::invalidPaymentData($customer->cashierId(), $driver, $e->getMessage(), $paymentData);
            throw $e;
        } catch (PaymentProcessingException $e) {
            PaymentLogger::paymentProcessingFailed($customer->cashierId(), $driver, $e->getMessage());
            throw $e;
        } catch (ProcessorNotFoundException $e) {
            PaymentLogger::paymentProviderNotFound($driver, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refund a transaction, in whole or in part.
     *
     * `$amount` is in major units, matching `transactions.amount` — a partial
     * refund of 95.50 is `95.5`, not `9550`. Omit it to refund whatever is
     * still outstanding.
     *
     * Every attempt is persisted, so the refunded total is a fact about the
     * database rather than something each caller has to recompute. Before
     * this, nothing wrote a refund row at all: a caller summing
     * `$transaction->refunds()` to decide whether more could be refunded
     * always read zero, and the same transaction could be refunded
     * repeatedly.
     *
     * @throws PaymentProcessingException when the request exceeds what is left
     */
    public function processRefund(
        string $transactionId,
        ?float $amount = null,
        ?string $reason = null
    ): RefundResult {
        $model = Cashier::transactionModel();

        /*
         * Scopes off, and the two identifiers grouped.
         *
         * A refund is issued by an operator, not by the account holder, so a
         * host tenant scope would hide the row. And the alternation has to be
         * parenthesised: `where(a)->orWhere(b)` under a global scope compiles
         * to `scope AND a OR b`, so the `b` branch escapes the scope entirely
         * and could match another customer's transaction.
         */
        /** @var Transaction $transaction */
        $transaction = $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->where(fn ($query) => $query
                ->where('provider_transaction_id', $transactionId)
                ->orWhere('id', $transactionId))
            ->firstOrFail();

        // The row is reserved under a lock and the provider is called outside
        // it. Deciding and reserving in one atomic step is what stops two
        // concurrent refunds from both seeing the same remaining balance;
        // holding that lock across a PSP HTTP call would instead block every
        // webhook for this transaction for the length of the request.
        $refund = $this->reserveRefund($transaction, $amount, $reason);

        try {
            $provider = $this->providerFor($transaction);
            $result = $provider->refund($transaction->provider_transaction_id, (float) $refund->amount);
        } catch (PaymentProcessingException $e) {
            // Release the reservation, or a provider outage would permanently
            // consume the customer's remaining refundable balance.
            $this->settleRefund($refund, null);
            PaymentLogger::refundProcessingFailed($transactionId, $e->getMessage());

            throw $e;
        }

        $this->settleRefund($refund, $result);

        if ($result->isSuccessful()) {
            PaymentLogger::refundProcessedSuccessfully($transactionId, $result->refundId, $result->amount);
            RefundSucceeded::dispatch($transaction, $refund);
        } else {
            PaymentLogger::refundFailed($transactionId, $result->message);
            RefundFailed::dispatch($transaction, $refund);
        }

        return $result;
    }

    /**
     * Claim part of a transaction's refundable balance under a row lock.
     *
     * Pending and processing refunds count against the balance as well as
     * succeeded ones — an in-flight attempt has to hold its share, or two
     * requests moments apart both pass.
     *
     * @throws PaymentProcessingException
     */
    private function reserveRefund(Transaction $transaction, ?float $amount, ?string $reason): Refund
    {
        $refundModel = Cashier::refundModel();

        return DB::transaction(function () use ($transaction, $amount, $reason, $refundModel): Refund {
            $model = Cashier::transactionModel();

            /** @var Transaction $locked */
            $locked = $model::query()
                ->withoutGlobalScopes($model::cashierBypassedScopes())
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $charged = round((float) $locked->amount, 2);

            $claimed = round((float) $refundModel::query()
                ->where('transaction_id', $locked->getKey())
                ->whereIn('status', [
                    RefundStatus::Succeeded->value,
                    RefundStatus::Pending->value,
                    RefundStatus::Processing->value,
                ])
                ->sum('amount'), 2);

            $remaining = round($charged - $claimed, 2);

            if ($remaining <= 0.0) {
                throw new PaymentProcessingException(
                    "Transaction [{$locked->getKey()}] is already fully refunded."
                );
            }

            $requested = round($amount ?? $remaining, 2);

            if ($requested <= 0.0) {
                throw new PaymentProcessingException('A refund amount must be greater than zero.');
            }

            if ($requested > $remaining) {
                throw new PaymentProcessingException(
                    "Refund of {$requested} exceeds the {$remaining} still refundable on transaction [{$locked->getKey()}]."
                );
            }

            return $refundModel::query()->create([
                'transaction_id' => $locked->getKey(),
                'amount' => $requested,
                'currency' => $locked->currency,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Write the provider's answer onto a reserved refund.
     *
     * A null result means the call never produced one — the reservation is
     * released as failed so it stops holding the balance.
     */
    private function settleRefund(Refund $refund, ?RefundResult $result): void
    {
        if ($result === null) {
            $refund->update(['status' => RefundStatus::Failed, 'failed_at' => now()]);

            return;
        }

        $refund->update([
            'provider_refund_id' => $result->refundId,
            'status' => $result->status,
            'provider_payload' => $result->metadata,
            'processed_at' => $result->isSuccessful() ? now() : null,
            'failed_at' => $result->isSuccessful() ? null : now(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function getAvailableProviders(): array
    {
        return $this->registry->getAvailable();
    }

    /**
     * Check if a connection (or driver) supports a specific feature.
     */
    public function providerSupports(\BackedEnum|string $connection, string $feature): bool
    {
        try {
            return $this->registry->get($connection)->supports($feature);
        } catch (ProcessorNotFoundException) {
            return false;
        }
    }

    /**
     * Pull a transaction's current state from its provider and apply it.
     *
     * The provider is resolved from the transaction's own connection, so each
     * PSP account is queried with the credentials that took the charge. The
     * retrieved state then goes through {@see WebhookProcessor}, the same path
     * a webhook takes — meaning a sync that recovers a missed success also
     * fires the deposit events and credits the ledger, rather than leaving a
     * deposit marked succeeded but never funded.
     *
     * @return bool whether the provider was reached and its answer applied;
     *              false covers a missing id, an unknown record, an unsupported
     *              driver, and transport errors — all logged
     */
    public function syncTransaction(Transaction $transaction): bool
    {
        $driver = $this->driverOf($transaction);

        try {
            if (! $transaction->provider_transaction_id) {
                PaymentLogger::cannotSyncWithoutProviderTransactionId($transaction->id);

                return false;
            }

            $provider = $this->providerFor($transaction);
            $result = $provider->retrieve($transaction->provider_transaction_id);

            if (! $result) {
                PaymentLogger::transactionNotFoundAtProvider($transaction->id, $transaction->provider_transaction_id, $driver);

                return false;
            }

            $this->webhookProcessor->applyUpdate(
                $driver,
                $transaction,
                $this->updateFromRetrievedResult($transaction, $result),
                source: 'sync',
            );

            PaymentLogger::transactionSyncedSuccessfully($transaction->id, $transaction->provider_transaction_id, $result->status->value);

            return true;

        } catch (ProcessorNotFoundException $e) {
            PaymentLogger::providerNotFoundWhileSyncing($transaction->id, $driver, $e->getMessage());

            return false;
        } catch (\BadMethodCallException $e) {
            // Providers that cannot be queried say so by throwing — bank
            // transfer and manual flows have no remote ledger to read.
            PaymentLogger::transactionSyncUnsupported($transaction->id, $driver, $e->getMessage());

            return false;
        } catch (\Exception $e) {
            PaymentLogger::transactionSyncFailed($transaction->id, $e->getMessage());

            return false;
        }
    }

    /**
     * The provider for a stored transaction, bound to the account that took
     * the charge.
     *
     * Some PSPs issue one merchant account per product and only that account
     * can see or refund its own transactions, but `refund()`/`retrieve()`
     * receive nothing but a transaction id — hence the stored connection name.
     * Transactions predating the column fall back to the driver, which
     * resolves to that driver's primary account and is the prior behaviour.
     */
    private function providerFor(Transaction $transaction): PaymentProcessorInterface
    {
        // The registry accepts a string or a BackedEnum — a host model may
        // cast `provider` to its own display enum.
        return $this->registry->get($transaction->connection ?: $transaction->provider);
    }

    /**
     * The transaction's driver as a plain string, whatever the host model
     * casts the `provider` column to.
     */
    private function driverOf(Transaction $transaction): string
    {
        return Connections::normalizeDriver($transaction->provider) ?? (string) $transaction->provider;
    }

    /**
     * Normalize a retrieved PaymentResult into the update shape the webhook
     * processor applies.
     */
    private function updateFromRetrievedResult(Transaction $transaction, PaymentResult $result): TransactionWebhookUpdate
    {
        // `transactions.amount` is decimal(16,2) while PaymentResult::amount is
        // an int, so the provider's figure has already lost its cents by the
        // time it reaches here. Sync reports a divergence and leaves the
        // charged amount alone rather than rounding the customer's deposit.
        //
        // Compared against what we asked the PSP for, not against `amount`:
        // once fees are grossed up those are different numbers by design, and
        // the provider only ever echoes the former.
        $expected = (float) ($transaction->requested_amount ?? $transaction->amount);

        if ($result->amount > 0 && abs($expected - (float) $result->amount) >= 1.0) {
            PaymentLogger::transactionSyncAmountMismatch($transaction->id, $expected, $result->amount);
        }

        $processorResponse = is_array($result->processorResponse)
            ? $result->processorResponse
            : array_filter(['response' => $result->processorResponse], fn ($value) => $value !== null);

        $paymentProcessor = $result->metadata['payment_processor']
            ?? ($processorResponse['PaymentProcessor'] ?? null);

        return new TransactionWebhookUpdate(
            status: $result->status,
            processorResponse: $processorResponse,
            paymentMethodSnapshot: $result->paymentMethodSnapshot,
            metadata: $result->metadata,
            errorCode: $result->errorCode,
            errorMessage: $result->message,
            additionalAttributes: $paymentProcessor && ! $transaction->payment_processor
                ? ['payment_processor' => $paymentProcessor]
                : [],
        );
    }

    /**
     * Create the transaction record for a charge.
     *
     * @param  array<string, mixed>  $paymentData
     * @param  array<string, string|null>  $selectedMethodAttributes
     */
    private function createTransactionRecord(
        CustomerContract $customer,
        PaymentResult $result,
        string $driver,
        string $connection,
        array $paymentData,
        ?FeeBreakdown $breakdown,
        array $selectedMethodAttributes,
    ): Transaction {
        $transactionData = [
            'user_id' => $customer->cashierId(),
            'trading_account_id' => $this->fundingAccounts->fundingAccountIdFor($customer, $paymentData),
            'provider' => $driver,
            // The exact account, where the driver has several. `provider` stays
            // coarse so webhook correlation and historical rows are unaffected.
            'connection' => $connection,
            'provider_transaction_id' => $result->transactionId,
            'type' => TransactionType::Deposit,
            // The customer's deposit, NOT `$result->amount` — that echoes the
            // grossed-up figure we sent the PSP. `amount` is what reaches the
            // ledger, and the credit path reads it directly.
            'amount' => $breakdown?->amount ?? $paymentData['account_amount'] ?? $result->amount,
            // The account currency, NOT $result->currency: on a foreign charge
            // the driver reports the currency it invoiced, and storing that
            // would put every downstream sum — deposit limits, reporting, the
            // ledger credit — into a currency the account is not held in.
            'currency' => $paymentData['account_currency'] ?? $result->currency,
            'conversion_rate' => $paymentData['conversion_rate'] ?? null,
            'charge_currency' => $paymentData['charge_currency'] ?? null,
            'charge_amount' => $paymentData['charge_amount'] ?? null,
            'vendor_fees' => $paymentData['vendor_fees'] ?? 0,
            'fixed_vendor_fees' => $paymentData['fixed_vendor_fees'] ?? 0,
            'status' => $result->status,
            'description' => $paymentData['description'] ?? null,
            'metadata' => array_merge($paymentData['metadata'] ?? [], $result->metadata ?? []),
            // Sanitized on the way in, same rules as the webhook path — the
            // charge response carries the same PSP PII a callback does.
            'provider_payload' => is_array($result->processorResponse)
                ? $this->sanitizer->forStorage($driver, $result->processorResponse)
                : $result->processorResponse,
            'processed_at' => $result->isSuccessful() ? now() : null,
            'failed_at' => $result->isFailed() ? now() : null,
            'error_message' => $result->isFailed() ? $result->message : null,
        ];

        // The resolved fees, as absolutes. Stored rather than recomputed so a
        // later rate change in the method catalog cannot rewrite what this
        // charge cost.
        if ($breakdown !== null) {
            $transactionData += [
                'charged_amount' => $breakdown->chargedAmount,
                'requested_amount' => $breakdown->requestedAmount,
                'psp_fee_amount' => $breakdown->pspFee,
                'markup_amount' => $breakdown->markup,
                'settlement_mode' => $breakdown->settlementMode,
            ];
        }

        // Record the method the user picked before they ever reach the PSP. On
        // a hosted-page provider the charge is only Pending here and no card or
        // wallet detail exists yet, so without this the transaction shows no
        // payment method at all until the webhook lands — and never, if the
        // user abandons the page.
        $transactionData = array_merge($transactionData, $selectedMethodAttributes);

        // Anything the provider already knows is more specific than the user's
        // selection, so it wins — but only where it actually knows something.
        $transactionData = array_merge(
            $transactionData,
            PaymentMethodSnapshotAttributes::known($result->paymentMethodSnapshot),
        );

        $model = Cashier::transactionModel();

        return $model::query()->create($transactionData);
    }

    /**
     * What this deposit costs, or null when there is no configuration to price
     * it against.
     *
     * @param  array<string, mixed>  $paymentData
     */
    private function resolveFees(?FeeConfigurationContract $feeConfiguration, array $paymentData): ?FeeBreakdown
    {
        $amount = (float) ($paymentData['amount'] ?? 0);

        if ($feeConfiguration === null || $amount <= 0) {
            return null;
        }

        return $this->fees->for($feeConfiguration, $amount);
    }
}
