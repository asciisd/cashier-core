<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\RefundStatus;

/**
 * Minimal provider for registry tests: records nothing, succeeds always, and
 * exposes the connection config it was constructed with.
 */
class FakeConnectionProvider extends AbstractPaymentProcessor
{
    protected array $supportedFeatures = ['charge', 'refund'];

    public function charge(array $data): PaymentResult
    {
        return $this->createSuccessResult(
            transactionId: 'fake-tx-1',
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'USD'),
        );
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        return new RefundResult(
            success: true,
            refundId: 'fake-refund-1',
            originalTransactionId: $transactionId,
            status: RefundStatus::Succeeded,
            amount: (int) $amount,
            currency: 'USD',
        );
    }

    public function getName(): string
    {
        return 'fake';
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionConfig(): array
    {
        return $this->config;
    }
}
