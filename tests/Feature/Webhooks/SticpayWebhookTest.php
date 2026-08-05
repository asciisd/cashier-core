<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Drivers\Sticpay\SticpayProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Models\WebhookEvent;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Testing\FakeLedger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * Ported from the host app's SticpayWebhookTest. Unlike the other webhook
 * ports, the queue is NOT faked here: the app's assertions run the pipeline
 * end-to-end (the sync queue executes ProcessPaymentProviderWebhook inside
 * the request), and the package can do the same against its own Transaction
 * model — so the transaction-status assertions survive the port verbatim.
 * The app's PaymentInvoice mail assertions do not: the package sends nothing
 * itself (hosts hang mail off DepositSucceeded), so the delivery-once
 * guarantees are asserted against the replay guard instead.
 */

beforeEach(function () {
    // The connection must be named after the driver — that is the one
    // ProcessPaymentProviderWebhook falls back to to parse the callback.
    fakeSticpayConnection('sticpay');

    config()->set('cashier-core.webhooks.verify_signature', true);

    // Testbench boots without an APP_KEY; stored payloads stay plain here,
    // as in WebhookProcessorTest.
    config()->set('cashier-core.security.encrypt_provider_payload', false);
});

function fakeSticpayConnection(string $name, array $overrides = []): void
{
    config()->set("cashier-core.connections.{$name}", array_merge([
        'driver' => 'sticpay',
        'class' => SticpayProvider::class,
        'base_url' => 'https://sticpay.test',
        'merchant_email' => 'merchant@sticpay.test',
        'api_key' => 'test-sticpay-key',
        'sign_type' => 'MD5',
        'interface_version' => 'live',
        'success_url' => 'https://members.example.com/api/payments/sticpay/return/success',
        'failure_url' => 'https://members.example.com/api/payments/sticpay/return/failure',
        'referrer_url' => 'https://members.example.com/api/payments/sticpay/return/cancel',
        'callback_url' => 'https://members.example.com/api/webhooks/sticpay',
        // Off by default in tests: confirming through the Transaction Detail
        // API means every webhook case would need a second Http::fake stub.
        // The cases that exercise confirmation turn it on explicitly.
        'confirm_with_detail_api' => false,
    ], $overrides));
}

/**
 * A callback signed the way Sticpay signs one: the seven documented fields in
 * the documented order, then the API key, hashed.
 */
function sticpayCallbackParameters(
    string $orderNo,
    array $overrides = [],
    string $apiKey = 'test-sticpay-key',
    string $signType = 'MD5',
): array {
    $signed = array_merge([
        'merchant_email' => 'merchant@sticpay.test',
        'order_no' => $orderNo,
        'order_time' => '2026-08-04 07:22:21',
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'transaction_code' => '232857',
        'transaction_time' => '2026-08-04 07:24:45',
    ], array_intersect_key($overrides, array_flip([
        'merchant_email', 'order_no', 'order_time', 'order_amount',
        'order_currency', 'transaction_code', 'transaction_time',
    ])));

    $pairs = [];
    foreach ($signed as $field => $value) {
        $pairs[] = "{$field}={$value}";
    }
    $pairs[] = "key={$apiKey}";
    $canonical = implode('&', $pairs);

    return array_merge($signed, [
        'fee' => '4.42',
        'fee_currency' => 'USD',
        'interface_version' => 'live',
        'input_charset' => 'UTF-8',
        'sign_type' => $signType,
        'customer_email' => 'payer@sticpay.test',
        'sign' => strtoupper($signType) === 'SHA256'
            ? strtolower(hash('sha256', $canonical))
            : strtolower(md5($canonical)),
    ], $overrides);
}

/** The wire shape: one form field holding a double JSON-encoded envelope. */
function sticpayCallbackBody(array $parameters, int $code = -1, string $type = 'processing'): array
{
    return ['callback' => json_encode([
        'type' => $type,
        'code' => $code,
        'message' => '',
        'parameters' => json_encode($parameters),
    ])];
}

function sticpayTransaction(string $orderNo, PaymentStatus $status = PaymentStatus::Pending): Transaction
{
    return Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'sticpay',
        'connection' => 'sticpay',
        'provider_transaction_id' => $orderNo,
        'type' => TransactionType::Deposit,
        'status' => $status,
        'amount' => 100,
        'currency' => 'USD',
        'metadata' => [],
        'processed_at' => null,
        'failed_at' => null,
    ]);
}

