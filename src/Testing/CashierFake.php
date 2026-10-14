<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Testing;

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * The recorder behind {@see \Asciisd\CashierCore\Cashier::fake()}.
 *
 * Every faked connection resolves to a {@see FakeProvider} that records here
 * instead of reaching a PSP. Queue per-connection results with
 * `whenCharging()` / `whenRetrieving()`; unqueued charges succeed as a
 * hosted-page Pending result with a `fake-tx-*` id.
 */
final class CashierFake
{
    /** @var list<array{connection: string, driver: string, data: array<string, mixed>}> */
    private array $charges = [];

    /** @var list<array{connection: string, transaction_id: string, amount: int|null}> */
    private array $refunds = [];

    /** @var array<string, list<PaymentResult|Closure>> */
    private array $chargeResults = [];

    /** @var array<string, list<PaymentResult|null>> */
    private array $retrieveResults = [];

    private int $sequence = 0;

    /**
     * Queue the result the next charge on a connection returns. A Closure
     * receives `($data, $connection)` and may throw to simulate PSP failures.
     */
    public function whenCharging(string $connection, PaymentResult|Closure $result): self
    {
        $this->chargeResults[$connection][] = $result;

        return $this;
    }

    /**
     * Queue what the next `retrieve()` on a connection reports. Null means
     * "the provider does not know this transaction".
     */
    public function whenRetrieving(string $connection, ?PaymentResult $result): self
    {
        $this->retrieveResults[$connection][] = $result;

        return $this;
    }

    /** @internal called by FakeProvider */
    public function recordCharge(string $connection, string $driver, array $data): PaymentResult
    {
        $this->charges[] = compact('connection', 'driver', 'data');

        $queued = ($this->chargeResults[$connection] ?? []) === []
            ? null
            : array_shift($this->chargeResults[$connection]);

        if ($queued instanceof Closure) {
            $queued = $queued($data, $connection);
        }

        return $queued ?? $this->defaultChargeResult($data);
    }

    /** @internal called by FakeProvider */
    public function recordRefund(string $connection, string $transactionId, ?float $amount): RefundResult
    {
        $this->refunds[] = ['connection' => $connection, 'transaction_id' => $transactionId, 'amount' => $amount];

        return new RefundResult(
            success: true,
            refundId: 'fake-refund-'.++$this->sequence,
            originalTransactionId: $transactionId,
            status: RefundStatus::Succeeded,
            amount: $amount ?? 0,
            currency: 'USD',
        );
    }

    /** @internal called by FakeProvider */
    public function recordRetrieve(string $connection, string $transactionId): ?PaymentResult
    {
        if (array_key_exists($connection, $this->retrieveResults) && $this->retrieveResults[$connection] !== []) {
            return array_shift($this->retrieveResults[$connection]);
        }

        return null;
    }

    /**
     * @return list<array{connection: string, driver: string, data: array<string, mixed>}>
     */
    public function charges(): array
    {
        return $this->charges;
    }

    /**
     * @param  (callable(array<string, mixed> $data, string $connection): bool)|null  $matcher
     */
    public function assertCharged(?callable $matcher = null): void
    {
        $matches = array_filter(
            $this->charges,
            fn (array $charge) => $matcher === null || $matcher($charge['data'], $charge['connection']),
        );

        Assert::assertNotEmpty($matches, 'Expected a charge matching the given constraint.');
    }

    /**
     * @param  (callable(array<string, mixed> $data): bool)|null  $matcher
     */
    public function assertChargedOn(string $connection, ?callable $matcher = null): void
    {
        $matches = array_filter(
            $this->charges,
            fn (array $charge) => $charge['connection'] === $connection
                && ($matcher === null || $matcher($charge['data'])),
        );

        Assert::assertNotEmpty($matches, "Expected a charge on connection [{$connection}].");
    }

    public function assertNothingCharged(): void
    {
        Assert::assertSame([], $this->charges, 'Expected no charges.');
    }

    public function assertChargedCount(int $count): void
    {
        Assert::assertCount($count, $this->charges);
    }

    /**
     * @param  (callable(array{connection: string, transaction_id: string, amount: int|null}): bool)|null  $matcher
     */
    public function assertRefunded(?callable $matcher = null): void
    {
        $matches = array_filter(
            $this->refunds,
            fn (array $refund) => $matcher === null || $matcher($refund),
        );

        Assert::assertNotEmpty($matches, 'Expected a refund matching the given constraint.');
    }

    private function defaultChargeResult(array $data): PaymentResult
    {
        $id = 'fake-tx-'.++$this->sequence;

        return new PaymentResult(
            success: true,
            transactionId: $id,
            status: PaymentStatus::Pending,
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'USD'),
            metadata: ['redirect_url' => "https://cashier.fake/pay/{$id}"],
            processorResponse: ['fake' => true],
        );
    }
}
