<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentPayoutService;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

it('refuses with QUOTE_UNAVAILABLE instead of assuming zero when the withdrawal network fee is null', function () {
    HeropaymentPayoutApi::fake(['fee' => null]);

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

// --- send ------------------------------------------------------------------

it('sends the V2 withdrawal body and returns a receipt', function () {
    HeropaymentPayoutApi::fake();

    $receipt = hpService()->send(hpRequest(['payoutExtraId' => 'memo-1', 'customerEmail' => 'c@example.com']));

    expect($receipt->reference)->toBe('hero-wd-1')
        ->and($receipt->state)->toBe(PayoutState::Sent);

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/v2/withdrawal')) {
            return false;
        }

        return $request->data() === [
            'customerId' => '70001',
            'payoutAddress' => 'TXyzCustomer',
            'payoutCurrency' => 'usdttrc20',
            'priceCurrency' => 'usd',
            'priceAmount' => '100.00',
            'payoutExtraId' => 'memo-1',
            'customerEmail' => 'c@example.com',
            'externalOrderId' => 'WD-01TEST',
            'callbackUrl' => 'https://members.example.com/api/webhooks/heropayment',
            'fiat' => true,
        ];
    });
});

it('omits empty optional fields', function () {
    HeropaymentPayoutApi::fake();

    hpService()->send(hpRequest());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && ! array_key_exists('payoutExtraId', $request->data())
        && ! array_key_exists('customerEmail', $request->data()));
});

it('falls back to the package webhook route under its configured name prefix', function () {
    // The package routes are registered in tests as cashier.webhooks.heropayment.
    // The deposit path looks up `webhooks.heropayment`, which never matches —
    // the payout path must not repeat that.
    HeropaymentPayoutApi::fake();

    hpService(['webhook_url' => null])->send(hpRequest());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && str_ends_with((string) $request['callbackUrl'], '/api/webhooks/heropayment'));
});

it('refuses to send when no callback URL resolves', function () {
    HeropaymentPayoutApi::fake();
    app('router')->setRoutes(new \Illuminate\Routing\RouteCollection);

    expect(fn () => hpService(['webhook_url' => null])->send(hpRequest()))
        ->toThrow(\Asciisd\CashierCore\Exceptions\PaymentProcessingException::class, 'callback URL');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});

it('falls back to the package webhook route when webhook_url is empty', function () {
    HeropaymentPayoutApi::fake();

    hpService(['webhook_url' => ''])->send(hpRequest());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['callbackUrl'] === route('cashier.webhooks.heropayment'));
});

it('treats a 4xx as a clean rejection carrying Heropayments message', function () {
    HeropaymentPayoutApi::fake(['withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    expect(fn () => hpService()->send(hpRequest()))
        ->toThrow(PayoutRejectedException::class, 'Payout address not valid');
});

it('resolves a not-unique rejection by looking the payout up', function () {
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
        'lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'sending'])),
    ]);

    $receipt = hpService()->send(hpRequest());

    expect($receipt->reference)->toBe('hero-wd-1')
        ->and($receipt->rawStatus)->toBe('sending');
});

it('reports unknown when not-unique cannot be looked up', function () {
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
    ]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
});

it('reports unknown when not-unique cannot be looked up because the lookup times out', function () {
    // Ruling: lookup() -> getPaymentByOrderId() can throw ConnectionException on
    // a timeout. The not-unique branch in send() must catch that and report it
    // the same way as any other unresolved not-unique rejection, never let the
    // ConnectionException escape uncaught.
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
        'lookup' => fn () => throw new ConnectionException('cURL error 28: timed out'),
    ]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
});

it('reports unknown on a 5xx, a timeout, or a 2xx without an id', function (mixed $answer) {
    HeropaymentPayoutApi::fake(['withdrawal' => $answer]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
})->with([
    '500' => fn () => Http::response(['message' => 'internal server error'], 500),
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: timed out'),
    '200 no id' => fn () => Http::response(['status' => 'waiting']),
]);

it('checks config and input before sending', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawals_enabled' => false])->send(hpRequest()))
        ->toThrow(\Asciisd\CashierCore\Exceptions\PaymentProcessingException::class);

    Http::assertNothingSent();
});

// --- lookup ------------------------------------------------------------------

it('looks a payout up by order id', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'finished']))]);

    $receipt = hpService()->lookup('WD-01TEST');

    expect($receipt?->state)->toBe(PayoutState::Paid);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/payments/order/WD-01TEST'));
});

it('returns null when the lookup finds nothing', function () {
    HeropaymentPayoutApi::fake();

    expect(hpService()->lookup('WD-01TEST'))->toBeNull();
});

// --- Final-2: a failed lookup is never "not found" -----------------------------

it('returns null only on a definitive 404', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(['message' => 'Not found'], 404)]);

    expect(hpService()->lookup('WD-01TEST'))->toBeNull();
});

it('throws outcome-unknown when the lookup itself fails', function (mixed $answer) {
    HeropaymentPayoutApi::fake(['lookup' => $answer]);

    expect(fn () => hpService()->lookup('WD-01TEST'))
        ->toThrow(PayoutOutcomeUnknownException::class, 'lookup failed');
})->with([
    '500' => fn () => Http::response(['message' => 'internal server error'], 500),
    '403' => fn () => Http::response(['message' => 'Forbidden'], 403),
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: timed out'),
    '200 no id' => fn () => Http::response(['status' => 'waiting']),
    '200 non-array' => fn () => Http::response('"ok"'),
]);

it('reports unknown when not-unique is followed by a failed lookup', function () {
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
        'lookup' => Http::response(['message' => 'internal server error'], 500),
    ]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
});
