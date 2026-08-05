<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Jenapay;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

class JenapayAdapter implements PaymentAdapterInterface
{
    /**
     * Transform a Checkout session response into a PaymentResult.
     * Expects the context array to include our generated order fields.
     */
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        /** @var array<string, mixed> $response */
        return new PaymentResult(
            success: true,
            transactionId: (string) $response['order_number'],
            status: PaymentStatus::Pending,
            amount: (int) round((float) ($response['order_amount'] ?? 0)),
            currency: (string) ($response['order_currency'] ?? config('transactions.currency.default', 'USD')),
            metadata: array_filter([
                'redirect_url' => $response['redirect_url'] ?? null,
                'jenapay_order_number' => $response['order_number'] ?? null,
            ], fn ($value) => $value !== null),
            processorResponse: $response,
        );
    }

    /**
     * Transform a payment status response into a PaymentResult.
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        $status = $this->mapStatus($payload['status'] ?? null);

        return new PaymentResult(
            success: $status === PaymentStatus::Succeeded,
            transactionId: $transactionId,
            status: $status,
            amount: (int) round((float) ($payload['order']['amount'] ?? $payload['order_amount'] ?? 0)),
            currency: (string) ($payload['order']['currency'] ?? $payload['order_currency'] ?? config('transactions.currency.default', 'USD')),
            message: $payload['reason'] ?? null,
            metadata: $this->metadataFromPayload($payload),
            processorResponse: $payload,
            // The status response carries the masked card just as a callback
            // does, so a synced transaction records the same payment method
            // detail a webhook-driven one would.
            paymentMethodSnapshot: $this->extractPaymentMethod($payload),
        );
    }

    /**
     * Transform a Jenapay callback into a TransactionWebhookUpdate.
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        $status = $this->mapStatus($payload['status'] ?? null);

        return new TransactionWebhookUpdate(
            status: $status,
            processorResponse: $payload,
            paymentMethodSnapshot: $this->extractPaymentMethod($payload),
            metadata: $this->metadataFromPayload($payload),
            errorMessage: in_array($status, [PaymentStatus::Failed, PaymentStatus::Canceled], true)
                ? ($payload['reason'] ?? $payload['decline_reason'] ?? null)
                : null,
            amount: isset($payload['order_amount']) ? (int) round((float) $payload['order_amount']) : null,
            currency: $payload['order_currency'] ?? null,
        );
    }

    /**
     * Map a Jenapay (Akurateco) status to a cashier-core PaymentStatus.
     *
     * Statuses: settled, declined, refund, void, prepare/pending/redirect (in-flight).
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match (strtolower((string) $providerStatus)) {
            'settled', 'success' => PaymentStatus::Succeeded,
            // The live API answers `decline` (singular) on both the status
            // endpoint and callbacks; `declined` is the spelling in the
            // written spec. Accept both — mapping either to Pending leaves a
            // dead payment sitting in the pending state forever.
            'decline', 'declined', 'fail', 'failed' => PaymentStatus::Failed,
            'refund', 'refunded', 'void' => PaymentStatus::Canceled,
            'pending', 'prepare' => PaymentStatus::Pending,
            '3ds', 'redirect' => PaymentStatus::RequiresAction,
            default => PaymentStatus::Pending,
        };
    }

    public function getProviderName(): string
    {
        return 'jenapay';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function metadataFromPayload(array $payload): array
    {
        return array_filter([
            'jenapay_payment_id' => $payload['id'] ?? $payload['payment_id'] ?? null,
            // Callbacks send `order_number` flat; the status endpoint nests it
            // under `order.number`.
            'jenapay_order_number' => $payload['order_number'] ?? $payload['order']['number'] ?? null,
            'jenapay_type' => $payload['type'] ?? null,
            'jenapay_trans_status' => $payload['trans_status'] ?? null,
            'jenapay_reason' => $payload['reason'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPaymentMethod(array $payload): ?PaymentMethodSnapshot
    {
        $maskedCard = $payload['card'] ?? $payload['card_number'] ?? null;

        if (! $maskedCard) {
            return null;
        }

        $lastFour = substr(preg_replace('/\D/', '', (string) $maskedCard) ?? '', -4);

        return new PaymentMethodSnapshot(
            type: PaymentMethodType::CreditCard,
            brand: PaymentMethodBrand::Other,
            lastFour: $lastFour ?: null,
            displayName: $lastFour ? "Card •••• {$lastFour}" : 'Card',
        );
    }
}
