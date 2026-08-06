<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Testing;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use BadMethodCallException;

/**
 * The provider every faked connection resolves to.
 *
 * Charges, refunds and retrievals record into the {@see CashierFake} bound by
 * `Cashier::fake()`; nothing leaves the process. Webhook parsing is not faked
 * — use {@see WebhookSimulator} against real driver connections for that.
 */
final class FakeProvider implements PaymentProcessorInterface
{
    public function __construct(
        private readonly array $config,
        private readonly CashierFake $fake,
    ) {}

    public function charge(array $data): PaymentResult
    {
        return $this->fake->recordCharge($this->connection(), $this->driver(), $data);
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        return $this->fake->recordRefund($this->connection(), $transactionId, $amount);
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        return $this->fake->recordRetrieve($this->connection(), $transactionId);
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new BadMethodCallException(
            'Faked connections do not parse webhooks — post a signed delivery to a real driver connection with WebhookSimulator instead.'
        );
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return true;
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new BadMethodCallException('Not supported by the fake provider');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new BadMethodCallException('Not supported by the fake provider');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new BadMethodCallException('Not supported by the fake provider');
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new BadMethodCallException('Not supported by the fake provider');
    }

    public function validatePaymentData(array $data): array
    {
        return $data;
    }

    public function supports(string $feature): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->driver();
    }

    private function connection(): string
    {
        return (string) ($this->config['__connection'] ?? $this->config['driver'] ?? 'fake');
    }

    private function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'fake');
    }
}
