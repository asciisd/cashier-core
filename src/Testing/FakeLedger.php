<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Testing;

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\LedgerTicket;
use PHPUnit\Framework\Assert;

/**
 * In-memory FundsLedger for tests: records every movement, succeeds by
 * default, and can be scripted to refuse or throw.
 */
class FakeLedger implements FundsLedger
{
    /** @var list<array{operation: string, account: int|string, amount: float, currency: string, comment: string}> */
    public array $movements = [];

    /** @var list<int|string> */
    public array $refreshed = [];

    private bool $refuse = false;

    private ?\Throwable $throw = null;

    private int $nextTicket = 90001;

    public function refuseAll(): self
    {
        $this->refuse = true;

        return $this;
    }

    public function throwOnNextCall(?\Throwable $e = null): self
    {
        $this->throw = $e ?? new \RuntimeException('ledger unreachable');

        return $this;
    }

    public function credit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->record('credit', $account, $amount, $currency, $comment);
    }

    public function debit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->record('debit', $account, $amount, $currency, $comment);
    }

    public function correct(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->record('correct', $account, $amount, $currency, $comment);
    }

    public function refresh(int|string $account): void
    {
        $this->refreshed[] = $account;
    }

    public function assertMoved(string $operation, ?callable $matcher = null): void
    {
        $matches = array_filter(
            $this->movements,
            fn (array $m) => $m['operation'] === $operation && ($matcher === null || $matcher($m)),
        );

        Assert::assertNotEmpty($matches, "Expected a ledger [{$operation}] matching the given constraint.");
    }

    public function assertNothingMoved(): void
    {
        Assert::assertSame([], $this->movements, 'Expected no ledger movements.');
    }

    public function assertMovedCount(int $count): void
    {
        Assert::assertCount($count, $this->movements);
    }

    private function record(string $operation, int|string $account, float $amount, string $currency, string $comment): ?LedgerTicket
    {
        if ($this->throw) {
            $e = $this->throw;
            $this->throw = null;

            throw $e;
        }

        if ($this->refuse) {
            return null;
        }

        $this->movements[] = compact('operation', 'account', 'amount', 'currency', 'comment');

        return new LedgerTicket((string) $this->nextTicket++, $comment);
    }
}
