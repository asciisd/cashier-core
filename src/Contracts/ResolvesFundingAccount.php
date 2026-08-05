<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

/**
 * Resolves which of the customer's accounts a deposit funds.
 *
 * The charge payload names an account however the host lets it — an explicit
 * ledger login, an opaque account reference, nothing at all — and only the
 * host can turn that into the two numbers the engine needs: the ledger account
 * to credit and the local row to relate the transaction to. Bind your own
 * implementation; the default passthrough honors an explicit
 * `trading_account_login` and resolves nothing else.
 */
interface ResolvesFundingAccount
{
    /**
     * The ledger account (e.g. MT5 login) this deposit funds, or null when the
     * deposit targets the customer's wallet rather than a trading account.
     * Written to charge metadata as `trading_account_login`, which is what the
     * post-success credit path reads.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function ledgerAccountFor(CustomerContract $customer, array $paymentData): ?int;

    /**
     * The host-side funding account row id, persisted to
     * `transactions.trading_account_id`. Null when unresolvable — the column
     * is nullable by design.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function fundingAccountIdFor(CustomerContract $customer, array $paymentData): ?int;
}
