<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderPayoutService;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-10-12 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function pcbService(array $overrides = []): PayCrossBorderPayoutService
{
    return new PayCrossBorderPayoutService(array_merge(PayCrossBorderApi::CONFIG, $overrides));
}

/** The most recent payout POST, or null when none was sent. */
function pcbPayoutSent(): ?Request
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api'))
        ->last();
}

/**
 * Datasets load before the app boots, so they hold plain data and the test
 * turns it into a stub: 'timeout', or ['json' => …, 'body' => …, 'status' => …].
 */
function pcbStub(array|string $spec): mixed
{
    if ($spec === 'timeout') {
        return fn () => throw new ConnectionException('cURL error 28');
    }

    return Http::response($spec['body'] ?? $spec['json'] ?? [], $spec['status'] ?? 200);
}

// --- references ---------------------------------------------------------

it('derives readable references from workflow order ids and hashes the rest', function () {
    expect(PayCrossBorderPayoutService::reference('WD-01M4PQ4K3S3PAPTFM7J6FHJ60E'))->toBe('WD01M4PQ4K3S3PAPTFM7J6FHJ60E')
        ->and(PayCrossBorderPayoutService::reference('WD-01M4PQ4K3S3PAPTFM7J6FHJ60E-2'))->toBe('WD01M4PQ4K3S3PAPTFM7J6FHJ60E2')
        ->and(PayCrossBorderPayoutService::reference('WD-01TEST'))->toBe(PayCrossBorderApi::REFERENCE)
        ->and(PayCrossBorderPayoutService::reference('wd 1/2'))->toMatch('/^PCB[0-9A-F]{27}$/')
        ->and(PayCrossBorderPayoutService::reference('WD-A'))->not->toBe(PayCrossBorderPayoutService::reference('WD-B'));
});

// --- guard (shared by preflight and send) --------------------------------

it('refuses on configuration or input before calling PCB', function (array $config, array $request, ?array $beneficiary, string $message) {
    Http::fake();

    expect(fn () => pcbService($config)->preflight(PayCrossBorderApi::request($request, $beneficiary)))
        ->toThrow(PaymentProcessingException::class, $message);
    expect(fn () => pcbService($config)->send(PayCrossBorderApi::request($request, $beneficiary)))
        ->toThrow(PaymentProcessingException::class, $message);

    Http::assertNothingSent();
})->with([
    'disabled' => [['withdrawals_enabled' => false], [], null, 'withdrawals are disabled'],
    'cap unset' => [['withdrawal_max_amount' => null], [], null, 'no valid withdrawal_max_amount'],
    'cap zero' => [['withdrawal_max_amount' => '0'], [], null, 'no valid withdrawal_max_amount'],
    'over cap' => [[], ['amount' => '1000.01'], null, 'exceeds the configured cap of 1000'],
    'bad amount' => [[], ['amount' => '1e2'], null, 'positive number'],
    'other currency' => [[], ['currency' => 'AED'], null, 'An AED withdrawal cannot be paid from the PayCrossBorder USD-USA wallet'],
    'unmapped currency' => [[], ['currency' => 'EUR'], null, 'no currency code configured for eur'],
    'unmapped payout currency' => [[], ['payoutCurrency' => 'btc'], null, 'no currency code configured for btc'],
    'incomplete beneficiary' => [[], [], PayCrossBorderApi::beneficiary(['gender' => 'x']), 'beneficiary gender'],
    'bank and wallet' => [[], [], PayCrossBorderApi::bankBeneficiary(['wallet' => ['providerName' => 'G', 'providerCode' => '1']]), 'not both'],
]);

it('refuses a request without a beneficiary', function () {
    Http::fake();

    expect(fn () => pcbService()->send(PayCrossBorderApi::request(['beneficiary' => null])))
        ->toThrow(PaymentProcessingException::class, 'need a beneficiary');
});

// --- preflight -------------------------------------------------------------

it('passes preflight against the Main Wallet in the send currency', function () {
    PayCrossBorderApi::fake();

    $preflight = pcbService()->preflight(PayCrossBorderApi::request());

    expect($preflight->ok)->toBeTrue()
        ->and($preflight->balance)->toBe('5000')
        ->and($preflight->required)->toBe('102.00000000')
        ->and($preflight->walletCurrency)->toBe('USD-USA');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/wallet/walletOwner/1000008444'));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api'));
});

it('reads the balance of wallet_owner_code when it is configured', function () {
    PayCrossBorderApi::fake();

    pcbService(['wallet_owner_code' => '1000009999'])->preflight(PayCrossBorderApi::request());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/wallet/walletOwner/1000009999'));
});

