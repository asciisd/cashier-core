<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Support;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;

/**
 * The default {@see ResolvesFundingAccount} binding.
 *
 * Trusts an explicit `trading_account_login` in the charge payload as given —
 * the host's form request is expected to have validated ownership — and
 * resolves nothing else. Reference-based lookups need the host's account
 * model, so a host that deposits by reference binds its own resolver.
 */
class PassthroughFundingAccountResolver implements ResolvesFundingAccount
{
    public function ledgerAccountFor(CustomerContract $customer, array $paymentData): ?int
    {
        $login = $paymentData['trading_account_login'] ?? null;

        return empty($login) ? null : (int) $login;
    }

    public function fundingAccountIdFor(CustomerContract $customer, array $paymentData): ?int
    {
        return null;
    }
}
