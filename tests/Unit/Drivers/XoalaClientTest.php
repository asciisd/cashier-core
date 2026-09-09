<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function xoalaClient(): XoalaClient
{
    return new XoalaClient('https://xoala.test', '11344', 'secure-key', 'xoala');
}

beforeEach(function () {
    Cache::flush();
});

it('fetches a token, then inquires with it in the authtoken header', function () {
    Http::fake([
        'https://xoala.test/transactionServices/REST/v1/authToken' => Http::response([
            'result' => ['code' => '200', 'description' => 'Token generated successfully'],
            'AuthToken' => 'jwt-token',
        ]),
        'https://xoala.test/transactionServices/REST/v1/inquiry' => Http::response([
            'paymentId' => '54289',
            'status' => 'capturesuccess',
            'amount' => '1.00',
            'result' => ['code' => '00026', 'description' => 'Your record found successfully'],
        ]),
    ]);

    $data = xoalaClient()->inquiry('DEP-1', 'the-checksum');

    expect($data)->not->toBeNull()
        ->and($data['paymentId'])->toBe('54289');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/inquiry')
            && $request->header('authtoken') === ['jwt-token']
            && $request['paymentType'] === 'IN'
            && $request['idType'] === 'MID'
            && $request['merchantTransactionId'] === 'DEP-1'
            && $request['authentication.memberId'] === '11344'
            && $request['authentication.checksum'] === 'the-checksum';
    });
});

it('sends the secure key as authentication.sKey and never as a bare field', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    xoalaClient()->inquiry('DEP-1', 'the-checksum');

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/authToken')) {
            return true;
        }

        return $request['authentication.sKey'] === 'secure-key'
            && $request['authentication.memberId'] === '11344';
    });
});

it('caches the token across calls', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c1');
    $client->inquiry('DEP-2', 'c2');

    // One token fetch, two inquiries.
    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/authToken'))
        ->count();

    expect($tokenCalls)->toBe(1);
});

it('caches the token per connection, not globally', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    (new XoalaClient('https://xoala.test', '1', 'k1', 'xoala'))->inquiry('DEP-1', 'c');
    (new XoalaClient('https://xoala.test', '2', 'k2', 'xoala_second'))->inquiry('DEP-2', 'c');

    // Two token fetches, two inquiries — a shared cache key would send three.
    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/authToken'))
        ->count();

    expect($tokenCalls)->toBe(2);
});

it('returns null when the token cannot be obtained', function () {
    Http::fake([
        '*/authToken' => Http::response(['result' => ['code' => '401']], 401),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/inquiry'));
});

it('returns null when the inquiry itself fails', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response('gateway down', 502),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('returns null when the inquiry answers with HTML rather than JSON', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response('<html>blocked</html>', 200),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('returns null when the record is not found, rather than inventing a status', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response([
            'result' => ['code' => '10009', 'description' => 'Record not found'],
        ]),
    ]);

    expect(xoalaClient()->inquiry('DEP-1', 'c'))->toBeNull();
});

it('drops the cached token when Xoala rejects it, so the next call re-authenticates', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['result' => ['code' => '401']], 401),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c');
    $client->inquiry('DEP-2', 'c');

    // Two token fetches, not one: a token cached for 55 minutes after Xoala
    // stopped honouring it would fail every sync in that window.
    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/authToken'))
        ->count();

    expect($tokenCalls)->toBe(2);
});

it('drops a cached token on request so the next call re-authenticates', function () {
    Http::fake([
        '*/authToken' => Http::response(['AuthToken' => 'jwt-token']),
        '*/inquiry' => Http::response(['paymentId' => '1', 'status' => 'begun']),
    ]);

    $client = xoalaClient();
    $client->inquiry('DEP-1', 'c');
    $client->forgetToken();
    $client->inquiry('DEP-2', 'c');

    // Two token fetches because the cache was cleared between inquiries.
    $tokenCalls = collect(Http::recorded())
        ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/authToken'))
        ->count();

    expect($tokenCalls)->toBe(2);
});
