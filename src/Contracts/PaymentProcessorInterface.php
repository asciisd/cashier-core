<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;

interface PaymentProcessorInterface
{
    /**
     * Process a payment charge
     */
    public function charge(array $data): PaymentResult;

    /**
     * Refund a payment
     */
    public function refund(string $transactionId, ?float $amount = null): RefundResult;

    /**
     * Capture a previously authorized payment
     */
    public function capture(string $transactionId, ?float $amount = null): PaymentResult;

    /**
     * Authorize a payment without capturing
     */
    public function authorize(array $data): PaymentResult;

    /**
     * Void an authorized payment
     */
    public function void(string $transactionId): PaymentResult;

    /**
     * Retrieve transaction details from the payment provider
     */
    public function retrieve(string $transactionId): ?PaymentResult;

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): string;

    /**
     * Validate payment data
     */
    public function validatePaymentData(array $data): array;

    /**
     * Parse and validate incoming webhook data
     */
    public function parseWebhook(array $payload): TransactionWebhookUpdate;

    /**
     * Verify webhook signature authenticity
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool;

    /**
     * Get processor name
     */
    public function getName(): string;

    /**
     * Check if processor supports a specific feature
     */
    public function supports(string $feature): bool;
}
