<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Every status transition the pipeline commits, with its source
 * ('webhook', 'sync', 'admin', 'customer').
 */
class TransactionStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly PaymentStatus $from,
        public readonly PaymentStatus $to,
        public readonly string $source,
    ) {}
}
