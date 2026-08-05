<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Aps\ApsProvider;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    fakeApsConnection('aps', [
        'merchant_guid' => 'test-guid',
        'app_token' => 'test-token',
        'app_secret' => 'test-secret',
        'callback_secret' => 'test-callback-secret',
    ]);

    fakeApsConnection('aps_binance', [
        'base_url' => 'https://aps-binance.test',
        'merchant_guid' => 'binance-merchant-guid',
        'app_token' => 'binance-token',
        'app_secret' => 'binance-secret',
        'callback_secret' => 'binance-callback-secret',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function fakeApsConnection(string $name, array $overrides = []): void
{
    config()->set("cashier-core.connections.{$name}", array_merge([
        'driver' => 'aps',
        'class' => ApsProvider::class,
        'base_url' => "https://{$name}.test",
        'merchant_guid' => "{$name}-merchant-guid",
        'app_token' => "{$name}-token",
        'app_secret' => "{$name}-secret",
        'callback_secret' => "{$name}-callback-secret",
        'deposit_method' => "{$name}-deposit-guid",
        'redirect_url' => 'https://members.example.com/payment/success',
        'webhook_url' => 'https://members.example.com/api/webhooks/aps',
        'checkout_host_map' => ['api.pci-gw.com' => 'form.pci-gw.com'],
    ], $overrides));
}

function apsWebhookPayload(string $transactionId, string $status = 'done'): array
{
    return [
        'payload' => [
            'transaction_id' => $transactionId,
            'sep31_status' => $status === 'done' ? 'completed' : 'error',
            'status' => $status,
            'refunded' => false,
            'amount_in' => 500,
            'amount_out' => 475.5,
            'external_message' => 'APPROVED',
        ],
    ];
}

function signedApsHeaders(array $payload, string $secret = 'test-callback-secret'): array
{
    return ['X-Signature' => hash_hmac('sha256', (string) json_encode($payload), $secret)];
}

it('rejects a callback with an invalid signature', function () {
    Event::fake([WebhookRejected::class]);

    $payload = apsWebhookPayload('tx-1');

    $this->postJson('/api/webhooks/aps', $payload, ['X-Signature' => 'bogus'])
        ->assertForbidden();

    Queue::assertNothingPushed();

    Event::assertDispatched(
        WebhookRejected::class,
        fn (WebhookRejected $event) => $event->driver === 'aps' && $event->reason === 'invalid signature'
    );
});

it('rejects a callback signed with the app secret instead of the callback secret', function () {
    $payload = apsWebhookPayload('tx-1');

    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload, 'test-secret'))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('falls back to the app secret when no callback secret is configured', function () {
    config()->set('cashier-core.connections.aps.callback_secret', null);

    $payload = apsWebhookPayload('tx-fallback');

    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload, 'test-secret'))
        ->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'aps' && $job->connectionName === 'aps'
    );
});

it('queues the webhook job with the matched connection on a signed callback', function () {
    $payload = apsWebhookPayload('tx-1');

    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'aps'
            && $job->connectionName === 'aps'
            && $job->payload['payload']['transaction_id'] === 'tx-1'
    );
});

it('fires WebhookReceived with the redacted payload after queueing', function () {
    Event::fake([WebhookReceived::class]);

    $payload = apsWebhookPayload('tx-events');

    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload))->assertOk();

    Event::assertDispatched(
        WebhookReceived::class,
        fn (WebhookReceived $event) => $event->driver === 'aps'
            && $event->connection === 'aps'
            && $event->redactedPayload['payload']['transaction_id'] === 'tx-events'
    );
});

it('accepts a callback signed by the Binance merchant account', function () {
    $payload = apsWebhookPayload('tx-binance');

    // Both APS accounts post to the same URL with no merchant identifier in the
    // body, so a signature match is what identifies the sending account.
    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload, 'binance-callback-secret'))
        ->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->connectionName === 'aps_binance'
    );
});

it('still rejects a callback signed by neither APS account', function () {
    $payload = apsWebhookPayload('tx-unknown');

    $this->postJson('/api/webhooks/aps', $payload, signedApsHeaders($payload, 'some-other-secret'))
        ->assertForbidden();
});

// Behavior delta vs the app: a duplicate identical delivery used to be
// re-dispatched and de-duplicated downstream. The replay guard now ACKs the
// second delivery without dispatching anything.
it('acknowledges a duplicate delivery without queueing a second job', function () {
    $payload = apsWebhookPayload('tx-replay');
    $headers = signedApsHeaders($payload);

    $this->postJson('/api/webhooks/aps', $payload, $headers)->assertOk();
    $this->postJson('/api/webhooks/aps', $payload, $headers)
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    // One claim for the delivery — the replay was recognized, not re-claimed.
    expect(WebhookEvent::query()->count())->toBe(1);
});

it('skips signature verification when disabled outside production', function () {
    config()->set('cashier-core.webhooks.verify_signature', false);

    $payload = apsWebhookPayload('tx-unverified');

    $this->postJson('/api/webhooks/aps', $payload)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->connectionName === null
    );
});

it('refuses to skip signature verification in production', function () {
    config()->set('cashier-core.webhooks.verify_signature', false);
    $this->app['env'] = 'production';

    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    try {
        // Unsigned: with verification force-enabled, the delivery must be refused.
        $this->postJson('/api/webhooks/aps', apsWebhookPayload('tx-prod'))
            ->assertForbidden();

        Queue::assertNothingPushed();

        Log::shouldHaveReceived('critical')->withArgs(
            fn (string $message) => str_contains($message, 'refusing to honor disabled webhook signature verification')
        );
    } finally {
        // Testbench rolls migrations back on app destroy; leaving the env at
        // `production` makes that confirmable command prompt into a mock.
        $this->app['env'] = 'testing';
    }
});
