<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A deposit reached Succeeded. Fired after the status committed and before/independent of the ledger credit — listen here for invoices, CRM, notifications.
 */
class DepositSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
    ) {}
}
