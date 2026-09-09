<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaProvider;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    /*
     * charge() returns a signed link to the bridge route, so that route has to
     * exist for the URL to be signable. Task 5 registers it for real in the
     * service provider; until then a stub stands in, and the guard makes this
     * a no-op once the real one is registered.
     */
    if (! Route::has('cashier.checkout.xoala')) {
        Route::get('/cashier/xoala/checkout/{transaction}', fn () => '')
            ->name('cashier.checkout.xoala');

        // A fluently-set name is not in the router's name lookup table until
        // it is refreshed — normally triggered by dispatching a real HTTP
        // request. Nothing in this test suite does that, so Route::has()
        // (and route()) would see this route as unnamed without this. See
        // MyfatoorahProviderTest for the same workaround.
        Route::getRoutes()->refreshNameLookups();
    }
});

function xoalaConfig(array $overrides = []): array
{
    return array_merge([
        'driver' => 'xoala',
        'base_url' => 'https://xoala.test',
        'member_id' => '11344',
        'secure_key' => 'secure-key',
        'totype' => 'PartnerName',
        'redirect_url' => 'https://app.test/payment/success',
        'webhook_url' => 'https://app.test/api/webhooks/xoala',
    ], $overrides);
}

function xoalaProvider(array $overrides = []): XoalaProvider
{
    return new XoalaProvider(xoalaConfig($overrides));
}

it('refuses to construct without credentials', function () {
    new XoalaProvider(['base_url' => 'https://xoala.test']);
})->throws(PaymentProcessingException::class);

it('names itself and reports its features', function () {
    $provider = xoalaProvider();

    expect($provider->getName())->toBe('xoala')
        ->and($provider->supports('charge'))->toBeTrue()
        ->and($provider->supports('webhook'))->toBeTrue()
        ->and($provider->supports('refund'))->toBeFalse();
});

it('makes no HTTP call when charging', function () {
    Http::fake();

    xoalaProvider()->charge(['amount' => 50.0, 'order_id' => 'DEP-1']);

    Http::assertNothingSent();
});

it('returns a pending result pointing at the signed bridge route', function () {
    $result = xoalaProvider()->charge(['amount' => 50.0, 'order_id' => 'DEP-1']);

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->amount)->toBe(50)
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toContain('/xoala/checkout/DEP-1')
        ->and($result->getRedirectUrl())->toContain('signature=');
});

it('mints an id when the caller supplies none', function () {
    $result = xoalaProvider()->charge(['amount' => 10.0]);

    expect($result->transactionId)->toStartWith('DEP-');
});

it('rejects a charge with no usable amount', function () {
    xoalaProvider()->charge(['amount' => 0]);
})->throws(Illuminate\Validation\ValidationException::class);

it('declares the connection currency before the charge', function () {
    $customer = Mockery::mock(Asciisd\CashierCore\Contracts\CustomerContract::class);

    $prepared = xoalaProvider(['currency' => 'sar'])
        ->prepareChargeData($customer, 'xoala', ['amount' => 50.0, 'currency' => 'USD']);

    // Upper-cased, so the value the engine compares against the account
    // currency is the value Xoala is sent.
    expect($prepared['currency'])->toBe('SAR');
});

it('leaves the currency alone when the connection configures none', function () {
    $customer = Mockery::mock(Asciisd\CashierCore\Contracts\CustomerContract::class);

    $prepared = xoalaProvider()
        ->prepareChargeData($customer, 'xoala', ['amount' => 50.0, 'currency' => 'EUR']);

    expect($prepared['currency'])->toBe('EUR');
});

it('carries only the optional extras into metadata', function () {
    $result = xoalaProvider()->charge([
        'amount' => 50.0,
        'order_id' => 'DEP-1',
        'metadata' => ['user_email' => 'john@example.com', 'user_name' => 'John Doe'],
    ]);

    $fields = $result->metadata['xoala_fields'];

    expect($fields['email'])->toBe('john@example.com')
        ->and($fields)->not->toHaveKey('amount')
        ->and($fields)->not->toHaveKey('checksum')
        ->and($fields)->not->toHaveKey('merchantTransactionId');
});

