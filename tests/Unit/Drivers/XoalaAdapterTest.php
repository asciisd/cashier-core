<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

function xoalaCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'paymentId' => '18608029',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'paymentBrand' => 'VISA',
        'paymentMode' => 'CC',
        'amount' => '100.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'remark' => 'Approved',
        'checksum' => 'irrelevant-here',
        'result' => ['code' => '00001', 'description' => 'Transaction succeeded'],
        'card' => ['bin' => '444433', 'last4Digits' => '1111'],
        'timestamp' => '2023-02-09 19:42:23',
    ], $overrides);
}

it('maps every documented long status', function (string $xoala, PaymentStatus $expected) {
    expect((new XoalaAdapter)->mapStatus($xoala))->toBe($expected);
})->with([
    ['capturesuccess', PaymentStatus::Succeeded],
    ['settled', PaymentStatus::Succeeded],
    ['authsuccessful', PaymentStatus::Succeeded],
    ['begun', PaymentStatus::Pending],
    ['authstarted', PaymentStatus::Pending],
    ['capturestarted', PaymentStatus::Pending],
    ['cancelstarted', PaymentStatus::Pending],
    ['markedforreversal', PaymentStatus::Pending],
    ['authfailed', PaymentStatus::Failed],
    ['capturefailed', PaymentStatus::Failed],
    ['failed', PaymentStatus::Failed],
    ['cancelled', PaymentStatus::Canceled],
    ['authcancelled', PaymentStatus::Canceled],
    ['reversed', PaymentStatus::Canceled],
    ['chargeback', PaymentStatus::Canceled],
]);

it('maps the short statuses', function (string $xoala, PaymentStatus $expected) {
    expect((new XoalaAdapter)->mapShortStatus($xoala))->toBe($expected);
})->with([
    ['Y', PaymentStatus::Succeeded],
    ['N', PaymentStatus::Failed],
    ['P', PaymentStatus::Pending],
    ['3D', PaymentStatus::Pending],
    ['C', PaymentStatus::Canceled],
]);

it('is case-insensitive about the long status', function () {
    expect((new XoalaAdapter)->mapStatus('CaptureSuccess'))->toBe(PaymentStatus::Succeeded);
});

it('falls back to the short status when the long one is absent', function () {
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['status' => '', 'transactionStatus' => 'N'])
    );

    expect($update->status)->toBe(PaymentStatus::Failed);
});

it('falls back to the short status when the long one is unrecognised', function () {
    // A status Xoala adds later must not silently read as Pending when the
    // payload already states the outcome plainly.
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['status' => 'somethingnew', 'transactionStatus' => 'Y'])
    );

    expect($update->status)->toBe(PaymentStatus::Succeeded);
});

it('builds a webhook update from a successful callback', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload());

    expect($update->status)->toBe(PaymentStatus::Succeeded)
        ->and($update->amount)->toBe(100.0)
        ->and($update->currency)->toBe('USD')
        ->and($update->metadata['xoala_payment_id'])->toBe('18608029')
        ->and($update->paymentMethodSnapshot?->lastFour)->toBe('1111')
        ->and($update->paymentMethodSnapshot?->type)->toBe(PaymentMethodType::CreditCard);
});

it('keeps the decimals of a callback amount', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload(['amount' => '100000.65']));

    expect($update->amount)->toBe(100000.65);
});

it('carries the result code and description onto a failed update', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload([
        'status' => 'authfailed',
        'transactionStatus' => 'N',
        'result' => ['code' => '10001', 'description' => 'Transaction failed'],
    ]));

    expect($update->status)->toBe(PaymentStatus::Failed)
        ->and($update->errorCode)->toBe('10001')
        ->and($update->errorMessage)->toBe('Transaction failed');
});

it('leaves error fields unset on a success', function () {
    $update = (new XoalaAdapter)->fromWebhook(xoalaCallbackPayload());

    expect($update->errorCode)->toBeNull()
        ->and($update->errorMessage)->toBeNull();
});

it('reports no payment method when the callback describes none', function () {
    $update = (new XoalaAdapter)->fromWebhook(
        xoalaCallbackPayload(['paymentMode' => '', 'paymentBrand' => '', 'card' => []])
    );

    expect($update->paymentMethodSnapshot)->toBeNull();
});

it('turns a charge context into a pending result carrying the bridge url', function () {
    $result = (new XoalaAdapter)->fromProviderResponse([
        'merchant_transaction_id' => 'DEP-1',
        'amount' => '50.00',
        'currency' => 'USD',
        'redirect_url' => 'https://app.test/cashier/xoala/checkout/DEP-1?signature=abc',
        'fields' => ['email' => 'john@example.com'],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(50)
        ->and($result->currency)->toBe('USD')
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toBe('https://app.test/cashier/xoala/checkout/DEP-1?signature=abc')
        ->and($result->metadata['xoala_fields'])->toBe(['email' => 'john@example.com']);
});

it('builds a result from an inquiry response', function () {
    $result = (new XoalaAdapter)->fromProviderPayload('DEP-1', [
        'paymentId' => '54289',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
        'amount' => '1.00',
        'currency' => 'USD',
        'merchantTransactionId' => 'DEP-1',
        'result' => ['code' => '00026', 'description' => 'Your record found successfully'],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(1)
        ->and($result->metadata['xoala_payment_id'])->toBe('54289');
});

it('reports an unpaid inquiry as pending and unsuccessful', function () {
    $result = (new XoalaAdapter)->fromProviderPayload('DEP-1', [
        'paymentId' => '54289',
        'status' => 'begun',
        'amount' => '1.00',
        'currency' => 'USD',
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe(PaymentStatus::Pending);
});

it('names the provider', function () {
    expect((new XoalaAdapter)->getProviderName())->toBe('xoala');
});
