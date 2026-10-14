<?php

use Asciisd\CashierCore\Drivers\Sticpay\SticpayCallbackPayload;

function sticpayEnvelopeParameters(array $overrides = []): array
{
    return array_merge([
        'sign' => '226e2e78ebb61c3ba6e07c85b613f7d6',
        'fee_currency' => 'USD',
        'fee' => '442',
        'transaction_code' => '232857',
        'transaction_time' => '2018-03-15 16:22:45',
        'merchant_email' => 'merchant@sticpay.com',
        'order_no' => 'DEP-1',
        'order_time' => '2018-03-15 07:22:21',
        'order_amount' => '5.03',
        'order_currency' => 'USD',
        'interface_version' => 'live',
        'input_charset' => 'UTF-8',
        'sign_type' => 'MD5',
    ], $overrides);
}

/**
 * The wire format: one form field holding JSON, whose `message` and
 * `parameters` are themselves JSON strings.
 */
function sticpayEncodedBody(array $envelope): array
{
    if (isset($envelope['parameters']) && is_array($envelope['parameters'])) {
        $envelope['parameters'] = json_encode($envelope['parameters']);
    }

    if (isset($envelope['message']) && is_array($envelope['message'])) {
        $envelope['message'] = json_encode($envelope['message']);
    }

    return ['callback' => json_encode($envelope)];
}

it('decodes the fully stringified envelope Sticpay actually posts', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEncodedBody([
        'type' => 'processing',
        'code' => -1,
        'message' => '',
        'parameters' => sticpayEnvelopeParameters(),
    ]));

    expect($payload->type)->toBe('processing')
        ->and($payload->code)->toBe(-1)
        ->and($payload->messages)->toBe([])
        ->and($payload->parameters['order_no'])->toBe('DEP-1')
        ->and($payload->parameters['transaction_code'])->toBe('232857')
        ->and($payload->orderNo())->toBe('DEP-1')
        ->and($payload->signature())->toBe('226e2e78ebb61c3ba6e07c85b613f7d6')
        ->and($payload->interfaceVersion())->toBe('live');
});

it('decodes an envelope whose sub-fields arrive as real objects', function () {
    // Some deployments — and the resend-callback endpoint — send the nested
    // nodes already decoded rather than as JSON strings.
    $payload = SticpayCallbackPayload::fromInput([
        'callback' => [
            'type' => 'payment',
            'code' => 0,
            'message' => 'Success',
            'parameters' => sticpayEnvelopeParameters(),
        ],
    ]);

    expect($payload->code)->toBe(0)
        ->and($payload->orderNo())->toBe('DEP-1')
        ->and($payload->firstMessage())->toBe('Success');
});

it('decodes a half-encoded envelope', function () {
    $payload = SticpayCallbackPayload::fromInput([
        'callback' => json_encode([
            'type' => 'payment',
            'code' => 1,
            'message' => 'Cancel',
            'parameters' => sticpayEnvelopeParameters(),
        ]),
    ]);

    expect($payload->code)->toBe(1)
        ->and($payload->orderNo())->toBe('DEP-1');
});

it('accepts a body that is already a flat parameters array', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEnvelopeParameters());

    expect($payload->orderNo())->toBe('DEP-1')
        ->and($payload->code)->toBeNull()
        ->and($payload->parameters['fee'])->toBe('442');
});

it('normalises a bare message string into one entry', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEncodedBody([
        'type' => 'payment',
        'code' => 1,
        'message' => 'Cancel',
        'parameters' => sticpayEnvelopeParameters(),
    ]));

    expect($payload->messages)->toBe([['code' => 1, 'message' => 'Cancel']])
        ->and($payload->firstMessage())->toBe('Cancel');
});

it('normalises the failure envelope list of code/message objects', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEncodedBody([
        'type' => 'payment',
        'code' => 800,
        'message' => [
            ['code' => 804, 'message' => 'Parameter "order_time" must be a date'],
            ['code' => 803, 'message' => 'Parameter "order_amount" must be a float'],
        ],
        'parameters' => sticpayEnvelopeParameters(),
    ]));

    expect($payload->code)->toBe(800)
        ->and($payload->messages)->toHaveCount(2)
        ->and($payload->messages[0])->toBe(['code' => 804, 'message' => 'Parameter "order_time" must be a date'])
        ->and($payload->firstMessage())->toBe('Parameter "order_time" must be a date');
});

it('flattens to the parameters plus reserved envelope keys', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEncodedBody([
        'type' => 'processing',
        'code' => -1,
        'message' => '',
        'parameters' => sticpayEnvelopeParameters(),
    ]));

    $flat = $payload->toArray();

    expect($flat)->toHaveKeys(['order_no', 'order_amount', 'transaction_code', 'fee', 'sign'])
        ->and($flat['callback_type'])->toBe('processing')
        ->and($flat['callback_code'])->toBe(-1)
        ->and($flat['callback_message'])->toBe([])
        // Nothing nested survives — the queue, the provider and the adapter all
        // read this array directly.
        ->and($flat)->not->toHaveKey('callback')
        ->and($flat)->not->toHaveKey('parameters');
});

it('does not throw on a malformed callback string', function () {
    // A body that cannot be decoded must fail signature verification, not blow
    // up the request — otherwise garbage becomes a 500 instead of a 403.
    $payload = SticpayCallbackPayload::fromInput(['callback' => '{not json at all']);

    expect($payload->parameters)->toBe([])
        ->and($payload->orderNo())->toBeNull()
        ->and($payload->signature())->toBe('')
        ->and($payload->code)->toBeNull();
});

it('does not throw on an empty body', function () {
    $payload = SticpayCallbackPayload::fromInput([]);

    expect($payload->parameters)->toBe([])
        ->and($payload->orderNo())->toBeNull();
});

it('refuses to let a crafted payload forge the reserved envelope keys', function () {
    $payload = SticpayCallbackPayload::fromInput(sticpayEncodedBody([
        'type' => 'payment',
        'code' => 1,
        'message' => 'Cancel',
        'parameters' => sticpayEnvelopeParameters([
            'callback_code' => 0,
            'callback_type' => 'processing',
        ]),
    ]));

    // The envelope, not the parameters, decides these — a caller reading
    // toArray()['callback_code'] must see what Sticpay signed the envelope with.
    expect($payload->parameters)->not->toHaveKey('callback_code')
        ->and($payload->toArray()['callback_code'])->toBe(1)
        ->and($payload->toArray()['callback_type'])->toBe('payment');
});

it('reads order_id when a section 5 style payload uses it instead of order_no', function () {
    $payload = SticpayCallbackPayload::fromInput(['order_id' => 'DEP-9', 'transaction_code' => '1']);

    expect($payload->orderNo())->toBe('DEP-9');
});
