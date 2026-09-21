<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxTransferService;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function transferService(array $overrides = []): DigibloxTransferService
{
    return new DigibloxTransferService(array_merge([
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '100',
    ], $overrides));
}

beforeEach(function () {
    Cache::flush();

    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => Http::response(
            ['id' => 'Qk1ZbFZkN2R3Z1E9', 'status' => 'QUEUED'], 202,
        ),
    ]);
});

it('refuses to send while withdrawals are disabled', function () {
    expect(fn () => transferService(['withdrawals_enabled' => false])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'disabled');

    Http::assertNothingSent();
});

it('refuses an amount above the configured cap', function () {
    expect(fn () => transferService()
        ->create('500', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'exceeds');
});

it('refuses a malformed amount, because the API does not', function () {
    foreach (['abc', '', '0', '-5'] as $amount) {
        expect(fn () => transferService()
            ->create($amount, '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
            ->toThrow(PaymentProcessingException::class);
    }
});

it('refuses an empty destination address, because the API does not validate it', function () {
    expect(fn () => transferService()->create('25', '  ', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'address');
});

it('accepts a 202 and returns the opaque transfer id', function () {
    $result = transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    expect($result['id'])->toBe('Qk1ZbFZkN2R3Z1E9')
        ->and($result['status'])->toBe('QUEUED');
});

it('sends the fixed reporting fields verbatim', function () {
    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['initial_rate'] === '1.00'
        && $request['initial_rate_currency_id'] === 'bkE0RmNjbEhCUmc9'
        && $request['amount'] === '25'
        && $request['network'] === 'ETHEREUM'
        && $request['asset'] === 'USDC');
});

it('carries our reference in user_note so treasury exports can be joined', function () {
    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC', 'WD-777');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['user_note'] === 'WD-777');
});

it('truncates a note to the documented 255 characters', function () {
    transferService()->create('25', '0xabc', 'ETHEREUM', 'USDC', str_repeat('x', 300));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && strlen($request['user_note']) === 255);
});
