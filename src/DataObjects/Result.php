<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Models\Transaction;

/**
 * Outcome of a workflow action, shaped for thin admin-UI wrappers: ok maps to
 * a success message, not-ok to a danger message, with the (possibly updated)
 * transaction along for display.
 */
readonly class Result
{
    private function __construct(
        public bool $ok,
        public string $message,
        public ?Transaction $transaction = null,
    ) {}

    public static function ok(string $message, ?Transaction $transaction = null): self
    {
        return new self(true, $message, $transaction);
    }

    public static function failed(string $message, ?Transaction $transaction = null): self
    {
        return new self(false, $message, $transaction);
    }
}
