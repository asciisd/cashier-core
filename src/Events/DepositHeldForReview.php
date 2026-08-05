<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A webhook reported success but its amount or currency deviated from the
 * invoice beyond tolerance. The transaction is OnHold: no ledger credit, no
 * invoice, until a human resolves it.
 */
class DepositHeldForReview
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?float $reportedAmount,
        public readonly ?string $reportedCurrency,
        public readonly string $reason,
    ) {}
}
