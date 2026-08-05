<?php

use Asciisd\CashierCore\Drivers\Sticpay\SticpayAdapter;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

function sticpayFlatCallback(array $overrides = []): array
{
    return array_merge([
        'sign' => '226e2e78ebb61c3ba6e07c85b613f7d6',
        'fee_currency' => 'USD',
        'fee' => '4.42',
        'transaction_code' => '232857',
        'transaction_time' => '2018-03-15 16:22:45',
        'merchant_email' => 'merchant@sticpay.com',
        'order_no' => 'DEP-1',
        'order_time' => '2018-03-15 07:22:21',
        'order_amount' => '100.00',
        'order_currency' => 'USD',
        'interface_version' => 'live',
        'callback_type' => 'processing',
        'callback_code' => -1,
        'callback_message' => [],
    ], $overrides);
}

it('turns a pay response into a pending result carrying the hosted page url', function () {
    $result = (new SticpayAdapter)->fromProviderResponse([
        'link' => 'https://pay.sticpay.com/1.1/pay/consume_token/aee5880d',
        'link_expires_at' => '2026-08-04T12:05:00+00:00',
        'order_no' => 'DEP-1',
        'order_amount' => '125.03',
        'order_currency' => 'USD',
        'response' => ['success' => true],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->currency)->toBe('USD')
        ->and($result->getRedirectUrl())->toBe('https://pay.sticpay.com/1.1/pay/consume_token/aee5880d')
        ->and($result->requiresAction())->toBeTrue()
        // The link dies after five minutes; anything that ever offers it as a
        // resumable payment has to check this rather than re-serve a dead token.
        ->and($result->metadata['sticpay_link_expires_at'])->toBe('2026-08-04T12:05:00+00:00');
});

it('maps the transaction detail vocabulary', function (string $status, PaymentStatus $expected) {
    expect((new SticpayAdapter)->mapStatus($status))->toBe($expected);
})->with([
    'approved' => ['approved', PaymentStatus::Succeeded],
    'rejected' => ['rejected', PaymentStatus::Failed],
    // Not Pending: Sticpay has taken the money and is deciding, which is a
    // different thing from a payment the customer has not started.
    'pending' => ['pending', PaymentStatus::Processing],
    'mixed case' => ['Approved', PaymentStatus::Succeeded],
    'unknown' => ['something-new', PaymentStatus::Pending],
]);

it('maps the callback envelope codes', function (mixed $code, PaymentStatus $expected) {
    expect((new SticpayAdapter)->mapCallbackStatus($code))->toBe($expected);
})->with([
    'transaction callback' => [-1, PaymentStatus::Succeeded],
    'pay success' => [0, PaymentStatus::Succeeded],
    'user cancelled' => [1, PaymentStatus::Canceled],
    'request expired' => [100, PaymentStatus::Canceled],
    'customer not found' => [200, PaymentStatus::Failed],
    'below minimum' => [410, PaymentStatus::Failed],
    'insufficient balance' => [700, PaymentStatus::Failed],
    'invalid parameter' => [800, PaymentStatus::Failed],
    'invalid signature' => [809, PaymentStatus::Failed],
    'order already used' => [1300, PaymentStatus::Failed],
    'email mismatch' => [1500, PaymentStatus::Failed],
    'numeric string' => ['-1', PaymentStatus::Succeeded],
    'absent' => [null, PaymentStatus::Pending],
    'unknown code' => [9999, PaymentStatus::Pending],
]);

it('extracts the reconciliation metadata from a callback', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback());

    expect($update->status)->toBe(PaymentStatus::Succeeded)
        ->and($update->amount)->toBe(100)
        ->and($update->currency)->toBe('USD')
        ->and($update->metadata['sticpay_transaction_code'])->toBe('232857')
        ->and($update->metadata['sticpay_transaction_time'])->toBe('2018-03-15 16:22:45')
        ->and($update->metadata['sticpay_fee'])->toBe('4.42')
        ->and($update->metadata['sticpay_interface_version'])->toBe('live')
        // What Sticpay credits us after its fee, for the fee-drift check. Never
        // written to transactions.amount, which stays what the customer owed.
        ->and($update->metadata['sticpay_merchant_amount'])->toBe(95.58)
        ->and($update->errorCode)->toBeNull()
        ->and($update->errorMessage)->toBeNull();
});

/*
 * Sticpay settles into the merchant's default wallet currency when it holds
 * none for the requested one. Subtracting a fee across two currencies would
 * report a settlement figure that is simply wrong, so it is withheld and an
 * admin is flagged instead.
 */
