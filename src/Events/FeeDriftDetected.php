<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The settlement a PSP reported diverges from what the configured fees
 * predicted — the contract and the config have drifted apart. Reporting
 * only; never blocks a credit.
 */
class FeeDriftDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly float $expected,
        public readonly float $actual,
    ) {}
}
