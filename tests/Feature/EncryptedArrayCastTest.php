<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

function castTransaction(array $attributes = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'aps',
        'provider_transaction_id' => 'tx-'.fake()->unique()->uuid(),
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ], $attributes));
}

function rawColumn(Transaction $transaction, string $column): ?string
{
    return DB::table('transactions')->where('id', $transaction->id)->value($column);
}

function encryption(bool $on): void
{
    config()->set('cashier-core.security.encrypt_provider_payload', $on);
    config()->set('cashier-core.security.encrypt_withdrawal_details', $on);
}

it('stores ciphertext when encryption is on', function () {
    encryption(true);

    $transaction = castTransaction(['provider_payload' => ['order_id' => 'A1']]);

    $raw = rawColumn($transaction, 'provider_payload');

    expect(json_decode($raw, true))->toBeNull()
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe(['order_id' => 'A1'])
        ->and($transaction->fresh()->provider_payload)->toBe(['order_id' => 'A1']);
});

it('stores cleartext json when encryption is off', function () {
    encryption(false);

    $transaction = castTransaction(['provider_payload' => ['order_id' => 'A1']]);

    expect(json_decode(rawColumn($transaction, 'provider_payload'), true))->toBe(['order_id' => 'A1'])
        ->and($transaction->fresh()->provider_payload)->toBe(['order_id' => 'A1']);
});

/*
 * The reason this cast exists: a host flipping encryption on mid-life must keep
 * serving its back catalogue while `cashier:encrypt-historical` works through it.
 */
it('reads legacy cleartext rows after encryption is switched on', function () {
    encryption(false);

    $transaction = castTransaction([
        'provider_payload' => ['order_id' => 'A1'],
        'withdrawal_details' => ['iban' => 'KW00'],
    ]);

    encryption(true);

    $fresh = $transaction->fresh();

    expect($fresh->provider_payload)->toBe(['order_id' => 'A1'])
        ->and($fresh->withdrawal_details)->toBe(['iban' => 'KW00']);
});

it('still reads ciphertext after encryption is switched back off', function () {
    encryption(true);

    $transaction = castTransaction(['provider_payload' => ['order_id' => 'A1']]);

    encryption(false);

    expect($transaction->fresh()->provider_payload)->toBe(['order_id' => 'A1']);
});

it('surfaces a decrypt failure instead of silently returning null', function () {
    encryption(true);

    $transaction = castTransaction(['provider_payload' => ['order_id' => 'A1']]);

    DB::table('transactions')->where('id', $transaction->id)->update([
        'provider_payload' => 'not-json-and-not-a-valid-payload',
    ]);

    expect(fn () => $transaction->fresh()->provider_payload)->toThrow(DecryptException::class);
});

it('governs each column with its own flag', function () {
    config()->set('cashier-core.security.encrypt_provider_payload', true);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    $transaction = castTransaction([
        'provider_payload' => ['order_id' => 'A1'],
        'withdrawal_details' => ['iban' => 'KW00'],
    ]);

    expect(json_decode(rawColumn($transaction, 'provider_payload'), true))->toBeNull()
        ->and(json_decode(rawColumn($transaction, 'withdrawal_details'), true))->toBe(['iban' => 'KW00']);
});

it('round-trips null', function () {
    encryption(true);

    $transaction = castTransaction(['provider_payload' => null]);

    expect(rawColumn($transaction, 'provider_payload'))->toBeNull()
        ->and($transaction->fresh()->provider_payload)->toBeNull();
});