it('withholds the settled amount and flags for attention when the fee is in another currency', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback([
        'order_currency' => 'USD',
        'fee_currency' => 'EUR',
    ]));

    expect($update->metadata)->not->toHaveKey('sticpay_merchant_amount')
        ->and($update->metadata['requires_attention'])->toBeTrue();
});

it('records an error code and message for a failed callback', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback([
        'callback_code' => 800,
        'callback_message' => [['code' => 803, 'message' => 'Parameter "order_amount" must be a float']],
    ]));

    expect($update->status)->toBe(PaymentStatus::Failed)
        ->and($update->errorCode)->toBe('800')
        ->and($update->errorMessage)->toBe('Parameter "order_amount" must be a float');
});

it('falls back to a static label when a failure carries no message', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback([
        'callback_code' => 700,
        'callback_message' => [],
    ]));

    expect($update->status)->toBe(PaymentStatus::Failed)
        ->and($update->errorMessage)->toBe('The customer’s Sticpay balance is insufficient.');
});

it('reports a cancellation without inventing an error message', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback([
        'callback_code' => 1,
        'callback_message' => [['code' => 1, 'message' => 'Cancel']],
    ]));

    expect($update->status)->toBe(PaymentStatus::Canceled)
        ->and($update->errorCode)->toBe('1')
        ->and($update->errorMessage)->toBe('Cancel');
});

it('builds an update for a status resolved outside the envelope', function () {
    $adapter = new SticpayAdapter;

    // The confirmation path: the callback code says nothing, the Detail API
    // says "rejected".
    $update = $adapter->toUpdate(sticpayFlatCallback(), PaymentStatus::Failed);

    expect($update->status)->toBe(PaymentStatus::Failed)
        ->and($update->errorCode)->toBe('-1')
        ->and($update->metadata['sticpay_transaction_code'])->toBe('232857');
});

it('merges extra metadata into a held update', function () {
    $update = (new SticpayAdapter)->toUpdate(
        sticpayFlatCallback(),
        PaymentStatus::Processing,
        ['requires_attention' => true],
    );

    expect($update->status)->toBe(PaymentStatus::Processing)
        ->and($update->metadata['requires_attention'])->toBeTrue()
        // Held, not failed — nothing has gone wrong with the payment itself.
        ->and($update->errorCode)->toBeNull();
});

it('reports the instrument as a wallet and keeps the payer email out of it', function () {
    $update = (new SticpayAdapter)->fromWebhook(sticpayFlatCallback([
        'customer_email' => 'payer@sticpay.com',
    ]));

    $snapshot = $update->paymentMethodSnapshot;

    expect($snapshot->type)->toBe(PaymentMethodType::DigitalWallet)
        ->and($snapshot->brand)->toBe(PaymentMethodBrand::Other)
        ->and($snapshot->lastFour)->toBeNull()
        // The display name is rendered in the member UI and in Nova; another
        // person's wallet address does not belong there.
        ->and($snapshot->displayName)->toBe('Sticpay')
        ->and($snapshot->displayName)->not->toContain('payer@sticpay.com');
});

it('reports no instrument when the payload carries no wallet context', function () {
    $update = (new SticpayAdapter)->fromWebhook([
        'order_no' => 'DEP-1',
        'callback_code' => 800,
    ]);

    // A validation-failure envelope names no wallet; overwriting the method the
    // deposit was created with would lose the user's own selection.
    expect($update->paymentMethodSnapshot)->toBeNull();
});

it('turns a transaction detail response into a result', function () {
    $result = (new SticpayAdapter)->fromProviderPayload('DEP-1', [
        'success' => true,
        'transaction_code' => '232857',
        'transaction_time' => '2018-12-31 14:00:00',
        'order_id' => 'DEP-1',
        'from_currency' => 'USD',
        'from_amount' => '100.00',
        'customer_email' => 'payer@sticpay.com',
        'fee' => '4.42',
        'fee_currency' => 'USD',
        'to_currency' => 'EUR',
        'to_amount' => '88.10',
        'status' => 'approved',
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->amount)->toBe(100)
        ->and($result->currency)->toBe('USD')
        // The settlement leg is recorded but never confused with what the
        // customer was charged.
        ->and($result->metadata['sticpay_to_currency'])->toBe('EUR')
        ->and($result->metadata['sticpay_to_amount'])->toBe('88.10');
});

it('names itself sticpay', function () {
    expect((new SticpayAdapter)->getProviderName())->toBe('sticpay');
});
