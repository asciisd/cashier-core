<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\LedgerTicket;

/**
 * The host application's money ledger — the system that ultimately holds the
 * customer's balance (a trading platform, a wallet service, an ERP).
 *
 * The package moves transaction statuses; the ledger moves the actual value.
 * Implementations MUST return null on definite failure rather than throwing —
 * the pipeline treats null as "refused, safe to retry" and an exception as
 * "outcome unknown, hold before retrying" (a timeout may have landed).
 */
interface FundsLedger
{
    /**
     * Credit the customer's account (deposit settled).
     */
    public function credit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket;

    /**
     * Debit the customer's account (withdrawal approved).
     */
    public function debit(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket;

    /**
     * Return previously debited funds (withdrawal rejected or cancelled).
     */
    public function correct(int|string $account, float $amount, string $currency, string $comment = ''): ?LedgerTicket;

    /**
     * Refresh any locally cached balance for the account. Best-effort.
     */
    public function refresh(int|string $account): void;
}
