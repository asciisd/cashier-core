<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payout send ended without a definitive answer: the payout may or may not
 * exist at the PSP. Nothing may be resent until it is resolved — by a lookup,
 * or for providers that cannot look up by order id, by an admin.
 */
class WithdrawalPayoutOutcomeUnknown
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $orderId,
        public readonly string $error,
    ) {}
}
