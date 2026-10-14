<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cashier::fakeConnection('xoala', ['secure_key' => 'base-key']);
    Cashier::fakeConnection('xoala_second', ['secure_key' => 'second-key']);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function xoalaCallback(array $overrides = [], string $secureKey = 'base-key'): array
{
    $payload = array_merge([
        'paymentId' => '18608029',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'paymentBrand' => 'VISA',
        'paymentMode' => 'CC',
        'amount' => '50.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'timestamp' => '2026-09-09 12:00:00',
    ], $overrides);

    $payload['checksum'] = (new XoalaSignatureService('11344', $secureKey))->forCallback(
        (string) $payload['paymentId'],
        (string) $payload['merchantTransactionId'],
        (string) $payload['amount'],
        (string) $payload['transactionStatus'],
    );

    return $payload;
}

it('accepts a correctly signed JSON callback', function () {
    $this->postJson('/api/webhooks/xoala', xoalaCallback())
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('accepts a form-encoded callback', function () {
    $this->post('/api/webhooks/xoala', xoalaCallback())
        ->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('rejects a callback signed with an unknown key', function () {
    Event::fake([WebhookRejected::class]);

    $this->postJson('/api/webhooks/xoala', xoalaCallback(secureKey: 'not-ours'))
        ->assertForbidden();

    Queue::assertNothingPushed();
    Event::assertDispatched(WebhookRejected::class);
});

it('rejects a callback carrying no checksum', function () {
    $payload = xoalaCallback();
    unset($payload['checksum']);

    $this->postJson('/api/webhooks/xoala', $payload)->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a callback whose amount was altered after signing', function () {
    $payload = xoalaCallback();
    $payload['amount'] = '5000.00';

    $this->postJson('/api/webhooks/xoala', $payload)->assertForbidden();

    Queue::assertNothingPushed();
});

it('matches the second account by its own key', function () {
    $this->postJson('/api/webhooks/xoala', xoalaCallback(secureKey: 'second-key'))
        ->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        // `connectionName`, NOT `connection`: the job renames it deliberately
        // because Laravel's Queueable trait already owns a `$connection`
        // property holding the QUEUE connection. Asserting on `connection`
        // reads that instead and never sees the PSP account.
        fn ($job) => $job->connectionName === 'xoala_second',
    );
});

it('acks a replayed delivery without dispatching it twice', function () {
    $payload = xoalaCallback();

    $this->postJson('/api/webhooks/xoala', $payload)->assertOk();
    $this->postJson('/api/webhooks/xoala', $payload)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);
});

it('builds a verifiable delivery through the simulator', function () {
    $delivery = Asciisd\CashierCore\Testing\WebhookSimulator::make('xoala', [
        'paymentId' => '99',
        'merchantTransactionId' => 'DEP-9',
        'amount' => '10.00',
        'transactionStatus' => 'Y',
        'status' => 'capturesuccess',
    ]);

    $this->post($delivery->uri, $delivery->payload)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});
