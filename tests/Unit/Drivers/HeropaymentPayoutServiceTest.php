<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentPayoutService;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

function hpService(array $overrides = []): HeropaymentPayoutService
{
    return new HeropaymentPayoutService(array_merge(HeropaymentPayoutApi::CONFIG, $overrides));
}

function hpRequest(array $overrides = []): PayoutRequest
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

// --- fail-closed configuration -------------------------------------------

it('refuses when withdrawals are disabled', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawals_enabled' => false])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'withdrawals are disabled');
});

it('fails closed on an invalid cap', function (mixed $cap) {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawal_max_amount' => $cap])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class);
})->with([
    'missing' => [null],
    'empty' => [''],
    'zero' => ['0'],
    'non-numeric' => ['lots'],
    'scientific' => ['2.5e1'],
]);

it('refuses an amount over the cap', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawal_max_amount' => '50'])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'exceeds the configured cap of 50');
});

it('refuses when fee_percent is not configured', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['fee_percent' => null])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'fee_percent');
});

it('refuses a request with no payout address', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService()->preflight(hpRequest(['payoutAddress' => '  '])))
        ->toThrow(PaymentProcessingException::class, 'payout address');
});

// --- the balance check ----------------------------------------------------

it('passes when the balance exactly covers amount, fee, network fee and buffer', function () {
    // (100 × 1.006 + 1.0) × 1.02 = 103.632
    HeropaymentPayoutApi::fake(['balance' => '103.632']);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeTrue()
        ->and($preflight->walletCurrency)->toBe('usdttrc20')
        ->and(bccomp($preflight->required, '103.632', 8))->toBe(0);
});

it('refuses with the numbers when the balance is short', function () {
    HeropaymentPayoutApi::fake(['balance' => '103.63199999']);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeFalse()
        ->and($preflight->reason)->toBe(PayoutPreflight::INSUFFICIENT_FUNDS)
        ->and($preflight->balance)->toBe('103.63199999')
        ->and($preflight->message)->toContain('103.63199999')->toContain('103.632');
});

it('converts a network fee quoted in the payout coin into the wallet currency', function (string $balance, bool $ok) {
    // Wallet usdttrc20, payout btc, every rate 2.0, btc network fee 1.0 (native units):
    // base 100 × 2 = 200; × 1.006 = 201.2; + 1.0 btc × 2 = 203.2; × 1.02 = 207.264
    HeropaymentPayoutApi::fake(['balance' => $balance, 'rate' => '2.0', 'fee_ticker' => 'btc']);

    expect(hpService()->preflight(hpRequest(['payoutCurrency' => 'btc']))->ok)->toBe($ok);
})->with([
    'exactly enough' => ['207.264', true],
    'just short' => ['207.26', false],
]);

it('refuses a payout coin with no withdrawal network fee instead of assuming zero', function () {
    // The fee row is for usdttrc20 only; a btc payout has none.
    HeropaymentPayoutApi::fake();

    expect(hpService()->preflight(hpRequest(['payoutCurrency' => 'btc']))->reason)
        ->toBe(PayoutPreflight::QUOTE_UNAVAILABLE);
});

it('refuses when the amount is below the minimum withdrawal', function () {
    HeropaymentPayoutApi::fake(['min' => 150.0]);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeFalse()
        ->and($preflight->reason)->toBe(PayoutPreflight::BELOW_MINIMUM);
});

// --- Review Focus 3: outages refuse, never pass -----------------------------

it('refuses when the balance is unavailable', function () {
    HeropaymentPayoutApi::fake(['balance' => null]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::BALANCE_UNAVAILABLE);
});

it('refuses when the withdrawal rate is unavailable', function () {
    HeropaymentPayoutApi::fake(['rate' => null]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::QUOTE_UNAVAILABLE);
});

it('refuses when the balance lookup times out', function () {
    HeropaymentPayoutApi::fake([
        'balance' => fn () => throw new ConnectionException('cURL error 28: timed out'),
    ]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::BALANCE_UNAVAILABLE);
});

// --- Review Focus 4: malformed balance strings ------------------------------

it('refuses a malformed balance string instead of crashing', function (string $balance) {
    HeropaymentPayoutApi::fake(['balance' => $balance]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::BALANCE_UNAVAILABLE);
})->with([
    'thousands separator' => ['1,234.50'],
    'empty' => [''],
    'scientific' => ['2.5e1'],
    'negative' => ['-5'],
]);
