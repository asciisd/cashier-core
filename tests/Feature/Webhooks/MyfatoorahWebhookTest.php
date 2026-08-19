<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Events\WebhookReceived;
use Asciisd\CashierCore\Events\WebhookRejected;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\WebhookEvent;
use Asciisd\CashierCore\Testing\WebhookSimulator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cashier::fakeConnection('myfatoorah');

    Cashier::fakeConnection('myfatoorah_sau', [
        'base_url' => 'https://apisa.myfatoorah.com',
        'api_key' => 'sau-key',
        'webhook_secret' => 'sau-webhook-secret',
        'currency' => 'SAR',
    ]);

    config()->set('cashier-core.webhooks.verify_signature', true);

    Queue::fake();
});

function myfatoorahEvent(string $invoiceId = '6409988', string $status = 'SUCCESS', int $code = 1): array
{
    return [
        'Event' => [
            'Code' => $code,
            'Name' => 'PAYMENT_STATUS_CHANGED',
            'CountryIsoCode' => 'KWT',
            'CreationDate' => '2026-01-04T08:15:00.9500000Z',
            'Reference' => 'WH-626519',
        ],
        'Data' => [
            'Invoice' => [
                'Id' => $invoiceId,
                'Status' => $status === 'SUCCESS' ? 'PAID' : 'PENDING',
                'ExternalIdentifier' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            ],
            'Transaction' => [
                'Id' => '86781',
                'Status' => $status,
                'PaymentId' => '07076409988323998875',
                'PaymentMethod' => 'VISA/MASTER',
                'Error' => ['Code' => '', 'Message' => ''],
            ],
            'Amount' => [
                'BaseCurrency' => 'KWD',
                'ValueInBaseCurrency' => '100',
                'DisplayCurrency' => 'KWD',
                'ValueInDisplayCurrency' => '100',
            ],
        ],
    ];
}

it('accepts a correctly signed delivery and dispatches the job', function () {
    Event::fake([WebhookReceived::class]);

    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
    Event::assertDispatched(WebhookReceived::class);
});

it('threads the connection whose secret verified the delivery', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(), connection: 'myfatoorah_sau');

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, function ($job) {
        return $job->connectionName === 'myfatoorah_sau';
    });
});

it('rejects a delivery with an invalid signature', function () {
    Event::fake([WebhookRejected::class]);

    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => 'not-a-real-signature',
        'MyFatoorah-Webhook-Version' => 'v2',
    ])->assertForbidden();

    Queue::assertNothingPushed();
    Event::assertDispatched(WebhookRejected::class);
});

it('rejects a delivery signed with a secret belonging to no connection', function () {
    $payload = myfatoorahEvent();

    $signature = base64_encode(hash_hmac(
        'sha256',
        'Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07076409988323998875,Invoice.ExternalIdentifier=DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
        'a-secret-nobody-configured',
        true,
    ));

    $this->postJson('/api/webhooks/myfatoorah', $payload, [
        'MyFatoorah-Signature' => $signature,
        'MyFatoorah-Webhook-Version' => 'v2',
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a delivery whose signed field was tampered with after signing', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $tampered = $delivery->payload;
    $tampered['Data']['Transaction']['Status'] = 'SUCCESS';
    $tampered['Data']['Invoice']['Id'] = '9999999';

    $this->postJson($delivery->uri, $tampered, $delivery->headers)->assertForbidden();

    Queue::assertNothingPushed();
});

/*
 * The version header is undocumented but MyFatoorah's own library reads it and
 * refuses without it. Inferring the version from the payload shape is not an
 * option — V1 and V2 bodies are close enough to confuse, and the two signing
 * rules are incompatible.
 */
it('rejects a delivery carrying no version header', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => $delivery->headers['MyFatoorah-Signature'],
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects a v1 delivery rather than verifying it with the v2 rule', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, [
        'MyFatoorah-Signature' => $delivery->headers['MyFatoorah-Signature'],
        'MyFatoorah-Webhook-Version' => 'v1',
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

/*
 * A refund, deposit or supplier event. Acknowledged so MyFatoorah stops
 * retrying — a non-200 for a transient reason can lose an event permanently —
 * but nothing is dispatched and nothing is acted on.
 */
it('acknowledges an event it does not handle without dispatching anything', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(code: 2));

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertNothingPushed();
});

it('acknowledges a duplicate delivery once and dispatches only the first', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent());

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();
    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);

    expect(WebhookEvent::query()->where('driver', 'myfatoorah')->count())->toBe(1);
});

it('accepts a failed payment event', function () {
    $delivery = WebhookSimulator::make('myfatoorah', myfatoorahEvent(status: 'FAILED'));

    $this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});
