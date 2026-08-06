<?php

use Asciisd\CashierCore\Drivers\Payport\PayportAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new PayportAdapter;
});

it('maps polled invoice statuses', function (mixed $payport, PaymentStatus $expected) {
    expect($this->adapter->mapStatus($payport))->toBe($expected);
})->with([
    'paid' => [1, PaymentStatus::Succeeded],
    'cancelled' => [-1, PaymentStatus::Canceled],
    // 0 is "invoice created" when polling — the customer has not paid yet.
    'created' => [0, PaymentStatus::Pending],
    'user confirmed' => [2, PaymentStatus::Processing],
    'trader confirmed' => [3, PaymentStatus::Processing],
    'unknown' => ['weird', PaymentStatus::Pending],
]);

it('maps callback statuses, treating both documented cancel codes as cancelled', function (mixed $payport, PaymentStatus $expected) {
    expect($this->adapter->mapCallbackStatus($payport))->toBe($expected);
})->with([
    'paid' => [1, PaymentStatus::Succeeded],
    'cancelled' => [-1, PaymentStatus::Canceled],
    // Callbacks fire only on final statuses, so 0 cannot mean "created" here.
    'cancelled (API5 prose)' => [0, PaymentStatus::Canceled],
    'trader confirmed' => [3, PaymentStatus::Processing],
]);

it('reads the invoice amount from amount_currency, not the USDT leg', function () {
    // A freshly created invoice reports amount=0 until Payport fixes a rate;
    // the figure the customer owes is in amount_currency.
    $result = $this->adapter->fromProviderResponse([
        'url' => 'https://payport.test/payment/invoice/post/checkout/11244408?signature=abc',
        'order_id' => 'DEP-1',
        'invoice_id' => 11244408,
        'merchant_id' => 1169,
        'amount' => 0,
        'amount_currency' => 50,
        'currency' => 'USD',
    ]);

    expect($result->transactionId)->toBe('DEP-1')
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->amount)->toBe(50)
        ->and($result->currency)->toBe('USD')
        ->and($result->getRedirectUrl())->toBe('https://payport.test/payment/invoice/post/checkout/11244408?signature=abc')
        ->and($result->metadata['payport_invoice_id'])->toBe(11244408);
});

it('transforms a paid callback into a succeeded update', function () {
    $update = $this->adapter->fromWebhook([
        'invoice_id' => '7240',
        'merchant_id' => '1169',
        'order_id' => 'DEP-1',
        'amount' => '100',
        'amount_currency' => '100',
        'currency' => 'USD',
        'merchant_amount' => '74',
        'status' => '1',
        'account_info' => '455691******7441',
        'fiat_currency' => 'SAR',
        'fiat_amount' => '375',
        'payment_system_type' => 'card_number',
    ]);

    expect($update->status)->toBe(PaymentStatus::Succeeded)
        ->and($update->amount)->toBe(100.0)
        ->and($update->currency)->toBe('USD')
        ->and($update->errorMessage)->toBeNull()
        ->and($update->metadata['payport_fiat_currency'])->toBe('SAR')
        ->and($update->metadata['payport_merchant_amount'])->toBe('74')
        ->and($update->paymentMethodSnapshot?->type)->toBe(PaymentMethodType::CreditCard)
        ->and($update->paymentMethodSnapshot?->lastFour)->toBe('7441');
});

it('carries the cancellation reason on a cancelled callback', function () {
    $update = $this->adapter->fromWebhook([
        'invoice_id' => '432338',
        'order_id' => 'DEP-2',
        'amount_currency' => '2000',
        'currency' => 'USD',
        'status' => '-1',
        'cancellation_reason' => 'INVALID_DETAILS',
        'payment_system_type' => 'card_number',
    ]);

    expect($update->status)->toBe(PaymentStatus::Canceled)
        ->and($update->errorCode)->toBe('INVALID_DETAILS')
        ->and($update->errorMessage)->toBe('INVALID_DETAILS');
});

it('classifies the local rails behind the SAR and EGP processors', function (string $systemType, PaymentMethodType $expected) {
    $update = $this->adapter->fromWebhook([
        'order_id' => 'DEP-1',
        'status' => '1',
        'payment_system_type' => $systemType,
        'account_info' => 'SA0380000000608010167519',
    ]);

    expect($update->paymentMethodSnapshot?->type)->toBe($expected);
})->with([
    'SAR IBAN' => ['iban', PaymentMethodType::BankTransfer],
    'SAR SWIFT' => ['swift', PaymentMethodType::BankTransfer],
    'EGP wallet' => ['by_mobile', PaymentMethodType::DigitalWallet],
    'EGP InstaPay QR' => ['qr_image', PaymentMethodType::DigitalWallet],
]);

it('does not invent a card tail for non-card rails', function () {
    $update = $this->adapter->fromWebhook([
        'order_id' => 'DEP-1',
        'status' => '1',
        'payment_system_type' => 'by_mobile',
        'account_info' => '01012345678',
    ]);

    expect($update->paymentMethodSnapshot?->lastFour)->toBeNull()
        ->and($update->paymentMethodSnapshot?->displayName)->toBe('By Mobile');
});

it('returns no snapshot when the callback names no rail', function () {
    $update = $this->adapter->fromWebhook(['order_id' => 'DEP-1', 'status' => '1']);

    expect($update->paymentMethodSnapshot)->toBeNull();
});

it('reads the invoice node when transforming a status lookup', function () {
    $result = $this->adapter->fromProviderPayload('DEP-1', [
        'status' => 1,
        'message' => 'Payment paid',
        'merchant_amount' => 82,
        'invoice' => [
            'invoice_id' => 11244408,
            'merchant_id' => 1169,
            'order_id' => 'DEP-1',
            'amount' => 0,
            'amount_currency' => 50,
            'currency' => 'USD',
        ],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->amount)->toBe(50)
        ->and($result->currency)->toBe('USD')
        ->and($result->message)->toBe('Payment paid');
});
