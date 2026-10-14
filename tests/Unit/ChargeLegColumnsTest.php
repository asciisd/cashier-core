<?php

declare(strict_types=1);

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Schema;

it('stores the charge leg without truncating a three-decimal currency', function () {
    expect(Schema::hasColumn('transactions', 'charge_currency'))->toBeTrue()
        ->and(Schema::hasColumn('transactions', 'charge_amount'))->toBeTrue();

    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'myfatoorah',
        'connection' => 'myfatoorah',
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 100.00,
        'currency' => 'USD',
        'charge_currency' => 'KWD',
        // Four meaningful decimals, and deliberately so: this is the only thing
        // separating decimal(20,4) from the decimal(16,2) the test exists to
        // rule out. A value like 31.1300 round-trips identically through both,
        // so it would assert nothing.
        'charge_amount' => 31.1234,
        'conversion_rate' => 0.30670000,
    ]);

    // KWD carries three minor units. A decimal(16,2) column would store 31.12
    // and put the webhook assertion permanently a millifil out.
    expect((float) $transaction->fresh()->charge_amount)->toBe(31.1234)
        ->and($transaction->fresh()->charge_currency)->toBe('KWD');
});
