<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The ledger refused (or errored on) the credit for a settled deposit. The
 * transaction stays Succeeded with no ticket — recover via sync or retry.
 */
class FundsCreditFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $reason,
    ) {}
}
