<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The ledger refused the debit during approval; the withdrawal remains Pending.
 */
class WithdrawalDebitFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?Actor $actor = null,
    ) {}
}
