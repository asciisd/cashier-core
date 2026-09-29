<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP refused or failed the payout. The withdrawal stays Processing for an
 * admin to resend or cancel; $reason is internal PSP text — not for customers.
 */
class WithdrawalPayoutFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $reason,
    ) {}
}
