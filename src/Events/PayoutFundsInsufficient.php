<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP balance cannot cover this payout — ops should top it up. Nothing
 * was sent; amounts are decimal strings in the wallet currency.
 */
class PayoutFundsInsufficient
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $balance,
        public readonly string $required,
        public readonly string $walletCurrency,
    ) {}
}
