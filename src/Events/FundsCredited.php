<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\DataObjects\LedgerTicket;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The ledger credit for a settled deposit landed.
 */
class FundsCredited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly LedgerTicket $ticket,
    ) {}
}
