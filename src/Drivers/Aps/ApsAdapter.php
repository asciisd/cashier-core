<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Aps;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;

class ApsAdapter implements PaymentAdapterInterface
{
    /**
     * Transform an APS create-transaction response into a PaymentResult.
     * The `how` field carries the hosted checkout URL.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        $amount = (int) round((float) ($response['amount'] ?? 0));

        return new PaymentResult(
            success: true,
            transactionId: (string) $response['id'],
            status: PaymentStatus::Pending,
            amount: $amount,
            currency: (string) ($response['currency'] ?? config('cashier-core.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['how'] ?? null,
                'aps_external_id' => $response['external_id'] ?? null,
                'aps_amount_in' => $response['amount_in'] ?? null,
                'aps_amount_out' => $response['amount_out'] ?? null,
                'aps_customer_fee' => $response['customer_fee'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
        );
    }

    /**
     * Transform an APS transaction payload (retrieve) into a PaymentResult.
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $payload = $payload['payload'] ?? $payload;
        $status = $this->mapStatus($payload['status'] ?? $payload['sep31_status'] ?? null);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: (int) round((float) ($payload['amount_in'] ?? $payload['amount'] ?? 0)),
            currency: (string) ($payload['currency'] ?? config('cashier-core.currency.default', 'USD')),
            message: $payload['external_message'] ?? null,
            metadata: $this->metadataFromPayload($payload),
            processorResponse: $payload,
        );
    }

    /**
     * Transform an APS status callback into a TransactionWebhookUpdate.
     * Callbacks nest fields under a top-level `payload` key.
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $inner = $payload['payload'] ?? $payload;
        $status = $this->mapStatus($inner['status'] ?? $inner['sep31_status'] ?? null);

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            metadata: $this->metadataFromPayload($inner),
            // Carried on Canceled as well as Failed. APS reports a downstream
            // rejection as fiscal status `canceled` with the PSP's own reason in
            // `external_message` ("512: Desktop devices are not supported"), and
            // WebhookProcessor writes error_message on both statuses. Restricting
            // this to Failed left the customer and support staring at a bare
            // "Canceled" while the reason sat unread in metadata.
            errorMessage: in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)
                ? ($inner['external_message'] ?? null)
                : null,
            amount: isset($inner['amount_in']) ? (int) round((float) $inner['amount_in']) : null,
        );
    }

    /**
     * Map an APS status to a cashier-core PaymentStatus.
     *
     * APS uses three vocabularies, and which one `status` holds depends on how
     * the payload reached us:
     *
     * - Fiscal (`fiscal_status` on both paths, and `status` on neither):
     *   pending, canceled, expired, done, failed (payouts only).
     * - Deposit/sep31 (`status` on retrieve, `sep31_status` on callbacks):
     *   pending_sender, pending_external, completed, error,
     *   pending_transaction_info_update.
     * - PSP transaction (`status` on callbacks only): canceled, expired,
     *   payed, done, refund_pending, refunded, refund_rejected.
     *
     * They are matched in one table because the overlapping members (`done`,
     * `canceled`, `expired`) mean the same thing in each. The refund states
     * are reachable only from the callback vocabulary.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtolower((string) $providerStatus)) {
            'done', 'completed' => PaymentStatus::Succeeded,
            // A rejected refund leaves the original payment standing.
            'refund_rejected' => PaymentStatus::Succeeded,
            'failed', 'error', 'expired' => PaymentStatus::Failed,
            // Named like an in-flight state, but the docs are explicit that it
            // "should be interpreted as an error".
            'pending_transaction_info_update' => PaymentStatus::Failed,
            // How this package represents a refunded deposit: WebhookProcessor
            // lets a settled deposit move only to Canceled, and the Heropayment
            // and Jenapay adapters map their own `refunded` the same way.
            'canceled', 'cancelled', 'refunded' => PaymentStatus::Canceled,
            // `payed`: taken from the customer, not yet settled to APS.
            'pending_external', 'payed', 'refund_pending' => PaymentStatus::Processing,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'aps';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        return array_filter([
            'aps_transaction_id' => $payload['transaction_id'] ?? null,
            'aps_status' => $payload['status'] ?? null,
            'aps_sep31_status' => $payload['sep31_status'] ?? null,
            'aps_amount_in' => $payload['amount_in'] ?? null,
            'aps_amount_out' => $payload['amount_out'] ?? null,
            'aps_refunded' => $payload['refunded'] ?? null,
            'aps_external_message' => $payload['external_message'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
