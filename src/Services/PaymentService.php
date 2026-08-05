<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Services;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FeeConfigurationContract;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\ChargeCreated;
use Asciisd\CashierCore\Exceptions\InvalidPaymentDataException;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Fees\FeeBreakdown;
use Asciisd\CashierCore\Fees\FeeCalculator;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Support\PayloadSanitizer;

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
            // prepareChargeData() hook.
            $paymentData['currency'] = config('cashier-core.currency.default', 'USD');

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
     * Process a refund for a transaction.
     */
    public function processRefund(
        string $transactionId,
        ?int $amount = null,
        ?string $reason = null
    ): RefundResult {
        $model = Cashier::transactionModel();

        /** @var Transaction $transaction */
        $transaction = $model::query()
            ->where('provider_transaction_id', $transactionId)
            ->orWhere('id', $transactionId)
            ->firstOrFail();

        try {
            $provider = $this->providerFor($transaction);
            $result = $provider->refund($transaction->provider_transaction_id, $amount);

            if ($result->isSuccessful()) {
                PaymentLogger::refundProcessedSuccessfully($transactionId, $result->refundId, $result->amount);
            } else {
                PaymentLogger::refundFailed($transactionId, $result->message);
            }

            return $result;

        } catch (PaymentProcessingException $e) {
            PaymentLogger::refundProcessingFailed($transactionId, $e->getMessage());
            throw $e;
        }
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
            'amount' => $breakdown?->amount ?? $result->amount,
            'currency' => $result->currency,
            'conversion_rate' => $paymentData['conversion_rate'] ?? null,
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