it('refuses preflight on balance problems', function (array|string $balance, string $reason) {
    PayCrossBorderApi::fake(['balance' => pcbStub($balance)]);

    $preflight = pcbService()->preflight(PayCrossBorderApi::request());

    expect($preflight->ok)->toBeFalse()->and($preflight->reason)->toBe($reason);
})->with([
    'too little' => [['json' => PayCrossBorderApi::balance(101.99)], PayoutPreflight::INSUFFICIENT_FUNDS],
    'below minimum' => [['json' => PayCrossBorderApi::balance(5000.0, 150.0)], PayoutPreflight::BELOW_MINIMUM],
    'unreadable value' => [['json' => PayCrossBorderApi::balance('lots')], PayoutPreflight::BALANCE_UNAVAILABLE],
    'no main wallet' => [['json' => ['resultCode' => '0', 'walletList' => []]], PayoutPreflight::BALANCE_UNAVAILABLE],
    'result code' => [['json' => ['resultCode' => '9', 'resultDescription' => 'nope']], PayoutPreflight::BALANCE_UNAVAILABLE],
    'timeout' => ['timeout', PayoutPreflight::BALANCE_UNAVAILABLE],
]);

// --- send --------------------------------------------------------------------

it('quotes by payinAmount and sends a crypto payout', function () {
    PayCrossBorderApi::fake();

    $receipt = pcbService()->send(PayCrossBorderApi::request());

    expect($receipt->reference)->toBe('197930')
        ->and($receipt->state)->toBe(PayoutState::Sent)
        ->and($receipt->rawStatus)->toBe('In Process')
        ->and($receipt->payload['payoutResponseBean']['transReference'])->toBe('197930');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/fxratequotation/api')
        && $request->data() == [
            'payinAmount' => '100.00',
            'payoutAmount' => '',
            'sendCurrencyCode' => 'USD-USA',
            'receiveCurrencyCode' => 'USDT',
            'settlementCurrencyCode' => 'USD-USA',
            'destinationCountryCode' => 'ARE',
            'sourceCountry' => 'ARE',
            'serviceTypeCode' => 'B2C',
            'paymentMode' => 'Cash',
            'senderCode' => '1000008444',
            'customerCode' => '1000002225',
            'chargeTypeCode' => 'OUR',
        ]);

    $payout = pcbPayoutSent()->data()['payout'];

    expect($payout['transactionInfo'])->toEqual([
        'payinAmount' => '100.00',
        'payinCurrency' => 'USD-USA',
        'type' => 'B2C',
        'requestDate' => '12-10-2026',
        'sendClientTrxReference' => PayCrossBorderApi::REFERENCE,
        'descriptionText' => substr(PayCrossBorderApi::REFERENCE, 0, 25),
        'paymentMode' => 'Cash',
        'sendClientCode' => '1000008444',
        'payoutCurrency' => 'USDT',
        'payoutAmount' => '98.00',
        'destinationCountryCode' => 'ARE',
        'settlementCurrency' => 'USD-USA',
        'sourceCountry' => 'ARE',
        'fxRateValue' => '0.98',
        'senderMargin' => '0.98',
    ])->and($payout['sender'])->toEqual(['business' => [
        'isAutoRegistered' => false,
        'declaration' => false,
        'docReferenceNumber' => PayCrossBorderApi::REFERENCE,
        'customerCode' => '1000002225',
    ]])->and($payout['receiver']['customer']['walletAddress'])->toBe('TXyzCustomerWalletAddress000001')
        ->and($payout['compliance'])->toEqual([
            'forexQuoteId' => '114672',
            'remittancePurpose' => 'PAYP002',
            'sourceOfFund' => 'PAYS005',
            'relationship' => 'PAYR002',
        ]);
});

it('sends bank payouts as paymentMode Bank and wallet payouts as Cash', function () {
    PayCrossBorderApi::fake();

    pcbService()->send(PayCrossBorderApi::request(['payoutCurrency' => 'aed'], PayCrossBorderApi::bankBeneficiary()));
    $bank = pcbPayoutSent()->data()['payout'];

    expect($bank['transactionInfo']['paymentMode'])->toBe('Bank')
        ->and($bank['transactionInfo']['payoutCurrency'])->toBe('AED')
        ->and($bank['receiver']['customer']['receiverAccountNumber'])->toBe('AE110260000959024298101');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/fxratequotation/api')
        && $request['paymentMode'] === 'Bank');

    pcbService()->send(PayCrossBorderApi::request(['payoutCurrency' => 'php'], PayCrossBorderApi::walletBeneficiary()));
    $wallet = pcbPayoutSent()->data()['payout'];

    expect($wallet['transactionInfo']['paymentMode'])->toBe('Cash')
        ->and($wallet['transactionInfo']['destinationCountryCode'])->toBe('PHL')
        ->and($wallet['receiver']['customer']['receiverServiceProviderCode'])->toBe('63001');
});

