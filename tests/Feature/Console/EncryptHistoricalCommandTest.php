<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Tests\Fixtures\HostTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Writes a row the way the host wrote it before the engine existed: cleartext
 * JSON, because both flags are off at insert time.
 */
function legacyTransaction(array $attributes = []): Transaction
{
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    $transaction = Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'aps',
        'provider_transaction_id' => 'tx-'.fake()->unique()->uuid(),
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
        'provider_payload' => ['order_id' => 'A1', 'email' => 'client@example.com'],
    ], $attributes));

    config()->set('cashier-core.security.encrypt_provider_payload', true);
    config()->set('cashier-core.security.encrypt_withdrawal_details', true);

    return $transaction;
}

function rawValue(Transaction $transaction, string $column): ?string
{
    return DB::table('transactions')->where('id', $transaction->id)->value($column);
}

it('encrypts and sanitizes legacy cleartext payloads', function () {
    $transaction = legacyTransaction();

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    $raw = rawValue($transaction, 'provider_payload');

    expect(json_decode($raw, true))->toBeNull()
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe([
            'order_id' => 'A1',
            'email' => '[redacted]',
        ])
        ->and($transaction->fresh()->provider_payload)->toBe([
            'order_id' => 'A1',
            'email' => '[redacted]',
        ]);
});

it('leaves withdrawal details unsanitized — the host needs them to pay the beneficiary', function () {
    $transaction = legacyTransaction([
        'withdrawal_details' => ['iban' => 'KW81CBKU0000000000001234560101', 'beneficiary_name' => 'Jane Doe'],
    ]);

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    expect($transaction->fresh()->withdrawal_details)->toBe([
        'iban' => 'KW81CBKU0000000000001234560101',
        'beneficiary_name' => 'Jane Doe',
    ]);
});

it('keeps the payload verbatim with --skip-sanitize', function () {
    $transaction = legacyTransaction();

    $this->artisan('cashier:encrypt-historical', ['--skip-sanitize' => true])->assertSuccessful();

    expect($transaction->fresh()->provider_payload)->toBe([
        'order_id' => 'A1',
        'email' => 'client@example.com',
    ]);
});

it('is resumable: a second run reports rows as already encrypted and rewrites nothing', function () {
    $transaction = legacyTransaction();

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    $afterFirstRun = rawValue($transaction, 'provider_payload');

    $this->artisan('cashier:encrypt-historical')
        ->expectsOutputToContain('1 already encrypted')
        ->assertSuccessful();

    expect(rawValue($transaction, 'provider_payload'))->toBe($afterFirstRun);
});

it('writes nothing on a dry run', function () {
    $transaction = legacyTransaction();

    $this->artisan('cashier:encrypt-historical', ['--dry-run' => true])
        ->expectsOutputToContain('would encrypt 1 row(s)')
        ->assertSuccessful();

    expect(json_decode(rawValue($transaction, 'provider_payload'), true))->toBe([
        'order_id' => 'A1',
        'email' => 'client@example.com',
    ]);
});

it('skips a column whose encryption flag is still off', function () {
    $transaction = legacyTransaction();

    config()->set('cashier-core.security.encrypt_provider_payload', false);

    $this->artisan('cashier:encrypt-historical')
        ->expectsOutputToContain('skipping provider_payload')
        ->assertSuccessful();

    expect(json_decode(rawValue($transaction, 'provider_payload'), true))->not->toBeNull();
});

it('fails when both flags are off', function () {
    legacyTransaction();

    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    $this->artisan('cashier:encrypt-historical')->assertFailed();
});

it('rejects an unknown --column', function () {
    $this->artisan('cashier:encrypt-historical', ['--column' => ['metadata']])->assertFailed();
});

it('processes only the requested column', function () {
    $transaction = legacyTransaction([
        'withdrawal_details' => ['iban' => 'KW81'],
    ]);

    $this->artisan('cashier:encrypt-historical', ['--column' => ['withdrawal_details']])->assertSuccessful();

    expect(json_decode(rawValue($transaction, 'withdrawal_details'), true))->toBeNull()
        ->and(json_decode(rawValue($transaction, 'provider_payload'), true))->not->toBeNull();
});

it('does not move updated_at — this is a storage format change, not a business event', function () {
    $transaction = legacyTransaction();

    DB::table('transactions')->where('id', $transaction->id)->update([
        'updated_at' => '2020-01-01 00:00:00',
    ]);

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    expect(rawValue($transaction, 'updated_at'))->toStartWith('2020-01-01 00:00:00');
});

it('reports a damaged value and leaves it untouched instead of aborting the run', function () {
    $damaged = legacyTransaction();
    $healthy = legacyTransaction();

    DB::table('transactions')->where('id', $damaged->id)->update([
        'provider_payload' => 'neither-json-nor-ciphertext',
    ]);

    $this->artisan('cashier:encrypt-historical')
        ->expectsOutputToContain('1 failed')
        ->assertFailed();

    expect(rawValue($damaged, 'provider_payload'))->toBe('neither-json-nor-ciphertext')
        ->and($healthy->fresh()->provider_payload)->toBe(['order_id' => 'A1', 'email' => '[redacted]']);
});

/*
 * Found against real data: the sanitizer needs the driver string, and reading
 * it through the model handed back the host's display enum instead.
 */
it('sanitizes rows whose host model casts provider to an enum', function () {
    $transaction = legacyTransaction();

    config()->set('cashier-core.models.transaction', HostTransaction::class);

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    expect(HostTransaction::query()->find($transaction->id)->provider_payload)->toBe([
        'order_id' => 'A1',
        'email' => '[redacted]',
    ]);
});

it('covers soft-deleted rows — a deleted transaction still holds the payload', function () {
    $transaction = legacyTransaction();
    $transaction->delete();

    $this->artisan('cashier:encrypt-historical')->assertSuccessful();

    expect(json_decode(rawValue($transaction, 'provider_payload'), true))->toBeNull();
});
