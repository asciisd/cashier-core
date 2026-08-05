<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Testing\WebhookSimulator;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('cashier-core.webhooks.verify_signature', true);
    config()->set('cashier-core.security.encrypt_provider_payload', false);
});

it('signs an APS delivery the account verifies', function () {
    Cashier::fakeConnection('aps');
    Queue::fake();

    $delivery = WebhookSimulator::make('aps', [
        'payload' => ['transaction_id' => 'tx-sim-1', 'status' => 'done', 'amount_in' => 100],
    ]);

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'aps' && $job->connectionName === 'aps'
    );
});

it('signs with the named account so the matched connection follows', function () {
    Cashier::fakeConnection('aps');
    Cashier::fakeConnection('aps_binance');
    Queue::fake();

    $delivery = WebhookSimulator::make('aps', [
        'payload' => ['transaction_id' => 'tx-sim-2', 'status' => 'done'],
    ], connection: 'aps_binance');

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->connectionName === 'aps_binance'
    );
});

it('signs a Heropayment delivery the account verifies', function () {
    Cashier::fakeConnection('heropayment');
    Queue::fake();

    $delivery = WebhookSimulator::make('heropayment', [
        'externalOrderId' => 'DEP-SIM-1',
        'status' => 'finished',
    ]);

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'heropayment'
    );
});

it('hashes a Jenapay callback the controller accepts', function () {
    Cashier::fakeConnection('jenapay');
    Queue::fake();

    $delivery = WebhookSimulator::make('jenapay', [
        'id' => 'pay-sim-1',
        'order_number' => 'DEP-SIM-2',
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'order_description' => 'Test deposit',
        'type' => 'sale',
        'status' => 'settled',
    ]);

    expect($delivery->isJson())->toBeFalse();

    $this->post($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'jenapay'
    );
});

it('signs a Payport callback the account verifies', function () {
    Cashier::fakeConnection('payport');
    Queue::fake();

    $delivery = WebhookSimulator::make('payport', [
        'invoice_id' => '11244408',
        'order_id' => 'DEP-SIM-3',
        'amount' => '50',
        'currency' => 'USD',
        'status' => '1',
    ]);

    $this->post($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(
        ProcessPaymentProviderWebhook::class,
        fn (ProcessPaymentProviderWebhook $job) => $job->driver === 'payport' && $job->connectionName === 'payport'
    );
});

it('a tampered simulated delivery is rejected like a real one', function () {
    Cashier::fakeConnection('payport');
    Queue::fake();

    $delivery = WebhookSimulator::make('payport', [
        'order_id' => 'DEP-SIM-4',
        'amount' => '50',
        'status' => '1',
    ]);

    $payload = $delivery->payload;
    $payload['amount'] = '5000';

    $this->post($delivery->uri, $payload, $delivery->headers)->assertForbidden();

    Queue::assertNothingPushed();
});

it('wraps and signs a Sticpay callback that settles the transaction end to end', function () {
    Cashier::fakeConnection('sticpay');

    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'sticpay',
        'connection' => 'sticpay',
        'provider_transaction_id' => 'DEP-SIM-5',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    $delivery = WebhookSimulator::make('sticpay', [
        'merchant_email' => 'merchant@sticpay.test',
        'order_no' => 'DEP-SIM-5',
        'order_time' => '2026-08-04 07:22:21',
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'transaction_code' => '232857',
        'transaction_time' => '2026-08-04 07:24:45',
        'fee' => '4.42',
        'fee_currency' => 'USD',
        'interface_version' => 'live',
        'input_charset' => 'UTF-8',
        'customer_email' => 'payer@sticpay.test',
    ]);

    $this->post($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('refuses to simulate a driver it has no recipe for', function () {
    config()->set('cashier-core.connections.manual', ['driver' => 'manual']);

    WebhookSimulator::make('manual', []);
})->throws(InvalidArgumentException::class);

it('refuses to simulate against an unconfigured connection', function () {
    WebhookSimulator::make('aps', []);
})->throws(InvalidArgumentException::class, "connection 'aps' is not configured");
