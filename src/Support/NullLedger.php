<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\LedgerTicket;
use Illuminate\Support\Facades\Log;

/**
 * Default FundsLedger binding for hosts that have no internal balance system.
 *
 * Never reached in normal operation for such hosts — the pipeline skips the
 * credit entirely when a transaction names no ledger account. Being called
 * therefore means an account WAS specified while no real ledger is bound:
 * every call refuses (null) and logs, so a misconfiguration surfaces instead
 * of pretending funds moved.
 */
class NullLedger implements FundsLedger
{
    public function credit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->refuse('credit', $account, $amount, $currency);
    }

    public function debit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->refuse('debit', $account, $amount, $currency);
    }

    public function correct(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket
    {
        return $this->refuse('correct', $account, $amount, $currency);
    }

    public function refresh(int|string $account): void
    {
        // Nothing to refresh.
    }

    private function refuse(string $operation, int|string $account, float $amount, string $currency): null
    {
        Log::error('cashier-core: ledger operation requested but no FundsLedger is bound — bind your ledger implementation', [
            'operation' => $operation,
            'account' => $account,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return null;
    }
}
