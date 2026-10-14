<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Models\WebhookEvent;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
});

it('passes cashier:check on a clean configuration', function () {
    config()->set('cashier-core.connections.manual', ['driver' => 'manual']);
    config()->set('cashier-core.default_connection', 'manual');

    $this->artisan('cashier:check')
        ->expectsOutputToContain('cashier:check passed.')
        ->assertSuccessful();
});

it('fails cashier:check when a connection resolves to no provider class', function () {
    config()->set('cashier-core.connections.mystery', ['driver' => 'no-such-driver']);
    config()->set('cashier-core.default_connection', 'mystery');

    $this->artisan('cashier:check')->assertFailed();
});

it('fails cashier:check when the default connection is not configured', function () {
    config()->set('cashier-core.connections.manual', ['driver' => 'manual']);
    config()->set('cashier-core.default_connection', 'missing');

    $this->artisan('cashier:check')->assertFailed();
});

it('only warns about disabled signature verification outside production', function () {
    config()->set('cashier-core.connections.manual', ['driver' => 'manual']);
    config()->set('cashier-core.default_connection', 'manual');
    config()->set('cashier-core.webhooks.verify_signature', false);

    $this->artisan('cashier:check')->assertSuccessful();
});

function purgeableTransaction(array $attributes = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'aps',
        'provider_transaction_id' => 'tx-'.fake()->unique()->uuid(),
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
        'provider_payload' => ['ok' => true],
    ], $attributes));
}

it('purges expired provider payloads and webhook events, keeping fresh ones', function () {
    $expired = purgeableTransaction();
    $expired->forceFill(['created_at' => now()->subDays(200)])->save();

    $fresh = purgeableTransaction();

    WebhookEvent::query()->create([
        'driver' => 'aps', 'connection' => 'aps', 'digest' => 'old', 'received_at' => now()->subDays(40),
    ]);
    WebhookEvent::query()->create([
        'driver' => 'aps', 'connection' => 'aps', 'digest' => 'new', 'received_at' => now()->subDay(),
    ]);

    $this->artisan('cashier:purge')
        ->expectsOutputToContain('Cleared provider_payload on 1 transaction(s); deleted 1 webhook event(s).')
        ->assertSuccessful();

    expect($expired->fresh()->provider_payload)->toBeNull()
        ->and($fresh->fresh()->provider_payload)->toBe(['ok' => true])
        ->and(WebhookEvent::query()->pluck('digest')->all())->toBe(['new']);
});

it('reports without purging on --dry-run', function () {
    $expired = purgeableTransaction();
    $expired->forceFill(['created_at' => now()->subDays(200)])->save();

    $this->artisan('cashier:purge', ['--dry-run' => true])
        ->expectsOutputToContain('Would clear provider_payload on 1 transaction(s)')
        ->assertSuccessful();

    expect($expired->fresh()->provider_payload)->toBe(['ok' => true]);
});

it('publishes the configuration via cashier:install', function () {
    $path = config_path('cashier-core.php');

    if (file_exists($path)) {
        unlink($path);
    }

    try {
        $this->artisan('cashier:install')->assertSuccessful();

        expect(file_exists($path))->toBeTrue();
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
