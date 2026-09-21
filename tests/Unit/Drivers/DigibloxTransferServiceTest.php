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

/**
 * Fakes both endpoints. Http::fake() stubs match in registration order — first
 * match wins — so a test that wants a non-default transfer response must be
 * the only place that registers the transfer stub. Guard tests (which must
 * never reach the HTTP layer) don't call this helper, but they still need a
 * bare Http::fake() of their own: Laravel only records outbound requests once
 * fake() has switched recording on for that test, so without it
 * Http::assertNothingSent() cannot fail no matter what the code does. A fake
 * with no stubs is enough — no request should ever match, or be sent, at all.
 */
function fakeDigiblox(int $transferStatus = 202, array $transferBody = ['id' => 'Qk1ZbFZkN2R3Z1E9', 'status' => 'QUEUED']): void
{
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => Http::response($transferBody, $transferStatus),
    ]);
}

beforeEach(function () {
    Cache::flush();
});

it('refuses to send while withdrawals are disabled', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawals_enabled' => false])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'disabled');

    Http::assertNothingSent();
});

it('refuses an amount above the configured cap', function () {
    Http::fake();

    expect(fn () => transferService()
        ->create('500', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'exceeds');

    Http::assertNothingSent();
});

it('refuses a malformed amount, because the API does not', function () {
    Http::fake();

    foreach (['abc', '', '0', '-5'] as $amount) {
        expect(fn () => transferService()
            ->create($amount, '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
            ->toThrow(PaymentProcessingException::class);
    }

    Http::assertNothingSent();
});

it('refuses an empty destination address, because the API does not validate it', function () {
    Http::fake();

    expect(fn () => transferService()->create('25', '  ', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'address');

    Http::assertNothingSent();
});

it('fails closed when withdrawal_max_amount is missing', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawal_max_amount' => null])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('fails closed when withdrawal_max_amount is an empty string', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawal_max_amount' => ''])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('fails closed when withdrawal_max_amount is zero', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawal_max_amount' => '0'])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('fails closed when withdrawal_max_amount is non-numeric', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawal_max_amount' => 'not-a-number'])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('refuses a scientific-notation amount, which is_numeric() accepts but bcmath cannot compare', function () {
    Http::fake();

    expect(fn () => transferService()
        ->create('2.5e1', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('refuses a scientific-notation cap, which is_numeric() accepts but bcmath cannot compare', function () {
    Http::fake();

    expect(fn () => transferService(['withdrawal_max_amount' => '2.5e1'])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class);

    Http::assertNothingSent();
});

it('accepts a whitespace-padded amount, trimming it before validation and before sending', function () {
    fakeDigiblox();

    $result = transferService()->create(' 25 ', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    expect($result['id'])->toBe('Qk1ZbFZkN2R3Z1E9');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['amount'] === '25');
});

it('accepts a whitespace-padded cap', function () {
    fakeDigiblox();

    $result = transferService(['withdrawal_max_amount' => ' 100 '])
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    expect($result['id'])->toBe('Qk1ZbFZkN2R3Z1E9');
});

it('accepts a 202 and returns the opaque transfer id', function () {
    fakeDigiblox();

    $result = transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    expect($result['id'])->toBe('Qk1ZbFZkN2R3Z1E9')
        ->and($result['status'])->toBe('QUEUED');
});

it('sends the fixed reporting fields verbatim', function () {
    fakeDigiblox();

    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['initial_rate'] === '1.00'
        && $request['initial_rate_currency_id'] === 'bkE0RmNjbEhCUmc9'
        && $request['amount'] === '25'
        && $request['network'] === 'ETHEREUM'
        && $request['asset'] === 'USDC');
});

it('carries our reference in user_note so treasury exports can be joined', function () {
    fakeDigiblox();

    transferService()->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC', 'WD-777');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && $request['user_note'] === 'WD-777');
});

it('truncates a note to the documented 255 characters', function () {
    fakeDigiblox();

    transferService()->create('25', '0xabc', 'ETHEREUM', 'USDC', str_repeat('x', 300));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfers/centralized')
        && strlen($request['user_note']) === 255);
});

it('treats a 4xx as a clean rejection: no transfer was created', function () {
    fakeDigiblox(400, ['message' => 'Insufficent funds']);

    expect(fn () => transferService()
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'no transfer was created');
});

it('treats a 5xx as ambiguous: a transfer may exist and must not be retried blindly', function () {
    fakeDigiblox(500, ['message' => 'Internal Server Error']);

    expect(fn () => transferService()
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'do NOT retry');
});

it('treats a 202 with a blank id as ambiguous: it must not be retried either', function () {
    fakeDigiblox(202, ['id' => '', 'status' => 'QUEUED']);

    expect(fn () => transferService()
        ->create('25', '0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765', 'ETHEREUM', 'USDC'))
        ->toThrow(PaymentProcessingException::class, 'do NOT retry');
});

// Top-level, not inside the describe: a function declared in a closure is
// redeclared globally each time the closure runs, which fatals on the second
// pass.
function fakeTransfer(string $status, ?string $txHash = null): array
{
    return [
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/transfers/*' => Http::response([
            'api_message' => 'TRANSFER_GET_SHOW_SUCCESS',
            'api_data' => ['transfer' => [
                'id' => 'Qk1ZbFZkN2R3Z1E9',
                'status' => $status,
                'amount' => '15.000000000000000000',
                'system_fee' => 0.2,
                'currency' => 'USDT',
                'tx_hash' => $txHash,
                'currency_decimals' => 18,
            ]],
        ]),
    ];
}

describe('status', function () {
    it('reads the transfer from the api_data envelope', function () {
        Http::fake(fakeTransfer('CONFIRMED', '0xabc'));

        $result = transferService()->status('Qk1ZbFZkN2R3Z1E9');

        expect($result['status'])->toBe('CONFIRMED')
            ->and($result['mapped'])->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Succeeded)
            ->and($result['tx_hash'])->toBe('0xabc');
    });

    it('uses the status path without the v3 segment that creation uses', function () {
        Http::fake(fakeTransfer('QUEUED'));

        transferService()->status('Qk1ZbFZkN2R3Z1E9');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/gateway/api/v1/transfers/Qk1ZbFZkN2R3Z1E9')
            && ! str_contains($request->url(), '/v3/transfers'));
    });

    it('keeps FINALIZE pending — confirmed on-chain is not yet terminal', function () {
        Http::fake(fakeTransfer('FINALIZE'));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Pending);
    });

    it('keeps an unlisted status pending rather than erroring out', function () {
        Http::fake(fakeTransfer('SOME_NEW_STATE'));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Pending);
    });

    it('reports a terminal failure for each documented failure state', function (string $status) {
        Http::fake(fakeTransfer($status));

        expect(transferService()->status('X')['mapped'])
            ->toBe(Asciisd\CashierCore\Enums\PaymentStatus::Failed);
    })->with(['FAILED', 'REJECTED', 'DROPPED', 'EXPIRED']);
});
