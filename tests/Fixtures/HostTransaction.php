<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Models\Transaction;

/**
 * A host subclass shaped like the real ones: `provider` is a plain string to
 * the engine, but hosts cast it to their own display enum. Anything the engine
 * does with that column has to survive the cast.
 */
class HostTransaction extends Transaction
{
    protected $table = 'transactions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'provider' => HostProvider::class,
        ];
    }
}
