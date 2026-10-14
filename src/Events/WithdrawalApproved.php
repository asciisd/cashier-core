<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin approved a withdrawal; the ledger debit succeeded and the transaction is Processing.
 */
class WithdrawalApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?Actor $actor = null,
    ) {}
}