it('builds a signed checkout form from a stored transaction', function () {
    $transaction = new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 50.0,
        'currency' => 'USD',
        'metadata' => ['xoala_fields' => ['email' => 'john@example.com']],
    ]);

    $form = xoalaProvider()->checkoutForm($transaction);

    expect($form['action'])->toBe('https://xoala.test/transaction/Checkout')
        ->and($form['fields']['memberId'])->toBe('11344')
        ->and($form['fields']['totype'])->toBe('PartnerName')
        ->and($form['fields']['amount'])->toBe('50.00')
        ->and($form['fields']['currency'])->toBe('USD')
        ->and($form['fields']['merchantTransactionId'])->toBe('DEP-1')
        ->and($form['fields']['transactionType'])->toBe('DB')
        ->and($form['fields']['email'])->toBe('john@example.com')
        ->and($form['fields']['checksum'])->toBe(
            (new XoalaSignatureService('11344', 'secure-key'))->forCheckout(
                'PartnerName',
                '50.00',
                'DEP-1',
                'https://app.test/payment/success',
            )
        );
});

it('signs the charge leg, not the account amount, on a converted deposit', function () {
    $transaction = new Transaction([
        'provider' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'amount' => 100.0,
        'currency' => 'USD',
        'charge_amount' => 375.0,
        'charge_currency' => 'SAR',
    ]);

    $form = xoalaProvider()->checkoutForm($transaction);

    expect($form['fields']['amount'])->toBe('375.00')
        ->and($form['fields']['currency'])->toBe('SAR');
});

it('verifies a webhook signature through the signature service', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => 'DEP-1',
        'amount' => '50.00',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', 'DEP-1', '50.00', 'Y');

    expect(xoalaProvider()->verifyWebhookSignature($payload, $payload['checksum']))->toBeTrue()
        ->and(xoalaProvider(['secure_key' => 'other'])->verifyWebhookSignature($payload, $payload['checksum']))->toBeFalse();
});

it('correlates a webhook on our own merchant transaction id', function () {
    expect(xoalaProvider()->extractWebhookTransactionId(['merchantTransactionId' => 'DEP-1']))->toBe('DEP-1')
        ->and(xoalaProvider()->extractWebhookTransactionId(['merchantTransactionId' => '']))->toBeNull()
        ->and(xoalaProvider()->extractWebhookTransactionId([]))->toBeNull();
});

it('retrieves a transaction through the inquiry endpoint', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response([
            'paymentId' => '54289',
            'status' => 'capturesuccess',
            'transactionStatus' => 'Y',
            'amount' => '50.00',
            'currency' => 'USD',
        ]),
    ]);

    $result = xoalaProvider()->retrieve('DEP-1');

    expect($result?->status)->toBe(PaymentStatus::Succeeded)
        ->and($result?->transactionId)->toBe('DEP-1');
});

it('returns null from retrieve when the lookup fails', function () {
    Http::fake(['*' => Http::response('down', 502)]);

    expect(xoalaProvider()->retrieve('DEP-1'))->toBeNull();
});

it('reports unknown when the status cannot be read', function () {
    Http::fake(['*' => Http::response('down', 502)]);

    expect(xoalaProvider()->getPaymentStatus('DEP-1'))->toBe('unknown');
});

it('throws for the operations it does not implement', function (string $method, array $args) {
    xoalaProvider()->{$method}(...$args);
})->throws(BadMethodCallException::class)->with([
    ['refund', ['DEP-1', 1.0]],
    ['capture', ['DEP-1', 1.0]],
    ['authorize', [['amount' => 1.0]]],
    ['void', ['DEP-1']],
]);
