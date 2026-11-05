<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A provider reported a second payment against an existing charge, and the
 * engine opened a transaction of its own for it.
 *
 * The new transaction then settles through the normal webhook pipeline, so
 * the deposit events follow. This event exists so the host can tell an admin
 * that money arrived without a deposit having been opened for it.
 */
class RepeatPaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly Transaction $original,
    ) {}
}
