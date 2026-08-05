<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Jenapay\JenapayProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Jobs\RelayWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * The callback relay: a verified delivery that settles no transaction of ours
 * is forwarded verbatim to the party that owned the endpoint before us.
 *
 * Jenapay is the driver under test because it is the one with a relay URL in
 * the shipped config, but nothing here is Jenapay-specific — the hook lives in
 * WebhookRelay and every bundled controller calls it.
 */

beforeEach(function () {
    config()->set('cashier-core.connections.jenapay', [
        'driver' => 'jenapay',
        'class' => JenapayProvider::class,
        'checkout_url' => 'https://checkout.jenapay.test',
        'api_url' => 'https://api.jenapay.test',
        'merchant_key' => 'test-merchant',
        'password' => 'test-password',
        'success_url' => 'https://members.example.com/payment/success',
        'cancel_url' => 'https://members.example.com/payment/failed',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);
    config()->set('cashier-core.webhooks.relay.jenapay', 'https://relay.example.com/callback');

    Queue::fake();
});

function relayCallback(string $orderNumber): array
{
    $payload = [
        'id' => 'pay-1',
        'order_number' => $orderNumber,
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'order_description' => 'Test deposit',
        'type' => 'sale',
        'status' => 'settled',
    ];

    $payload['hash'] = sha1(md5(strtoupper(
        $payload['id'].$payload['order_number'].$payload['order_amount']
        .$payload['order_currency'].$payload['order_description'].'test-password'
    )));

    return $payload;
}

function relayOwnDeposit(string $orderNumber): Transaction
{
    return Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'jenapay',
        'provider_transaction_id' => $orderNumber,
        'type' => TransactionType::Deposit,
        'status' => PaymentStatus::Pending,
        'amount' => 100,
        'currency' => 'USD',
    ]);
}

it('relays a verified callback that matches no local transaction', function () {
    $this->post('/api/webhooks/jenapay', relayCallback('THEIRS-1'))->assertOk();

    Queue::assertPushed(
        RelayWebhook::class,
        fn (RelayWebhook $job) => $job->driver === 'jenapay'
            && $job->url === 'https://relay.example.com/callback'
    );
});

it('does not relay a callback that settles one of our own deposits', function () {
    relayOwnDeposit('OURS-1');

    $this->post('/api/webhooks/jenapay', relayCallback('OURS-1'))->assertOk();

    Queue::assertNotPushed(RelayWebhook::class);

    // Still ours to process — the relay decision never short-circuits the pipeline.
    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

// A transaction the customer deleted is still one we opened. Relaying its
// callback onward would report our own deposit as the third party's.
it('treats a soft-deleted transaction as our own and does not relay', function () {
    relayOwnDeposit('OURS-DELETED')->delete();

    $this->post('/api/webhooks/jenapay', relayCallback('OURS-DELETED'))->assertOk();

    Queue::assertNotPushed(RelayWebhook::class);
});

// A row under the same order number but a different driver is a different
// deposit. Matching on the id alone would swallow somebody else's callback.
it('does not treat another driver\'s transaction as our own', function () {
    relayOwnDeposit('SHARED-ID')->update(['provider' => 'aps']);

    $this->post('/api/webhooks/jenapay', relayCallback('SHARED-ID'))->assertOk();

    Queue::assertPushed(RelayWebhook::class);
});

it('relays nothing when the driver has no relay url configured', function () {
    config()->set('cashier-core.webhooks.relay', []);

    $this->post('/api/webhooks/jenapay', relayCallback('THEIRS-2'))->assertOk();

    Queue::assertNotPushed(RelayWebhook::class);
});

// The relay sits behind signature verification precisely so this endpoint
// cannot be used to pump arbitrary payloads at the third party on our behalf.
it('does not relay a callback whose signature fails', function () {
    $payload = relayCallback('THEIRS-3');
    $payload['hash'] = 'tampered';

    $this->post('/api/webhooks/jenapay', $payload)->assertForbidden();

    Queue::assertNotPushed(RelayWebhook::class);
});

it('does not relay a duplicate delivery a second time', function () {
    $payload = relayCallback('THEIRS-REPLAY');

    $this->post('/api/webhooks/jenapay', $payload)->assertOk();
    $this->post('/api/webhooks/jenapay', $payload)->assertOk();

    Queue::assertPushed(RelayWebhook::class, 1);
});

// The recipient parses whatever encoding the PSP sends, and for signed
// payloads a re-encode changes key order and escaping — which is exactly what
// the signature covers. The raw body has to survive the hop byte-for-byte.
it('relays the raw request body under its original content type', function () {
    $body = json_encode(relayCallback('THEIRS-RAW'));

    $this->call(
        'POST',
        '/api/webhooks/jenapay',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: $body,
    )->assertOk();

    Queue::assertPushed(
        RelayWebhook::class,
        fn (RelayWebhook $job) => $job->body === $body
            && $job->contentType === 'application/json'
    );
});

it('posts the relayed body verbatim when the job runs', function () {
    Http::fake(['relay.example.com/*' => Http::response('OK', 200)]);

    (new RelayWebhook('jenapay', 'https://relay.example.com/callback', '{"a":1}', 'application/json'))->handle();

    Http::assertSent(
        fn ($request) => $request->url() === 'https://relay.example.com/callback'
            && $request->body() === '{"a":1}'
            && $request->header('Content-Type')[0] === 'application/json'
    );
});

// Queued rather than inline: the PSP reads our response to decide whether
// delivery succeeded, so a dead relay host must never sit in front of the ACK.
it('retries a failing relay rather than losing it', function () {
    Http::fake(['relay.example.com/*' => Http::response('nope', 500)]);

    $job = new RelayWebhook('jenapay', 'https://relay.example.com/callback', '{}', 'application/json');

    expect(fn () => $job->handle())->toThrow(RequestException::class);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([10, 30, 60, 300]);
});
