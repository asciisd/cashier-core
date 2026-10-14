<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function hpClient(): HeropaymentClient
{
    return new HeropaymentClient('https://hero.test', 'test-key', 'test-secret');
}

it('reads the balance, signing the empty query string', function () {
    Http::fake(['hero.test/v2/balance' => Http::response([
        'walletAddress' => 'TMerchant',
        'walletCurrency' => 'usdttrc20',
        'balance' => '333.80103',
    ])]);

    expect(hpClient()->getBalance())->toBe([
        'walletAddress' => 'TMerchant',
        'walletCurrency' => 'usdttrc20',
        'balance' => '333.80103',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hero.test/v2/balance'
        && $request->header('x-api-key')[0] === 'test-key'
        && $request->header('x-api-sign')[0] === hash_hmac('sha512', '', 'test-secret'));
});

it('returns null when the balance lookup fails', function () {
    Http::fake(['hero.test/v2/balance' => Http::response([], 500)]);

    expect(hpClient()->getBalance())->toBeNull();
});

it('signs the withdrawal body exactly as sent, without escaping slashes or unicode', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response(['id' => 'hero-wd-1'])]);

    hpClient()->createWithdrawal([
        'customerEmail' => 'zoë@example.com',
        'callbackUrl' => 'https://members.example.com/api/webhooks/heropayment',
        'priceAmount' => '10.00',
    ]);

    Http::assertSent(function (Request $request) {
        $raw = $request->body();

        return $request->url() === 'https://hero.test/v2/withdrawal'
            && str_contains($raw, 'https://members.example.com/api/webhooks/heropayment')
            && str_contains($raw, 'zoë@example.com')
            && $request->header('x-api-sign')[0] === hash_hmac('sha512', $raw, 'test-secret');
    });
});

it('returns the response instead of throwing on a 4xx or 5xx', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    $response = hpClient()->createWithdrawal(['priceAmount' => '10.00']);

    expect($response->status())->toBe(400)
        ->and($response->json('message'))->toBe('Payout address not valid');
});

it('sends a withdrawal exactly once, with no automatic retry', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response([], 500)]);

    hpClient()->createWithdrawal(['priceAmount' => '10.00']);

    Http::assertSentCount(1);
});
