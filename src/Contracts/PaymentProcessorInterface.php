<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;

interface PaymentProcessorInterface
{
    /**
     * Process a payment charge
     */
    public function charge(array $data): PaymentResult;

    /**
     * Refund a payment
     */
    public function refund(string $transactionId, ?int $amount = null): RefundResult;

    /**
     * Capture a previously authorized payment
     */
    public function capture(string $transactionId, ?int $amount = null): PaymentResult;

    /**
     * Authorize a payment without capturing
     */
    public function authorize(array $data): PaymentResult;

    /**
     * Void an authorized payment
     */
    public function void(string $transactionId): PaymentResult;

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): string;

    /**
     * Validate payment data
     */
    public function validatePaymentData(array $data): array;

    /**
     * Get processor name
     */
    public function getName(): string;

    /**
     * Check if processor supports a specific feature
     */
    public function supports(string $feature): bool;
}
