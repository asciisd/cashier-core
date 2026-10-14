<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A deposit reached Failed.
 */
class DepositFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
    ) {}
}
