<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;

interface PaymentAdapterInterface
{
    /**
     * Transform provider response to a PaymentResult.
     */
    public function fromProviderResponse(mixed $response): PaymentResult;

    /**
     * Transform provider raw payload (from retrieve/resync) to a PaymentResult.
     */
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult;

    /**
     * Transform webhook payload to a TransactionWebhookUpdate.
     */
    public function fromWebhook(array $payload): TransactionWebhookUpdate;

    /**
     * Map a provider-specific status to a PaymentStatus.
     */
    public function mapStatus(mixed $providerStatus): PaymentStatus;

    /**
     * Get the provider name this adapter handles.
     */
    public function getProviderName(): string;
}
