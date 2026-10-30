<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * A host subclass with an owner scope like the real ones: outside a signed-in
 * session it hides every row. The engine has to reach the row anyway wherever
 * a signature, not a session, is what authorises the request.
 */
class OwnerScopedTransaction extends Transaction
{
    protected $table = 'transactions';

    protected static function booted(): void
    {
        static::addGlobalScope('owner', fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * @return list<string>
     */
    public static function cashierBypassedScopes(): array
    {
        return ['owner'];
    }
}
