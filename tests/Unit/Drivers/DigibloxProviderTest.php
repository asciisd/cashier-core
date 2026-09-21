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

it('converts a transport failure into a PaymentProcessingException', function () {
    // A connection timeout is a ConnectionException — a sibling of
    // RequestException, not a subclass — so catching RequestException alone
    // lets it escape as an uncaught 500 with nothing logged.
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('cURL error 28: timed out'));

    expect(fn () => (new DigibloxProvider(digibloxConfig()))->charge(['amount' => 100, 'currency' => 'USD']))
        ->toThrow(PaymentProcessingException::class, 'could not be created');
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

it('reports Succeeded when a CONFIRMED row is not first, never trusting result[0]', function () {
    // searchDeposits sorts newest-created first, not newest-settled. Here the
    // newest row (index 0) is Digiblox's own internal settlement row, still
    // SENT_TOKEN, sitting in front of the customer's already-CONFIRMED
    // payment. Reporting rows[0] would call this fully paid order Pending.
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response([
            'totalItems' => 2,
            'result' => [
                [
                    'external_transaction_id' => 'DEP-1',
                    'status' => 'SENT_TOKEN',
                    'amount' => '5.000000',
                    'system_fee' => '0.010000',
                    'tx_hash' => '0xnewest',
                ],
                [
                    'external_transaction_id' => 'DEP-1',
                    'status' => 'CONFIRMED',
                    'amount' => '149.700000',
                    'system_fee' => '0.300000',
                    'tx_hash' => '0xconfirmed',
                ],
            ],
        ]),
    ]);

    $result = (new DigibloxProvider(digibloxConfig()))->retrieve('DEP-1');

    expect($result->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->metadata['tx_hash'])->toBe('0xconfirmed')
        ->and($result->metadata['deposit_rows'])->toBe(2)
        ->and($result->processorResponse)->toHaveCount(2);
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

    // BadMethodCallException, not PaymentProcessingException: calling these is a
    // programming error, not a payment that failed. Matches Heropayment, Xoala
    // and Payport.
    expect(fn () => $provider->refund('DEP-1'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->capture('DEP-1'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->authorize([]))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->void('DEP-1'))->toThrow(BadMethodCallException::class);
});

it('advertises only what it supports', function () {
    $provider = new DigibloxProvider(digibloxConfig());

    expect($provider->getName())->toBe('digiblox')
        ->and($provider->supports('charge'))->toBeTrue()
        ->and($provider->supports('webhook'))->toBeTrue()
        ->and($provider->supports('refund'))->toBeFalse();
});

it('resolves through the connection registry', function () {
    config()->set('cashier-core.connections.digiblox', array_merge(
        digibloxConfig(),
        ['driver' => 'digiblox'],
    ));

    $provider = app(Asciisd\CashierCore\Connections\ConnectionRegistry::class)->get('digiblox');

    expect($provider)->toBeInstanceOf(DigibloxProvider::class)
        ->and($provider->getName())->toBe('digiblox');
});
