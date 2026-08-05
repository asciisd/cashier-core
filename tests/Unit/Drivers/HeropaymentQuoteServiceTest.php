<?php

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentClient;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentQuoteService;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('transactions.currency.default', 'USD');
    config()->set('transactions.providers.heropayment.base_url', 'https://hero.test');
    config()->set('transactions.providers.heropayment.api_key', 'test-key');
    config()->set('transactions.providers.heropayment.api_secret', 'test-secret');
    config()->set('transactions.providers.heropayment.fee_percent', 0.5);
});

function fakeHeroLookups(array $overrides = []): void
{
    Http::fake(array_merge([
        'hero.test/v2/currencies' => Http::response([
            ['ticker' => 'usdttrc20', 'fullName' => 'Tether', 'blockchain' => 'tron', 'isStable' => true],
            ['ticker' => 'btc', 'fullName' => 'Bitcoin', 'blockchain' => 'bitcoin', 'isStable' => false],
            ['ticker' => 'doge', 'fullName' => 'Dogecoin', 'blockchain' => 'dogecoin', 'isStable' => false],
        ]),
        'hero.test/v2/network-fees' => Http::response([
            ['networkfee' => '2.0000000000', 'ticker' => 'usdttrc20', 'type' => 'deposit'],
            ['networkfee' => '9.9900000000', 'ticker' => 'usdttrc20', 'type' => 'withdrawal'],
            ['networkfee' => '0.0000070000', 'ticker' => 'btc', 'type' => 'deposit'],
        ]),
        'hero.test/v2/rate*' => Http::response([
            'currencyFrom' => 'usd',
            'currencyTo' => 'usdttrc20',
            'transactionType' => 'deposit',
            'rate' => '2.0',
            'reverseRate' => '0.5',
        ]),
        'hero.test/v2/min-amount*' => Http::response([
            'minDeposit' => 5.0,
            'minWithdrawal' => 5.0,
            'currency' => 'usdttrc20',
        ]),
    ], $overrides));
}

function heroQuoteService(): HeropaymentQuoteService
{
    $config = (array) config('transactions.providers.heropayment');

    return new HeropaymentQuoteService(HeropaymentClient::fromConfig($config), $config);
}

it('signs lookup requests and narrows currencies to the given tickers with their deposit fee', function () {
    fakeHeroLookups();

    $currencies = heroQuoteService()->availableCurrencies(['usdttrc20', 'btc']);

    expect($currencies)->toHaveCount(2)
        ->and(array_column($currencies, 'ticker'))->toBe(['usdttrc20', 'btc'])
        ->and($currencies[0]['network_fee'])->toBe(2.0)
        ->and($currencies[1]['network_fee'])->toBe(0.000007);

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://hero.test/v2/currencies')
            && $request->hasHeader('x-api-key', 'test-key')
            // GET signs the query string; /v2/currencies has none, so the payload is empty.
            && $request->hasHeader('x-api-sign', hash_hmac('sha512', '', 'test-secret'));
    });
});

it('signs a rate lookup over its query string', function () {
    fakeHeroLookups();

    expect(heroQuoteService()->rate('usd', 'usdttrc20'))->toBe(2.0);

    Http::assertSent(function ($request) {
        $query = parse_url($request->url(), PHP_URL_QUERY);

        return str_starts_with($request->url(), 'https://hero.test/v2/rate')
            && $query === 'currencyFrom=usd&currencyTo=usdttrc20&transactionType=deposit'
            && $request->hasHeader('x-api-sign', hash_hmac('sha512', $query, 'test-secret'));
    });
});

it('quotes a deposit with rate, fees and minimum converted to the price currency', function () {
    fakeHeroLookups();

    $quote = heroQuoteService()->quote(100.0, 'usdttrc20');

    expect($quote->priceCurrency)->toBe('usd')
        ->and($quote->payCurrency)->toBe('usdttrc20')
        ->and($quote->rate)->toBe(2.0)
        ->and($quote->payAmount)->toBe(200.0)          // 100 usd * 2.0
        ->and($quote->networkFee)->toBe(2.0)           // native usdttrc20 units
        ->and($quote->networkFeeInPriceCurrency)->toBe(1.0)
        ->and($quote->providerFeePercent)->toBe(0.5)
        ->and($quote->providerFeeAmount)->toBe(0.5)
        ->and($quote->minDeposit)->toBe(5.0)
        ->and($quote->minDepositInPriceCurrency)->toBe(2.5)
        ->and($quote->meetsMinimum)->toBeTrue()
        ->and($quote->estimatedNetCredit)->toBe(98.5); // 100 - 0.5 fee - 1.0 network
});

it('flags a deposit below the provider minimum', function () {
    fakeHeroLookups();

    expect(heroQuoteService()->quote(2.0, 'usdttrc20')->meetsMinimum)->toBeFalse();
});

it('refuses to quote when the provider returns no rate', function () {
    fakeHeroLookups(['hero.test/v2/rate*' => Http::response([], 502)]);

    heroQuoteService()->quote(100.0, 'usdttrc20');
})->throws(PaymentProcessingException::class, 'no usd/usdttrc20 rate available');

it('degrades to a rate-only quote when fee and minimum lookups fail', function () {
    fakeHeroLookups([
        'hero.test/v2/network-fees' => Http::response([], 500),
        'hero.test/v2/min-amount*' => Http::response([], 500),
    ]);

    $quote = heroQuoteService()->quote(100.0, 'usdttrc20');

    expect($quote->payAmount)->toBe(200.0)
        ->and($quote->networkFee)->toBeNull()
        ->and($quote->minDeposit)->toBeNull()
        ->and($quote->meetsMinimum)->toBeTrue()
        ->and($quote->estimatedNetCredit)->toBe(99.5); // provider fee still applies
});

it('caches lookups so a deposit screen does not re-hit the provider', function () {
    fakeHeroLookups();

    $service = heroQuoteService();
    $service->quote(100.0, 'usdttrc20');
    $service->quote(250.0, 'usdttrc20');

    Http::assertSentCount(3); // rate + network-fees + min-amount, once each
});

it('omits the provider fee when none is configured', function () {
    config()->set('transactions.providers.heropayment.fee_percent', null);
    fakeHeroLookups();

    $quote = heroQuoteService()->quote(100.0, 'usdttrc20');

    expect($quote->providerFeePercent)->toBeNull()
        ->and($quote->providerFeeAmount)->toBeNull()
        ->and($quote->estimatedNetCredit)->toBe(99.0); // network fee only
});

it('offers every supported currency when no tickers are given', function () {
    fakeHeroLookups();

    expect(heroQuoteService()->availableCurrencies())->toHaveCount(3);
});

it('skips tickers the provider does not support', function () {
    fakeHeroLookups();

    // "usdt" is not a ticker — the network is part of it (usdt20, usdttrc20, ...).
    expect(heroQuoteService()->availableCurrencies(['usdt', 'usdttrc20']))->toHaveCount(1);
});