/*
 * The one behaviour that differs from every other webhook in the package.
 * Sticpay reads the body literally and keeps redelivering until the
 * merchant-side retry_count is exhausted if it is anything other than "OK".
 */
it('answers a valid callback with the plain text OK', function () {
    sticpayTransaction('DEP-1');

    $response = $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-1')));

    $response->assertOk();

    expect($response->getContent())->toBe('OK')
        ->and($response->headers->get('Content-Type'))->toContain('text/plain');
});

it('rejects a callback with an invalid signature', function () {
    $parameters = sticpayCallbackParameters('DEP-1', ['sign' => 'tampered']);

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertForbidden();
});

// Without the payload, a rejected callback is undiagnosable: a rotated key, a
// wrong sign_type and a changed signing scheme all log identically. Redaction
// now flows through PayloadRedactor and `cashier-core.security.redact_keys`,
// whose defaults already cover `customer_email` and `merchant_email`.
it('logs the rejected payload with the payer and merchant details removed', function () {
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();

    $parameters = sticpayCallbackParameters('DEP-1', ['sign' => 'tampered']);

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertForbidden();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $message === 'Payment provider webhook signature verification failed'
            && $context['provider'] === 'sticpay'
            && $context['signature'] === 'tampered'
            && $context['payload']['order_no'] === 'DEP-1'
            && $context['payload']['order_amount'] === '100.00'
            // Neither the payer's wallet address nor our own merchant address
            // is needed to diagnose a signature mismatch.
            && $context['payload']['customer_email'] === '[redacted]'
            && $context['payload']['merchant_email'] === '[redacted]'
    );
});

it('rejects a callback whose amount was altered after signing', function () {
    $parameters = sticpayCallbackParameters('DEP-1');
    $parameters['order_amount'] = '99999.00';

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertForbidden();
});

it('rejects a callback signed with a key no configured account holds', function () {
    $parameters = sticpayCallbackParameters('DEP-1', apiKey: 'not-our-key');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertForbidden();
});

/*
 * §2-6 B documents the callback digest as MD5 while the payload carries its own
 * sign_type and §1 makes the encryption type a merchant-side setting. Either
 * has to be accepted.
 */
