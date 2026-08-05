<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Fees;

/**
 * What a client-controlled payment actually settled at, once the PSP reports
 * how much arrived against how much was invoiced.
 */
final readonly class SettledAmount
{
    public function __construct(
        /** The deposit amount to credit, reconciled to what was actually paid. */
        public float $amount,
        /** Paid over invoiced. 1.0 is exact, 0.1 is a tenth, 1.5 is an overpay. */
        public float $ratio,
        /** Whether the shortfall/excess was small enough to treat as paid in full. */
        public bool $withinTolerance,
    ) {}

    public function isUnderpaid(): bool
    {
        return ! $this->withinTolerance && $this->ratio < 1.0;
    }

    public function isOverpaid(): bool
    {
        return ! $this->withinTolerance && $this->ratio > 1.0;
    }

    /**
     * Whether this settlement moved the deposit off its invoiced amount, and so
     * needs `transactions.amount` reconciled before the MT5 credit runs.
     */
    public function requiresReconciliation(): bool
    {
        return ! $this->withinTolerance;
    }
}
