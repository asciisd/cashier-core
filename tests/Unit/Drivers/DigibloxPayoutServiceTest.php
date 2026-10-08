<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\Digiblox\DigibloxPayoutService;
use Asciisd\CashierCore\Drivers\Digiblox\DigibloxProvider;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function dbxServiceConfig(array $overrides = []): array
{
    return array_merge([
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'payout_assets' => [
            'usdttrc20' => ['asset' => 'USDT', 'network' => 'TRON', 'peg' => 'USD'],
        ],
    ], $overrides);
}

function dbxRequest(array $overrides = []): PayoutRequest
{
    return new PayoutRequest(...array_merge([
        'externalOrderId' => 'WD-01TEST',
        'customerId' => '70001',
        'amount' => '100.00',
        'currency' => 'USD',
        'payoutCurrency' => 'usdttrc20',
        'payoutAddress' => 'TXyzCustomer',
    ], $overrides));
}

/** @param array<string, mixed> $stubs extra URL => response stubs */
function dbxFake(array $stubs = []): void
{
    Http::fake(array_merge([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
    ], $stubs));
}

function dbxStatusResponse(string $status, array $extra = []): array
{
    return ['api_message' => 'TRANSFER_GET_SHOW_SUCCESS', 'api_data' => ['transfer' => array_merge([
        'id' => 'Qk1Z', 'status' => $status, 'amount' => '100.000000', 'currency' => 'USDT',
        'tx_hash' => null, 'user_note' => 'WD-01TEST',
    ], $extra)]];
}

beforeEach(fn () => Cache::flush());

it('passes preflight for a mapped USD-pegged asset without calling Digiblox', function () {
    Http::fake();

    $preflight = (new DigibloxPayoutService(dbxServiceConfig()))->preflight(dbxRequest());

    expect($preflight->ok)->toBeTrue()->and($preflight->balance)->toBeNull();
    Http::assertNothingSent();
});

it('refuses preflight for an unmapped ticker, a non-matching peg, or a cap breach', function (array $config, array $request, string $message) {
    Http::fake();

    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig($config)))->preflight(dbxRequest($request)))
        ->toThrow(PaymentProcessingException::class, $message);

    Http::assertNothingSent();
})->with([
    'unmapped ticker' => [[], ['payoutCurrency' => 'btc'], 'no payout asset configured for btc'],
    'peg mismatch' => [[], ['currency' => 'EUR'], 'EUR'],
    'over cap' => [['withdrawal_max_amount' => '50'], [], 'exceeds'],
]);

it('sends the mapped asset and network with the order id as the note', function () {
    dbxFake(['https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => Http::response(['id' => 'Qk1Z', 'status' => 'QUEUED'], 202)]);

    $receipt = (new DigibloxPayoutService(dbxServiceConfig()))->send(dbxRequest());

    expect($receipt->reference)->toBe('Qk1Z')
        ->and($receipt->state)->toBe(PayoutState::Sent)
        ->and($receipt->rawStatus)->toBe('QUEUED');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/transfers/centralized')
        && $r['asset'] === 'USDT' && $r['network'] === 'TRON'
        && $r['amount'] === '100.00' && $r['toAddress'] === 'TXyzCustomer'
        && $r['user_note'] === 'WD-01TEST');
});

it('propagates the typed send exceptions', function (int $status, string $exception) {
    dbxFake(['https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => Http::response(['message' => 'x'], $status)]);

    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig()))->send(dbxRequest()))->toThrow($exception);
})->with([
    '400' => [400, PayoutRejectedException::class],
    '500' => [500, PayoutOutcomeUnknownException::class],
]);

it('never returns null from a lookup without a reference', function () {
    Http::fake();

    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig()))->lookup('WD-01TEST'))
        ->toThrow(PayoutOutcomeUnknownException::class, 'Link the Digiblox transfer id');

    Http::assertNothingSent();
});

it('maps transfer statuses to payout states', function (string $raw, PayoutState $state) {
    dbxFake(['https://digiblox.test/gateway/api/v1/transfers/*' => Http::response(dbxStatusResponse($raw))]);

    $receipt = (new DigibloxPayoutService(dbxServiceConfig()))->lookup('WD-01TEST', 'Qk1Z');

    expect($receipt->state)->toBe($state)->and($receipt->reference)->toBe('Qk1Z')->and($receipt->rawStatus)->toBe($raw);
})->with([
    'confirmed' => ['CONFIRMED', PayoutState::Paid],
    'failed' => ['FAILED', PayoutState::Failed],
    'rejected' => ['REJECTED', PayoutState::Failed],
    'dropped' => ['DROPPED', PayoutState::Failed],
    'expired' => ['EXPIRED', PayoutState::Failed],
    'finalize' => ['FINALIZE', PayoutState::Sent],
    'queued' => ['QUEUED', PayoutState::Sent],
    'unlisted' => ['SOMETHING_NEW', PayoutState::Sent],
]);

it('carries the on-chain hash as outcomeHash and the error on failure', function () {
    dbxFake(['https://digiblox.test/gateway/api/v1/transfers/*' => Http::sequence()
        ->push(dbxStatusResponse('CONFIRMED', ['tx_hash' => '0xabc']))
        ->push(dbxStatusResponse('FAILED', ['error_log' => ['reason' => 'policy']]))]);

    $service = new DigibloxPayoutService(dbxServiceConfig());

    expect($service->lookup('WD-01TEST', 'Qk1Z')->payload['outcomeHash'])->toBe('0xabc');
    expect($service->lookup('WD-01TEST', 'Qk1Z')->error)->toContain('FAILED')->toContain('policy');
});

it('refuses a transfer whose note names a different order', function () {
    dbxFake(['https://digiblox.test/gateway/api/v1/transfers/*' => Http::response(dbxStatusResponse('QUEUED', ['user_note' => 'WD-OTHER']))]);

    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig()))->lookup('WD-01TEST', 'Qk1Z'))
        ->toThrow(PayoutOutcomeUnknownException::class, 'WD-OTHER');
});

it('turns a failed status lookup into an unknown outcome, never a not-found', function () {
    dbxFake(['https://digiblox.test/gateway/api/v1/transfers/*' => Http::response(['message' => 'Not Found'], 404)]);

    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig()))->lookup('WD-01TEST', 'Qk1Z'))
        ->toThrow(PayoutOutcomeUnknownException::class);
});

it('refuses to parse payout webhooks, which Digiblox does not send', function () {
    expect(fn () => (new DigibloxPayoutService(dbxServiceConfig()))->parsePayoutWebhook([]))
        ->toThrow(PaymentProcessingException::class);
});

it('exposes payouts through the provider', function () {
    $provider = new DigibloxProvider(dbxServiceConfig());

    expect($provider)->toBeInstanceOf(\Asciisd\CashierCore\Contracts\SendsPayouts::class)
        ->and($provider->supports('payout'))->toBeTrue();
});
