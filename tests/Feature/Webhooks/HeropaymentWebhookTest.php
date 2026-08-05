<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/*
 * Ported from the host app's HeropaymentWebhookTest. Status-mapping outcomes
 * (finished/sending → Succeeded, hold → Processing + requires_attention) are
 * covered by tests/Unit/Drivers/HeropaymentAdapterTest.php and the pipeline
 * by WebhookProcessorTest — here the controller contract is what's under test.
 */

beforeEach(function () {
    fakeHeropaymentConnection('heropayment', [
        'api_key' => 'test-key',
        'api_secret' => 'test-secret',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function fakeHeropaymentConnection(string $name, array $overrides = []): void
{
    config()->set("cashier-core.connections.{$name}", array_merge([
        'driver' => 'heropayment',
        'class' => HeropaymentProvider::class,
        'base_url' => 'https://hero.test',
        'api_key' => 'test-key',
        'api_secret' => 'test-secret',
        'success_url' => 'https://members.example.com/payment/success',
        'fail_url' => 'https://members.example.com/payment/failed',
        'webhook_url' => 'https://members.example.com/api/webhooks/heropayment',
        'currencies' => ['usdttrc20', 'usdt20', 'usdc'],
    ], $overrides));
}

function heropaymentCallback(string $externalOrderId, string $status = 'finished'): array
{
    return [
        'id' => 'hero-pay-1',
        'externalOrderId' => $externalOrderId,
        'status' => $status,
        'priceAmount' => '100',
        'priceCurrency' => 'usd',
        'payCurrency' => 'usdttrc20',
    ];
}

function signedHeroHeaders(array $payload): array
{
    return ['x-api-sign' => hash_hmac('sha512', (string) json_encode($payload), 'test-secret')];
}

it('rejects a callback with an invalid signature', function () {
    Event::fake([WebhookRejected::class]);

    $payload = heropaymentCallback('DEP-1');

    $this->postJson('/api/webhooks/heropayment', $payload, ['x-api-sign' => 'bogus'])
        ->assertForbidden();

    Queue::assertNothingPushed();

    Event::assertDispatched(
        WebhookRejected::class,
        fn (WebhookRejected $event) => $event->driver === 'heropayment' && $event->reason === 'invalid signature'
    );
});

it('queues the webhook job on a finished callback', function () {
    $payload = heropaymentCallback('DEP-1');

    $this->postJson('/api/webhooks/heropayment', $payload, signedHeroHeaders($payload))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'heropayment'
            && $job->connectionName === 'heropayment'
            && $job->payload['externalOrderId'] === 'DEP-1'
            && $job->payload['status'] === 'finished'
    );
});

it('fires WebhookReceived with the redacted payload after queueing', function () {
    Event::fake([WebhookReceived::class]);

    $payload = heropaymentCallback('DEP-EVENTS');

    $this->postJson('/api/webhooks/heropayment', $payload, signedHeroHeaders($payload))->assertOk();

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event) => $event->driver === 'heropayment'
            && $event->connection === 'heropayment'
            && $event->redactedPayload['externalOrderId'] === 'DEP-EVENTS'
    );
});

// Behavior delta vs the app: the retried delivery used to be re-dispatched and
// de-duplicated downstream (which is what kept the credit single). The replay
// guard now ACKs the retry without dispatching anything, so the double-credit
// can no longer even reach the pipeline.
it('does not double-credit when the finished callback is retried', function () {
    $payload = heropaymentCallback('DEP-4');
    $headers = signedHeroHeaders($payload);

    $this->postJson('/api/webhooks/heropayment', $payload, $headers)->assertOk();
    $this->postJson('/api/webhooks/heropayment', $payload, $headers)
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    // One claim for the delivery — the retry was recognized, not re-claimed.
    expect(WebhookEvent::query()->count())->toBe(1);
});