it('accepts a callback signed with the other hash type', function () {
    $transaction = sticpayTransaction('DEP-SHA');

    $parameters = sticpayCallbackParameters('DEP-SHA', signType: 'SHA256');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('marks the transaction succeeded and records the reconciliation metadata', function () {
    $transaction = sticpayTransaction('DEP-2');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-2')))->assertOk();

    $transaction->refresh();

    expect($transaction->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->metadata['sticpay_transaction_code'])->toBe('232857')
        ->and($transaction->metadata['sticpay_fee'])->toBe('4.42')
        ->and($transaction->metadata['sticpay_merchant_amount'])->toBe(95.58)
        ->and($transaction->payment_method_display_name)->toBe('Sticpay');
});

// Behavior delta vs the app: the replayed delivery used to be re-dispatched
// and ignored downstream by the duplicate-status guard (which is what kept
// the invoice single). The replay guard now ACKs the second delivery — with
// the literal "OK" Sticpay reads — without dispatching anything.
it('ignores a replayed callback', function () {
    $transaction = sticpayTransaction('DEP-3');
    $body = sticpayCallbackBody(sticpayCallbackParameters('DEP-3'));

    $this->post('/api/webhooks/sticpay', $body)->assertOk();
    $this->post('/api/webhooks/sticpay', $body)->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded)
        // One claim for the delivery — the replay was recognized, not re-claimed.
        ->and(WebhookEvent::query()->count())->toBe(1);
});

it('ignores an out-of-order callback for a settled transaction', function () {
    $transaction = sticpayTransaction('DEP-4', PaymentStatus::Succeeded);

    $parameters = sticpayCallbackParameters('DEP-4');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters, code: 800))->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('accepts a callback for a transaction it does not know', function () {
    $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-UNKNOWN')))
        ->assertOk();

    expect(Transaction::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('skips signature verification when it is disabled', function () {
    config()->set('cashier-core.webhooks.verify_signature', false);

    $transaction = sticpayTransaction('DEP-5');

    $parameters = sticpayCallbackParameters('DEP-5');
    unset($parameters['sign']);

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters))->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('accepts the already-decoded envelope shape as well as the encoded one', function () {
    $transaction = sticpayTransaction('DEP-6');

    $this->post('/api/webhooks/sticpay', [
        'callback' => json_encode([
            'type' => 'processing',
            'code' => -1,
            'message' => '',
            // Sent as a real object rather than a nested JSON string.
            'parameters' => sticpayCallbackParameters('DEP-6'),
        ]),
    ])->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

// --- Environment guard ---------------------------------------------------
//
// Sticpay serves sandbox and live from one endpoint, distinguished only by
// `interface_version`. The vendor docs are explicit that a sandbox callback
// must never credit real funds.

it('holds a sandbox callback arriving on a live connection without crediting it', function () {
    $transaction = sticpayTransaction('DEP-SANDBOX');

    $parameters = sticpayCallbackParameters('DEP-SANDBOX', ['interface_version' => 'sandbox']);

    // "OK" so Sticpay stops retrying something we will never act on.
    $response = $this->post('/api/webhooks/sticpay', sticpayCallbackBody($parameters));

    $response->assertOk();

    // Pending, not merely "not Succeeded": the controller's early exit means
    // nothing was dispatched at all — the Processing hold below only appears
    // when the guard runs inside the job instead.
    expect($response->getContent())->toBe('OK')
        ->and($transaction->refresh()->status)->toBe(PaymentStatus::Pending);
});

/*
 * Asserted against the job rather than the endpoint: the controller's check is
 * only an early exit for retry hygiene. The authoritative guard lives in
 * SticpayProvider::parseWebhook(), which also covers admin-initiated sync —
 * deleting the controller check must not open a hole.
 *
 * Adapted from the app: the MT5Service mock becomes the package's FundsLedger
 * fake — the ledger is the only thing that can move money here.
 */
it('never credits the ledger for a sandbox callback even when dispatched directly', function () {
    $transaction = sticpayTransaction('DEP-SANDBOX-JOB');

    $ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $ledger);

    $parameters = sticpayCallbackParameters('DEP-SANDBOX-JOB', ['interface_version' => 'sandbox']);

    (new ProcessPaymentProviderWebhook('sticpay', $parameters + [
        'callback_type' => 'processing',
        'callback_code' => -1,
        'callback_message' => [],
    ]))->handle(app(ConnectionRegistry::class), app(WebhookProcessor::class));

    $transaction->refresh();

    expect($transaction->status)->toBe(PaymentStatus::Processing)
        ->and($transaction->metadata['requires_attention'])->toBeTrue()
        ->and($transaction->mt5_ticket_number)->toBeNull();

    $ledger->assertNothingMoved();
});

// --- Detail API confirmation ---------------------------------------------
//
// The callback carries no status of its own, so by default the outcome is
// resolved with an outbound authenticated lookup rather than trusted from an
// inbound POST.

it('confirms the outcome against the transaction detail api before crediting', function () {
    fakeSticpayConnection('sticpay', ['confirm_with_detail_api' => true]);

    Http::fake(['sticpay.test/rest_transaction/detail' => Http::response([
        'success' => true,
        'transaction_code' => '232857',
        'order_id' => 'DEP-CONFIRM',
        'from_amount' => '100.00',
        'from_currency' => 'USD',
        'status' => 'approved',
    ])]);

    $transaction = sticpayTransaction('DEP-CONFIRM');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-CONFIRM')))
        ->assertOk();

    expect($transaction->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('fails the transaction when the detail api reports a rejection', function () {
    fakeSticpayConnection('sticpay', ['confirm_with_detail_api' => true]);

    Http::fake(['sticpay.test/rest_transaction/detail' => Http::response([
        'success' => true,
        'order_id' => 'DEP-REJECTED',
        'from_amount' => '100.00',
        'from_currency' => 'USD',
        'status' => 'rejected',
    ])]);

    $transaction = sticpayTransaction('DEP-REJECTED');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-REJECTED')))
        ->assertOk();

    // The signature was valid and the callback fired — only the lookup reveals
    // that Sticpay later rejected the transfer.
    expect($transaction->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('holds the transaction rather than crediting it when the lookup is rejected', function () {
    fakeSticpayConnection('sticpay', ['confirm_with_detail_api' => true]);

    Http::fake(['sticpay.test/rest_transaction/detail' => Http::response([
        'success' => false,
        'code' => 1401,
        'message' => 'Order ID was not found',
    ])]);

    $transaction = sticpayTransaction('DEP-NOLOOKUP');

    $this->post('/api/webhooks/sticpay', sticpayCallbackBody(sticpayCallbackParameters('DEP-NOLOOKUP')))
        ->assertOk();

    $transaction->refresh();

    expect($transaction->status)->toBe(PaymentStatus::Processing)
        ->and($transaction->metadata['requires_attention'])->toBeTrue();
});
