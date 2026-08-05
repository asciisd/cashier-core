<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Jenapay\JenapayProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/*
 * Ported from the host app's JenapayWebhookTest. Status-mapping outcomes
 * (settled → Succeeded, refund → Canceled, declined → Failed) are covered by
 * tests/Unit/Drivers/JenapayAdapterTest.php and the pipeline by
 * WebhookProcessorTest — here the controller contract is what's under test.
 *
 * The app's relay tests (`transactions.webhooks.forward.jenapay`, forwarding
 * Paytiko-routed callbacks verbatim) are NOT ported: the package controller
 * has no forwarding feature. Hosts that need the Paytiko relay must keep
 * their own controller in front of the shared endpoint.
 */

beforeEach(function () {
    fakeJenapayConnection('jenapay', [
        'merchant_key' => 'test-merchant',
        'password' => 'test-password',
        'checkout_url' => 'https://checkout.jenapay.test',
        'api_url' => 'https://api.jenapay.test',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function fakeJenapayConnection(string $name, array $overrides = []): void
{
    config()->set("cashier-core.connections.{$name}", array_merge([
        'driver' => 'jenapay',
        'class' => JenapayProvider::class,
        'checkout_url' => 'https://checkout.jenapay.test',
        'api_url' => 'https://api.jenapay.test',
        'merchant_key' => 'test-merchant',
        'password' => 'test-password',
        'success_url' => 'https://members.example.com/payment/success',
        'cancel_url' => 'https://members.example.com/payment/failed',
    ], $overrides));
}

function jenapayCallback(string $orderNumber, string $status = 'settled'): array
{
    $payload = [
        'id' => 'pay-1',
        'order_number' => $orderNumber,
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'order_description' => 'Test deposit',
        'type' => $status === 'refund' ? 'refund' : 'sale',
        'status' => $status,
    ];

    $payload['hash'] = sha1(md5(strtoupper(
        $payload['id'].$payload['order_number'].$payload['order_amount']
        .$payload['order_currency'].$payload['order_description'].'test-password'
    )));

    return $payload;
}

it('rejects a callback with an invalid hash', function () {
    Event::fake([WebhookRejected::class]);

    $payload = jenapayCallback('DEP-1');
    $payload['hash'] = 'tampered';

    $this->post('/api/webhooks/jenapay', $payload)->assertForbidden();

    Queue::assertNothingPushed();

    Event::assertDispatched(
        WebhookRejected::class,
        fn (WebhookRejected $event) => $event->driver === 'jenapay' && $event->reason === 'invalid signature'
    );
});

// The Akurateco platform reads the response body, not the status code, to decide
// whether a callback was delivered. Anything other than the bare string `OK`
// puts the callback into its retry cycle.
it('acknowledges an accepted callback with the literal string OK', function () {
    $response = $this->post('/api/webhooks/jenapay', jenapayCallback('DEP-ACK'));

    $response->assertOk();

    expect($response->getContent())->toBe('OK');
});

it('answers ERROR when the callback hash does not verify', function () {
    $payload = jenapayCallback('DEP-NACK');
    $payload['hash'] = 'tampered';

    $response = $this->post('/api/webhooks/jenapay', $payload);

    $response->assertForbidden();

    expect($response->getContent())->toBe('ERROR');
});

it('queues the webhook job on a settled callback', function () {
    $this->post('/api/webhooks/jenapay', jenapayCallback('DEP-1'))->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'jenapay'
            && $job->connectionName === 'jenapay'
            && $job->payload['order_number'] === 'DEP-1'
            && $job->payload['status'] === 'settled'
    );
});

it('fires WebhookReceived with the redacted payload after queueing', function () {
    Event::fake([WebhookReceived::class]);

    $this->post('/api/webhooks/jenapay', jenapayCallback('DEP-EVENTS'))->assertOk();

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event) => $event->driver === 'jenapay'
            && $event->connection === 'jenapay'
            && $event->redactedPayload['order_number'] === 'DEP-EVENTS'
    );
});

// Behavior delta vs the app: a duplicate identical delivery used to be
// re-dispatched and de-duplicated downstream. The replay guard now ACKs the
// second delivery — with the literal `OK` the platform expects — without
// dispatching anything.
it('acknowledges a duplicate delivery without queueing a second job', function () {
    $payload = jenapayCallback('DEP-REPLAY');

    $this->post('/api/webhooks/jenapay', $payload)->assertOk();

    $response = $this->post('/api/webhooks/jenapay', $payload);

    $response->assertOk();

    expect($response->getContent())->toBe('OK');

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    // One claim for the delivery — the replay was recognized, not re-claimed.
    expect(WebhookEvent::query()->count())->toBe(1);
});
