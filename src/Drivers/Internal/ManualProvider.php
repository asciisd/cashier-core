<?php

namespace Asciisd\CashierCore\Drivers\Internal;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

class ManualProvider implements PaymentProcessorInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Process a payment charge (manual transactions don't charge).
     */
    public function charge(array $data): PaymentResult
    {
        throw new PaymentProcessingException('Manual provider does not support automatic charges');
    }

    /**
     * Process a refund.
     */
    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        return new RefundResult(
            success: true,
            refundId: 'manual-'.uniqid(),
            originalTransactionId: $transactionId,
            status: RefundStatus::Succeeded,
            amount: $amount ?? 0,
            currency: 'USD',
            message: 'Manual refund initiated',
        );
    }

    /**
     * Retrieve transaction details from provider.
     */
    public function retrieve(string $transactionId): ?PaymentResult
    {
        return null;
    }

    /**
     * Parse and validate webhook data.
     */
    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new PaymentProcessingException('Manual provider does not support webhooks');
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    /**
     * Check if the provider supports a specific feature.
     */
    public function supports(string $feature): bool
    {
        return match ($feature) {
            'refund' => true,
            default => false,
        };
    }

    public function capture(string $transactionId, ?int $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function validatePaymentData(array $data): array
    {
        return $data;
    }

    public function getName(): string
    {
        return 'manual';
    }
}
