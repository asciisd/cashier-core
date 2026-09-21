<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function digibloxConfig(array $overrides = []): array
{
    return array_merge([
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'crypto_currency' => 'USDT',
        'network' => 'TRON',
    ], $overrides);
}

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['paymentLink' => 'https://widget.digiblox.io/widget/auth?paymentId=R1N0'], 201,
        ),
    ]);
});

it('refuses to construct without credentials', function () {
    expect(fn () => new DigibloxProvider(['base_url' => 'https://digiblox.test']))
        ->toThrow(PaymentProcessingException::class, 'not configured');
});

it('charges by returning the payment link as a redirect', function () {
    $result = (new DigibloxProvider(digibloxConfig()))
        ->charge(['amount' => 150, 'currency' => 'USD', 'external_id' => 'DEP-1']);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toBe('DEP-1')
        ->and($result->requiresAction())->toBeTrue()
        ->and($result->getRedirectUrl())->toBe('https://widget.digiblox.io/widget/auth?paymentId=R1N0');
});

it('mints an external id when the caller does not supply one', function () {
    $result = (new DigibloxProvider(digibloxConfig()))->charge(['amount' => 10, 'currency' => 'USD']);

    expect($result->transactionId)->toStartWith('DEP-');
});

it('reports the status of the most recent deposit row', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response([
            'totalItems' => 1,
            'result' => [[
                'external_transaction_id' => 'DEP-1',
                'status' => 'CONFIRMED',
                'amount' => '149.700000',
                'system_fee' => '0.300000',
                'currency' => 'USDT',
                'tx_hash' => '0xabc',
            ]],
        ]),
    ]);

    $provider = new DigibloxProvider(digibloxConfig());

    expect($provider->getPaymentStatus('DEP-1'))->toBe(PaymentStatus::Succeeded->value)
        ->and($provider->retrieve('DEP-1')->status)->toBe(PaymentStatus::Succeeded);
});

it('returns null from retrieve when no deposit exists yet', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
            ['totalItems' => 0, 'result' => []],
        ),
    ]);

    expect((new DigibloxProvider(digibloxConfig()))->retrieve('DEP-nothing'))->toBeNull();
});

it('throws on every operation Digiblox has no endpoint for', function () {
    $provider = new DigibloxProvider(digibloxConfig());

    expect(fn () => $provider->refund('DEP-1'))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->capture('DEP-1'))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->authorize([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->void('DEP-1'))->toThrow(PaymentProcessingException::class);
});

it('advertises only what it supports', function () {
    $provider = new DigibloxProvider(digibloxConfig());

    expect($provider->getName())->toBe('digiblox')
        ->and($provider->supports('charge'))->toBeTrue()
        ->and($provider->supports('webhook'))->toBeTrue()
        ->and($provider->supports('refund'))->toBeFalse();
});
