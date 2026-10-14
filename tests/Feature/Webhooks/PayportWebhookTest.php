<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Payport\PayportProvider;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/*
 * Ported from the host app's PayportWebhookTest. Status-mapping outcomes
 * (paid → Succeeded, the two documented cancel codes → Canceled) are covered
 * by tests/Unit/Drivers/PayportAdapterTest.php, and the out-of-order /
 * unknown-transaction guards by WebhookProcessorTest — here the controller
 * contract (signature matching across accounts, redacted rejection logging,
 * replay guard, job dispatch) is what's under test.
 */

beforeEach(function () {
    // The driver-named connection is the one ProcessPaymentProviderWebhook
    // falls back to when no connection matched, so it has to be configured
    // even though the charge came through one of the filtered connections.
    fakePayportConnection('payport', ['api_key' => 'base-api5-key']);
    fakePayportConnection('payport_sar', ['api_key' => 'sar-api5-key', 'filter_fiats' => ['SAR']]);
    fakePayportConnection('payport_egy', ['api_key' => 'egy-api5-key', 'filter_fiats' => ['EGP']]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function fakePayportConnection(string $name, array $overrides = []): void
{
    config()->set("cashier-core.connections.{$name}", array_merge([
        'driver' => 'payport',
        'class' => PayportProvider::class,
        'base_url' => 'https://payport.test',
        'api_key' => 'test-api5-key',
        'success_url' => 'https://members.example.com/payment/success',
        'cancel_url' => 'https://members.example.com/payment/failed',
        'webhook_url' => 'https://members.example.com/api/webhooks/payport',
    ], $overrides));
}

/**
 * Build a callback signed the way Payport signs one: the API key followed by
 * every non-empty value, pipe-prefixed in key order, hashed with SHA-1.
 */
function payportCallback(string $orderId, string $status = '1', array $overrides = [], string $apiKey = 'sar-api5-key'): array
{
    $payload = array_filter(array_merge([
        'invoice_id' => '11244408',
        'merchant_id' => '1169',
        'order_id' => $orderId,
        'amount' => '50',
        'amount_currency' => '50',
        'currency' => 'USD',
        'merchant_amount' => '48.5',
        'status' => $status,
        'account_info' => '455691******7441',
        'fiat_currency' => 'SAR',
        'fiat_amount' => '187.5',
        'payment_system_type' => 'card_number',
    ], $overrides), fn ($value) => $value !== null && $value !== '');

    $sorted = $payload;
    ksort($sorted);

    $signature = $apiKey;
    foreach ($sorted as $value) {
        $signature .= '|'.trim((string) $value);
    }

    $payload['signature'] = strtolower(sha1($signature));

    return $payload;
}

it('rejects a callback with an invalid signature', function () {
    Event::fake([WebhookRejected::class]);

    $payload = payportCallback('DEP-1');
    $payload['signature'] = 'tampered';

    $this->post('/api/webhooks/payport', $payload)->assertForbidden();

    Queue::assertNothingPushed();

    Event::assertDispatched(
        WebhookRejected::class,
        fn (WebhookRejected $event) => $event->driver === 'payport' && $event->reason === 'invalid signature'
    );
});

// Without the payload, a rejected callback is undiagnosable: a rotated key, a
// stale invoice and a changed signing scheme all log identically. Redaction
// now flows through PayloadRedactor and `cashier-core.security.redact_keys`,
// whose defaults already cover `account_info`.
it('logs the rejected payload with the payer details removed', function () {
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $payload = payportCallback('DEP-1');
    $payload['signature'] = 'tampered';

    $this->post('/api/webhooks/payport', $payload)->assertForbidden();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $message === 'Payment provider webhook signature verification failed'
            && $context['provider'] === 'payport'
            && $context['signature'] === 'tampered'
            && $context['payload']['order_id'] === 'DEP-1'
            && $context['payload']['status'] === '1'
            // The masked PAN identifies the payer and is not needed to diagnose
            // a signature mismatch.
            && $context['payload']['account_info'] === '[redacted]'
    );
});

it('rejects a callback whose amount was altered after signing', function () {
    $payload = payportCallback('DEP-1');
    $payload['amount_currency'] = '5000';

    $this->post('/api/webhooks/payport', $payload)->assertForbidden();
});

it('rejects a callback signed with a key no configured account holds', function () {
    $this->post('/api/webhooks/payport', payportCallback('DEP-1', apiKey: 'not-our-key'))
        ->assertForbidden();
});

it('accepts a callback signed by either Payport account', function (string $apiKey, string $connection) {
    // Adapted from the app: the transaction settling is the pipeline's job —
    // the controller's contract is matching the signing account and queueing
    // under that connection.
    $this->post('/api/webhooks/payport', payportCallback('DEP-ACCOUNT', apiKey: $apiKey))->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'payport'
            && $job->connectionName === $connection
    );
})->with([
    'unfiltered account' => ['base-api5-key', 'payport'],
    'SAR account' => ['sar-api5-key', 'payport_sar'],
    'EGP account' => ['egy-api5-key', 'payport_egy'],
]);

it('queues the webhook job with the matched connection on a paid callback', function () {
    $this->post('/api/webhooks/payport', payportCallback('DEP-1'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'payport'
            && $job->connectionName === 'payport_sar'
            && $job->payload['order_id'] === 'DEP-1'
    );
});

// Behavior delta vs the app: a replayed callback used to be re-dispatched and
// ignored downstream by the duplicate-status guard. The replay guard now ACKs
// the second delivery without dispatching anything, which is also what keeps
// the invoice from being sent once per delivery attempt.
it('ignores a replayed callback', function () {
    $payload = payportCallback('DEP-3');

    $this->post('/api/webhooks/payport', $payload)->assertOk();
    $this->post('/api/webhooks/payport', $payload)
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    // One claim for the delivery — the replay was recognized, not re-claimed.
    expect(WebhookEvent::query()->count())->toBe(1);
});

it('accepts a callback for a transaction it does not know', function () {
    // Correlation happens in the queued job; the endpoint ACKs a verified
    // delivery regardless of whether a local row exists for it.
    $this->post('/api/webhooks/payport', payportCallback('DEP-UNKNOWN'))->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->payload['order_id'] === 'DEP-UNKNOWN'
    );
});

it('skips signature verification when it is disabled', function () {
    config()->set('cashier-core.webhooks.verify_signature', false);

    $payload = payportCallback('DEP-5');
    unset($payload['signature']);

    $this->post('/api/webhooks/payport', $payload)->assertOk();

    // No signature means no account was matched — the job falls back to the
    // driver-named connection when it parses.
    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->connectionName === null
            && $job->payload['order_id'] === 'DEP-5'
    );
});
