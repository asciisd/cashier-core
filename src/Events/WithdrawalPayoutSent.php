<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP accepted the payout; the withdrawal stays Processing until it lands.
 */
class WithdrawalPayoutSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly Actor $actor,
    ) {}
}
