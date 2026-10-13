<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderProvider;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('resolves the bundled paycrossborder driver from a connection', function () {
    PayCrossBorderApi::configure();

    $provider = app(ConnectionRegistry::class)->get('paycrossborder');

    expect($provider)->toBeInstanceOf(PayCrossBorderProvider::class)
        ->and($provider)->toBeInstanceOf(SendsPayouts::class)
        ->and($provider->getName())->toBe('paycrossborder')
        ->and($provider->supports('payout'))->toBeTrue()
        ->and($provider->supports('charge'))->toBeFalse()
        ->and($provider->supports('webhook'))->toBeFalse();
});

it('refuses to construct without credentials or codes', function (string $missing) {
    $config = PayCrossBorderApi::CONFIG;
    unset($config[$missing]);

    expect(fn () => new PayCrossBorderProvider($config))
        ->toThrow(PaymentProcessingException::class, "{$missing} is missing");
})->with(['base_url', 'username', 'password', 'sender_code', 'customer_code']);

it('supports nothing on the deposit side', function () {
    $provider = new PayCrossBorderProvider(PayCrossBorderApi::CONFIG);

    expect(fn () => $provider->charge(['amount' => 10]))->toThrow(PaymentProcessingException::class, 'payouts only')
        ->and(fn () => $provider->refund('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->capture('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->authorize([]))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->void('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->parseWebhook([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->validatePaymentData([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->getPaymentStatus('x'))->toThrow(PaymentProcessingException::class)
        ->and($provider->retrieve('x'))->toBeNull()
        ->and($provider->verifyWebhookSignature([], 'sig'))->toBeFalse();
});

it('delegates payouts to the payout service', function () {
    PayCrossBorderApi::fake();

    $receipt = (new PayCrossBorderProvider(PayCrossBorderApi::CONFIG))->send(PayCrossBorderApi::request());

    expect($receipt->reference)->toBe('197930');
});

it('gives fakeConnection working defaults', function () {
    Cashier::fakeConnection('paycrossborder');

    expect(app(ConnectionRegistry::class)->get('paycrossborder'))->toBeInstanceOf(PayCrossBorderProvider::class);
});
