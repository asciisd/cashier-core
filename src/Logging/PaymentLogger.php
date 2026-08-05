<?php

namespace Asciisd\CashierCore\Logging;

use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    /**
     * The log channel every payment line goes to — configurable per host via
     * cashier-core.logging.channel; null falls through to the default channel.
     */
    protected static function channel(): \Psr\Log\LoggerInterface
    {
        return Log::channel(config('cashier-core.logging.channel'));
    }

    // --- PaymentService ---

    public static function paymentProcessedSuccessfully(int $userId, ?string $transactionId, int $amount, string $provider): void
    {
        self::channel()->info('Payment processed successfully', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'provider' => $provider,
        ]);
    }

    public static function hostedPaymentPageCreated(int $userId, ?string $transactionId, int $amount, string $provider, ?string $redirectUrl): void
    {
        self::channel()->info('Hosted payment page created', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'provider' => $provider,
            'redirect_url' => $redirectUrl,
        ]);
    }

    public static function pendingPaymentCreated(int $userId, ?string $transactionId, int $amount, string $provider): void
    {
        self::channel()->info('Pending payment created (awaiting manual confirmation)', [
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'provider' => $provider,
        ]);
    }

    public static function paymentFailed(int $userId, string $provider, ?string $error): void
    {
        self::channel()->warning('Payment failed', [
            'user_id' => $userId,
            'provider' => $provider,
            'error' => $error,
        ]);
    }

    public static function invalidPaymentData(int $userId, string $provider, string $error, array $data): void
    {
        self::channel()->error('Invalid payment data', [
            'user_id' => $userId,
            'provider' => $provider,
            'error' => $error,
            'data' => $data,
        ]);
    }

    public static function paymentProcessingFailed(int $userId, string $provider, string $error): void
    {
        self::channel()->error('Payment processing failed', [
            'user_id' => $userId,
            'provider' => $provider,
            'error' => $error,
        ]);
    }

    public static function paymentProviderNotFound(string $provider, string $error): void
    {
        self::channel()->error('Payment provider not found', [
            'provider' => $provider,
            'error' => $error,
        ]);
    }

    public static function refundProcessedSuccessfully(string $transactionId, ?string $refundId, ?int $amount): void
    {
        self::channel()->info('Refund processed successfully', [
            'transaction_id' => $transactionId,
            'refund_id' => $refundId,
            'amount' => $amount,
        ]);
    }

    public static function refundFailed(string $transactionId, ?string $error): void
    {
        self::channel()->warning('Refund failed', [
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    public static function refundProcessingFailed(string $transactionId, string $error): void
    {
        self::channel()->error('Refund processing failed', [
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    public static function cannotSyncWithoutProviderTransactionId(int $transactionId): void
    {
        self::channel()->warning('Cannot sync transaction without provider transaction ID', [
            'transaction_id' => $transactionId,
        ]);
    }

    public static function transactionNotFoundAtProvider(int $transactionId, ?string $providerTransactionId, string $provider): void
    {
        self::channel()->warning('Transaction not found at provider', [
            'transaction_id' => $transactionId,
            'provider_transaction_id' => $providerTransactionId,
            'provider' => $provider,
        ]);
    }

    public static function transactionSyncedSuccessfully(int $transactionId, ?string $providerTransactionId, string $status): void
    {
        self::channel()->info('Transaction synced successfully', [
            'transaction_id' => $transactionId,
            'provider_transaction_id' => $providerTransactionId,
            'status' => $status,
        ]);
    }

    public static function transactionSyncUnsupported(int $transactionId, string $provider, string $error): void
    {
        self::channel()->warning('Payment provider cannot retrieve transactions', [
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'error' => $error,
        ]);
    }

    public static function transactionSyncAmountMismatch(int $transactionId, float $localAmount, int $providerAmount): void
    {
        self::channel()->warning('Provider reports a different amount than the local transaction', [
            'transaction_id' => $transactionId,
            'local_amount' => $localAmount,
            'provider_amount' => $providerAmount,
        ]);
    }

    public static function providerNotFoundWhileSyncing(int $transactionId, string $provider, string $error): void
    {
        self::channel()->error('Payment provider not found while syncing', [
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'error' => $error,
        ]);
    }

    public static function transactionSyncFailed(int $transactionId, string $error): void
    {
        self::channel()->error('Failed to sync transaction', [
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    // --- Knet ---

    public static function knetTransactionNotFoundForSuccess(string $trackId, ?string $paymentId): void
    {
        self::channel()->warning('Transaction not found for successful Knet payment', [
            'track_id' => $trackId,
            'knet_payment_id' => $paymentId,
        ]);
    }

    public static function knetTransactionConfirmedSuccessful(int $transactionId, string $trackId, mixed $amount): void
    {
        self::channel()->info('Knet transaction status confirmed as successful', [
            'transaction_id' => $transactionId,
            'track_id' => $trackId,
            'amount' => $amount,
        ]);
    }

    public static function noTradingAccountForKnetTransfer(int $transactionId): void
    {
        self::channel()->info('No trading account specified for Knet fund transfer', [
            'transaction_id' => $transactionId,
        ]);
    }

    public static function knetFundsTransferred(int $transactionId, string $tradingAccountLogin, mixed $amount, mixed $mt5Ticket): void
    {
        self::channel()->info('Knet funds successfully transferred to trading account', [
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'mt5_ticket' => $mt5Ticket,
        ]);
    }

    public static function knetFundsTransferFailed(int $transactionId, string $tradingAccountLogin, mixed $amount, string $currency): void
    {
        self::channel()->error('Failed to transfer Knet funds to trading account', [
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    public static function knetFundsTransferException(int $transactionId, string $error, string $trace): void
    {
        self::channel()->error('Exception during Knet trading account fund transfer', [
            'transaction_id' => $transactionId,
            'error' => $error,
            'trace' => $trace,
        ]);
    }

    public static function knetTransactionNotFoundForFailure(string $trackId, ?string $paymentId): void
    {
        self::channel()->warning('Transaction not found for failed Knet payment', [
            'track_id' => $trackId,
            'knet_payment_id' => $paymentId,
        ]);
    }

    public static function knetTransactionUpdatedToFailed(int $transactionId, string $trackId, ?string $error): void
    {
        self::channel()->info('Knet transaction status updated to failed', [
            'transaction_id' => $transactionId,
            'track_id' => $trackId,
            'error' => $error,
        ]);
    }

    public static function knetProviderCreationFailed(string $error): void
    {
        self::channel()->error('Failed to create Knet provider', ['error' => $error]);
    }

    // --- Paytiko ---

    public static function paytikoTransactionNotFoundForSuccess(string $orderId, ?string $transactionId): void
    {
        self::channel()->warning('Transaction not found for successful Paytiko payment', [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);
    }

    public static function paytikoTransactionConfirmedSuccessful(int $transactionId, string $orderId, mixed $initialAmount, mixed $actualReceivedAmount): void
    {
        self::channel()->info('Transaction status confirmed as successful', [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'initial_amount' => $initialAmount,
            'actual_received_amount' => $actualReceivedAmount,
        ]);
    }

    public static function noTradingAccountForPaytikoTransfer(int $transactionId): void
    {
        self::channel()->info('No trading account specified for fund transfer', [
            'transaction_id' => $transactionId,
        ]);
    }

    public static function paytikoFundsTransferred(int $transactionId, string $tradingAccountLogin, mixed $amount, mixed $mt5Ticket): void
    {
        self::channel()->info('Funds successfully transferred to trading account', [
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'mt5_ticket' => $mt5Ticket,
        ]);
    }

    public static function paytikoFundsTransferFailed(int $transactionId, string $tradingAccountLogin, mixed $amount, string $currency): void
    {
        self::channel()->error('Failed to transfer funds to trading account', [
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    public static function paytikoFundsTransferException(int $transactionId, string $error, string $trace): void
    {
        self::channel()->error('Exception occurred during trading account fund transfer', [
            'transaction_id' => $transactionId,
            'error' => $error,
            'trace' => $trace,
        ]);
    }

    public static function paytikoTransactionNotFoundForFailure(string $orderId, ?string $transactionId): void
    {
        self::channel()->warning('Transaction not found for failed Paytiko payment', [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);
    }

    public static function paytikoTransactionUpdatedToFailed(int $transactionId, string $orderId, ?string $declineReason): void
    {
        self::channel()->info('Transaction status updated to failed', [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'decline_reason' => $declineReason,
        ]);
    }

    public static function paytikoProviderCreationFailed(string $error): void
    {
        self::channel()->error('Failed to create Paytiko processor', ['error' => $error]);
    }

    // --- Paytiko Webhook ---

    public static function webhookTransactionNotFound(string $orderId, ?string $transactionId): void
    {
        self::channel()->warning('Transaction not found for webhook payment method update', [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);
    }

    public static function transactionUpdatedFromWebhook(string $orderId, int $transactionId, ?string $paytikoTransactionId, string $status): void
    {
        self::channel()->info('Transaction updated from webhook using DTO', [
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'paytiko_transaction_id' => $paytikoTransactionId,
            'status' => $status,
        ]);
    }

    public static function paymentMethodExtractionFailed(string $error, array $payload): void
    {
        self::channel()->warning('Failed to extract payment method from Paytiko payload', [
            'error' => $error,
            'payload' => $payload,
        ]);
    }

    // --- Nova Actions ---

    public static function novaPaytikoResyncInitiated(?int $userId, array $transactionIds, array $orderIds, int $resyncedCount): void
    {
        self::channel()->info('Nova: Paytiko webhook resync initiated', [
            'user_id' => $userId,
            'transaction_ids' => $transactionIds,
            'order_ids' => $orderIds,
            'resynced_count' => $resyncedCount,
        ]);
    }

    public static function novaPaytikoResyncFailed(?int $userId, array $transactionIds, string $error, string $trace): void
    {
        self::channel()->error('Nova: Paytiko webhook resync failed', [
            'user_id' => $userId,
            'transaction_ids' => $transactionIds,
            'error' => $error,
            'trace' => $trace,
        ]);
    }

    public static function mt5TransferFailedDuringBankApproval(int $transactionId, string $tradingAccountLogin): void
    {
        self::channel()->error('MT5 fund transfer failed during bank deposit approval', [
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
        ]);
    }

    public static function bankDepositMt5TransferException(int $transactionId, string $error): void
    {
        self::channel()->error('Exception during bank deposit MT5 transfer', [
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    // --- Payment Invoices ---

    public static function paymentInvoiceSent(int $transactionId, int $userId): void
    {
        self::channel()->info('Payment invoice email sent', [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
        ]);
    }

    public static function paymentInvoiceFailed(int $transactionId, string $error): void
    {
        self::channel()->error('Failed to send payment invoice email', [
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    // --- Paytiko Raw Payload ---

    public static function rawPayloadTransactionNotFound(string $orderId): void
    {
        self::channel()->warning('App transaction not found for persisting raw Paytiko payload', [
            'order_id' => $orderId,
        ]);
    }

    public static function rawPayloadPersisted(int $transactionId, string $orderId): void
    {
        self::channel()->info('Raw Paytiko webhook payload persisted to transaction', [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
        ]);
    }

    // --- Direct provider webhooks (APS, Jenapay, Heropayment) ---

    public static function providerWebhookReceived(string $provider, ?string $providerTransactionId, ?string $status): void
    {
        self::channel()->info('Payment provider webhook received', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload  Redacted by the caller. Without
     *                                              it a rejected callback cannot be told apart from a key
     *                                              mismatch, a stale invoice, or a signing-scheme change.
     */
    public static function providerWebhookSignatureInvalid(string $provider, ?string $signature, ?array $payload = null): void
    {
        self::channel()->warning('Payment provider webhook signature verification failed', array_filter([
            'provider' => $provider,
            'signature' => $signature,
            'payload' => $payload,
        ], fn ($value) => $value !== null));
    }

    /**
     * A provider that answered successfully at the transport level but refused
     * the operation in the response body.
     *
     * @param  list<string>  $codes  Provider-specific error codes
     * @param  array<string, mixed>  $errors  Field-level detail, which is usually
     *                                        the only thing that names what was wrong
     */
    public static function providerRequestRejected(
        string $provider,
        string $operation,
        string $message,
        array $codes = [],
        array $errors = [],
    ): void {
        self::channel()->error('Provider request rejected', array_filter([
            'provider' => $provider,
            'operation' => $operation,
            'message' => $message,
            'codes' => $codes,
            'errors' => $errors,
        ], fn ($value) => $value !== [] && $value !== null));
    }

    /**
     * A browser landing on a provider's POSTed return endpoint.
     *
     * Purely informational — these pages never write to a transaction, so this
     * exists to tell "the customer never came back" apart from "the customer
     * came back but no callback followed".
     */
    public static function providerReturnReceived(string $provider, ?string $providerTransactionId, ?int $code): void
    {
        self::channel()->info('Payment provider return redirect received', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'code' => $code,
        ]);
    }

    /**
     * A callback whose environment disagrees with the connection that received
     * it — a sandbox notification arriving on a live account, or the reverse.
     *
     * Providers that share one endpoint between sandbox and production
     * distinguish them by a field in the payload. Acting on the wrong one
     * would credit real money for a test payment.
     */
    public static function providerCallbackEnvironmentMismatch(
        string $provider,
        ?string $providerTransactionId,
        string $expected,
        ?string $received,
    ): void {
        self::channel()->error('Payment provider callback rejected (environment mismatch)', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'expected' => $expected,
            'received' => $received,
        ]);
    }

    /**
     * A callback that could not be confirmed against the provider's own ledger.
     *
     * Raised where the callback itself carries no status and the outcome has to
     * be resolved with an outbound lookup that then failed. The transaction is
     * held rather than credited.
     */
    public static function providerCallbackUnconfirmed(string $provider, string $providerTransactionId, string $reason): void
    {
        self::channel()->error('Payment provider callback could not be confirmed', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'reason' => $reason,
        ]);
    }

    public static function providerWebhookMissingTransactionId(string $provider): void
    {
        self::channel()->warning('Payment provider webhook has no resolvable transaction id', [
            'provider' => $provider,
        ]);
    }

    public static function providerWebhookTransactionNotFound(string $provider, string $providerTransactionId): void
    {
        self::channel()->warning('Transaction not found for payment provider webhook', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
        ]);
    }

    public static function providerWebhookIgnoredDuplicate(string $provider, int $transactionId, string $status, string $source = 'webhook'): void
    {
        self::channel()->info('Payment provider update ignored (duplicate status)', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'status' => $status,
            'source' => $source,
        ]);
    }

    public static function providerWebhookIgnoredOutOfOrder(string $provider, int $transactionId, string $currentStatus, string $incomingStatus, string $source = 'webhook'): void
    {
        self::channel()->warning('Payment provider update ignored (out-of-order status transition)', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'current_status' => $currentStatus,
            'incoming_status' => $incomingStatus,
            'source' => $source,
        ]);
    }

    public static function providerTransactionConfirmedSuccessful(string $provider, int $transactionId, string $providerTransactionId, float|int $amount, string $source = 'webhook'): void
    {
        self::channel()->info('Provider confirmed transaction successful', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'provider_transaction_id' => $providerTransactionId,
            'amount' => $amount,
            'source' => $source,
        ]);
    }

    public static function noTradingAccountForProviderTransfer(string $provider, int $transactionId): void
    {
        self::channel()->info('No trading account login specified for provider deposit transfer', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
        ]);
    }

    public static function providerFundsTransferred(string $provider, int $transactionId, int $tradingAccountLogin, float|int $amount, int $ticket): void
    {
        self::channel()->info('Provider deposit funds transferred to trading account', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'mt5_ticket' => $ticket,
        ]);
    }

    public static function providerFundsTransferFailed(string $provider, int $transactionId, int $tradingAccountLogin, float|int $amount, string $currency): void
    {
        self::channel()->error('Provider deposit MT5 fund transfer failed', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'trading_account_login' => $tradingAccountLogin,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    public static function providerFundsAlreadyTransferred(string $provider, int $transactionId, string $mt5Ticket): void
    {
        self::channel()->info('Provider deposit already credited to trading account, skipping transfer', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'mt5_ticket' => $mt5Ticket,
        ]);
    }

    public static function providerFundsCreditInFlight(string $provider, int $transactionId, string $claimedAt): void
    {
        self::channel()->warning('Provider deposit credit already claimed by another process, skipping transfer', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'claimed_at' => $claimedAt,
        ]);
    }

    public static function providerFundsTransferException(string $provider, int $transactionId, string $error): void
    {
        self::channel()->error('Provider deposit MT5 fund transfer threw exception', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'error' => $error,
        ]);
    }

    public static function providerChargeRequestFailed(string $provider, ?int $httpStatus, string $error): void
    {
        self::channel()->error('Provider charge request failed', [
            'provider' => $provider,
            'http_status' => $httpStatus,
            'error' => $error,
        ]);
    }

    public static function providerTransactionLookupFailed(string $provider, string $providerTransactionId, int $httpStatus, string $body): void
    {
        self::channel()->warning('Provider transaction lookup failed', [
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'http_status' => $httpStatus,
            'body' => $body,
        ]);
    }

    public static function providerQuoteLookupFailed(string $provider, string $lookup, string $error): void
    {
        self::channel()->warning('Provider quote lookup failed', [
            'provider' => $provider,
            'lookup' => $lookup,
            'error' => $error,
        ]);
    }

    public static function providerHoldRequiresAttention(string $provider, int $transactionId, string $providerTransactionId): void
    {
        self::channel()->warning('Provider transaction placed on hold (user KYC required at provider)', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'provider_transaction_id' => $providerTransactionId,
        ]);
    }

    /**
     * A client-controlled payment (crypto) landed off its invoice, so the
     * deposit was reconciled to what actually arrived before crediting.
     */
    public static function depositHeldForReview(string $provider, int $transactionId, string $reason): void
    {
        self::channel()->warning('Deposit success held for review instead of credited', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);
    }

    public static function depositSettledOffInvoice(
        string $provider,
        int $transactionId,
        float $invoicedAmount,
        float $settledAmount,
        float $ratio,
    ): void {
        self::channel()->warning('Deposit settled off its invoiced amount', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'invoiced_amount' => $invoicedAmount,
            'settled_amount' => $settledAmount,
            'paid_ratio' => round($ratio, 4),
            'direction' => $ratio < 1.0 ? 'underpaid' : 'overpaid',
        ]);
    }

    /**
     * What the PSP reports settling to us diverges from what the configured
     * fees predicted — the alarm for a Nova fee drifting off the real contract.
     */
    public static function feeReconciliationDrift(
        string $provider,
        int $transactionId,
        float $expectedReceived,
        float $actualReceived,
    ): void {
        self::channel()->warning('Provider settlement diverges from configured fees', [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'expected_received' => $expectedReceived,
            'actual_received' => $actualReceived,
            'drift' => round($actualReceived - $expectedReceived, 2),
        ]);
    }
}
