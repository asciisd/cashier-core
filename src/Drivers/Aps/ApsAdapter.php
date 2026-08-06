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
            errorMessage: $status === PaymentStatus::Failed ? ($inner['external_message'] ?? null) : null,
            amount: isset($inner['amount_in']) ? (int) round((float) $inner['amount_in']) : null,
        );
    }

    /**
     * Map an APS fiscal/deposit status to a cashier-core PaymentStatus.
     *
     * Fiscal statuses: pending, canceled, expired, done, failed.
     * Deposit (sep31) statuses: pending_sender, pending_external, completed, error.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtolower((string) $providerStatus)) {
            'done', 'completed' => PaymentStatus::Succeeded,
            'failed', 'error', 'expired' => PaymentStatus::Failed,
            'canceled', 'cancelled' => PaymentStatus::Canceled,
            'pending_external' => PaymentStatus::Processing,
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