it('falls back to fxRateValue when the quote has no senderMargin', function () {
    PayCrossBorderApi::fake(['quote' => Http::response(PayCrossBorderApi::quote(['senderMargin' => null]))]);

    pcbService()->send(PayCrossBorderApi::request());

    expect(pcbPayoutSent()->data()['payout']['transactionInfo']['senderMargin'])->toBe('0.98');
});

it('refuses to send when the quote is unusable', function (array|string $quote, string $message) {
    PayCrossBorderApi::fake(['quote' => pcbStub($quote)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PaymentProcessingException::class, $message);

    expect(pcbPayoutSent())->toBeNull();
})->with([
    'result code' => [['json' => ['resultCode' => '1015', 'resultDescription' => 'No rate']], 'No rate'],
    'different amount' => [['json' => PayCrossBorderApi::quote(['payinAmount' => '99.50'])], 'quoted 99.50 for a 100.00 payout'],
    'no code' => [['json' => PayCrossBorderApi::quote(['code' => null])], 'without code'],
    'timeout' => ['timeout', 'cURL error 28'],
]);

it('treats PCB refusals as rejections', function (array $payout, string $message) {
    PayCrossBorderApi::fake(['payout' => pcbStub($payout)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PayoutRejectedException::class, $message);
})->with([
    '400' => [['json' => ['resultCode' => '1001', 'resultDescription' => 'Invalid wallet'], 'status' => 400], 'Invalid wallet'],
    '422' => [['json' => ['resultCode' => '1002', 'resultDescription' => 'Bad receiver'], 'status' => 422], 'Bad receiver'],
    'result code in a 200' => [['json' => ['resultCode' => '1003', 'resultDescription' => 'Screening failed']], 'Screening failed'],
]);

it('treats ambiguous payout answers as unknown', function (array|string $payout, string $message) {
    PayCrossBorderApi::fake(['payout' => pcbStub($payout)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PayoutOutcomeUnknownException::class, $message);
})->with([
    'timeout' => ['timeout', 'did not answer'],
    '500' => [['json' => ['resultCode' => '500'], 'status' => 500], 'returned 500'],
    '409' => [['status' => 409], 'returned 409'],
    'success without reference' => [['json' => ['resultCode' => '0', 'payoutResponseBean' => []]], 'without a transaction reference'],
    'unreadable body' => [['body' => '<html>gateway</html>'], 'without a transaction reference'],
]);

it('never retries the payout POST', function () {
    PayCrossBorderApi::fake(['payout' => pcbStub(['status' => 500])]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))->toThrow(PayoutOutcomeUnknownException::class);

    expect(collect(Http::recorded())->filter(fn (array $pair) => str_ends_with($pair[0]->url(), '/payoutProcess/api')))->toHaveCount(1);
});

// --- lookup ------------------------------------------------------------------

it('looks a payout up by PCB\'s transId and ties it to our reference', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Payout Pass']))]);

    $receipt = pcbService()->lookup('WD-01TEST', '197930');

    expect($receipt->reference)->toBe('197930')->and($receipt->state)->toBe(PayoutState::Paid);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'transId=197930'));
});

it('never returns null from lookup', function (?string $reference, array|string|null $transaction, string $message) {
    PayCrossBorderApi::fake($transaction === null ? [] : ['transaction' => pcbStub($transaction)]);

    expect(fn () => pcbService()->lookup('WD-01TEST', $reference))
        ->toThrow(PayoutOutcomeUnknownException::class, $message);
})->with([
    'no reference' => [null, null, 'only look a payout up by its own transaction id'],
    'blank reference' => ['  ', null, 'only look a payout up by its own transaction id'],
    'no rows' => ['197930', ['json' => ['resultCode' => '0', 'transactionInfoResponse' => []]], 'returned 0 transactions'],
    'other transId' => ['197930', ['json' => PayCrossBorderApi::transaction(['transId' => 197931])], 'answered for transaction 197931'],
    'other order' => ['197930', ['json' => PayCrossBorderApi::transaction(['senderUniqueId' => 'PAYXPY29FF'])], 'belongs to PAYXPY29FF'],
    'enquiry failed' => ['197930', ['json' => ['resultCode' => '9'], 'status' => 500], 'lookup failed'],
    'timeout' => ['197930', 'timeout', 'lookup failed'],
]);

it('has no payout callbacks', function () {
    expect(fn () => pcbService()->parsePayoutWebhook([]))
        ->toThrow(PaymentProcessingException::class, 'sends no payout callbacks');
});
