<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderClient;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

function pcbClient(array $overrides = []): PayCrossBorderClient
{
    return PayCrossBorderClient::fromConfig(array_merge(PayCrossBorderApi::CONFIG, $overrides));
}

it('logs in with the password grant and PCB headers, then caches the token', function () {
    PayCrossBorderApi::fake();
    $client = pcbClient();

    expect($client->token())->toBe('pcb-token-1')
        ->and($client->token())->toBe('pcb-token-1');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pcb.test/ewallet/oauth/token'
        && $request->method() === 'POST'
        && $request->isForm()
        && $request['grant_type'] === 'password'
        && $request['scope'] === 'read write'
        && $request['username'] === '1000008340'
        && $request['password'] === 'HASHED-SECRET'
        && $request->header('channel') === ['WEB']
        && $request->header('source') === ['AGENT']
        && $request->header('Accept-Language') === ['en']
        && ! $request->hasHeader('Authorization'));
});

it('sends the uppercase md5 of the password when password_hash is md5', function () {
    PayCrossBorderApi::fake();

    pcbClient(['password' => 'secret', 'password_hash' => 'md5'])->token();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token')
        && $request['password'] === strtoupper(md5('secret')));
});

it('refuses an unknown password_hash', function () {
    expect(fn () => pcbClient(['password_hash' => 'sha1']))
        ->toThrow(PaymentProcessingException::class, 'password_hash');
});

it('adds basic client auth to the login when oauth_client_id is set', function () {
    PayCrossBorderApi::fake();

    pcbClient(['oauth_client_id' => 'cashier', 'oauth_client_secret' => 's3cret'])->token();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token')
        && $request->header('Authorization') === ['Basic '.base64_encode('cashier:s3cret')]);
});

it('throws with the reason when login fails', function () {
    PayCrossBorderApi::fake(['token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad credentials'], 400)]);

    expect(fn () => pcbClient()->token())->toThrow(PaymentProcessingException::class, 'Bad credentials');
});

it('turns a login timeout into a PaymentProcessingException', function () {
    PayCrossBorderApi::fake(['token' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->token())->toThrow(PaymentProcessingException::class, 'cURL error 28');
});

it('expires the cached token a minute before PCB does', function () {
    PayCrossBorderApi::fake(['token' => Http::sequence()
        ->push(PayCrossBorderApi::token('first', 120))
        ->push(PayCrossBorderApi::token('second', 120))]);
    $client = pcbClient();

    expect($client->token())->toBe('first');

    $this->travel(61)->seconds();

    expect($client->token())->toBe('second');
});

it('sends the bearer token and PCB headers on API calls', function () {
    PayCrossBorderApi::fake();

    $quote = pcbClient()->quote(['payinAmount' => '100.00']);

    expect($quote['fxRateQuotation']['code'])->toBe('114672');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pcb.test/ewallet/api/v1/fxratequotation/api'
        && $request->header('Authorization') === ['Bearer pcb-token-1']
        && $request->header('channel') === ['WEB']
        && $request['payinAmount'] === '100.00');
});

it('throws with PCB\'s description when resultCode is not 0', function () {
    PayCrossBorderApi::fake(['quote' => Http::response(['resultCode' => '1015', 'resultDescription' => 'Invalid currency'])]);

    expect(fn () => pcbClient()->quote([]))
        ->toThrow(PaymentProcessingException::class, 'resultCode 1015: Invalid currency');
});

it('forgets the token when a call answers 401', function () {
    PayCrossBorderApi::fake([
        'token' => Http::sequence()->push(PayCrossBorderApi::token('old'))->push(PayCrossBorderApi::token('new')),
        'quote' => Http::response(['error' => 'invalid_token'], 401),
    ]);
    $client = pcbClient();

    expect(fn () => $client->quote([]))->toThrow(PaymentProcessingException::class);
    expect($client->token())->toBe('new');
});

it('retries a read once with a fresh token after a 401', function () {
    PayCrossBorderApi::fake([
        'token' => Http::sequence()->push(PayCrossBorderApi::token('old'))->push(PayCrossBorderApi::token('new')),
        'transaction' => fn (Request $request) => $request->header('Authorization') === ['Bearer new']
            ? Http::response(PayCrossBorderApi::transaction())
            : Http::response(['error' => 'invalid_token'], 401),
    ]);

    $response = pcbClient()->transaction('197930');

    expect($response['transactionInfoResponse'][0]['transId'])->toBe(197930);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/ewallet/api/v1/transactionInfo/api?')
        && str_contains($request->url(), 'types=all')
        && str_contains($request->url(), 'status=all')
        && str_contains($request->url(), 'transId=197930'));
});

it('posts the payout once, wrapped, and returns the raw response', function () {
    PayCrossBorderApi::fake(['payout' => Http::response(['resultCode' => '1', 'resultDescription' => 'boom'], 500)]);

    $response = pcbClient()->payout(['transactionInfo' => ['type' => 'B2C']]);

    expect($response->status())->toBe(500);
    Http::assertSentCount(2); // login + exactly one payout
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/ewallet/api/v1/payoutProcess/api')
        && $request->data() === ['payout' => ['transactionInfo' => ['type' => 'B2C']]]);
});

it('lets a payout timeout escape as ConnectionException', function () {
    PayCrossBorderApi::fake(['payout' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->payout([]))->toThrow(ConnectionException::class);
});

it('reads the wallet list for the wallet owner', function () {
    PayCrossBorderApi::fake();

    $balance = pcbClient()->balance('1000008444');

    expect($balance['walletList'])->toHaveCount(2);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://pcb.test/ewallet/api/v1/wallet/walletOwner/1000008444');
});

it('turns a read timeout into a PaymentProcessingException', function () {
    PayCrossBorderApi::fake(['balance' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->balance('1000008444'))
        ->toThrow(PaymentProcessingException::class, 'cURL error 28');
});
