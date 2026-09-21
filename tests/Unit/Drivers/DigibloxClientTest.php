<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function digibloxClient(): DigibloxClient
{
    return new DigibloxClient(
        baseUrl: 'https://digiblox.test',
        username: 'merchant_alpha',
        apiKey: 'key',
        apiSecret: 'secret',
        merchantId: 'bkE0RmNjbEhCUmc9',
    );
}

function fakeLogin(string $token = 'jwt-token'): array
{
    return [
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response([
            'iss' => 'Crymbo-API',
            'iat' => 1785926400,
            'exp' => 1785930000,
            'type' => 'Bearer',
            'token' => $token,
        ]),
    ];
}

beforeEach(function () {
    Cache::flush();
});

it('exchanges credentials for a token and sends it as a bearer header', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['paymentLink' => 'https://widget.digiblox.io/widget/auth?paymentId=X'], 201,
        ),
    ]));

    digibloxClient()->createPaymentLink(['payload' => ['external_id' => 'DEP-1'], 'notification' => []]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/auth/login/jwt')
        && $request['username'] === 'merchant_alpha'
        && $request['api_key'] === 'key'
        && $request['api_secret'] === 'secret');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/guests')
        && $request->header('Authorization') === ['Bearer jwt-token']);
});

it('caches the token across calls rather than minting one per request', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();
    $client->createPaymentLink(['payload' => ['external_id' => 'A'], 'notification' => []]);
    $client->createPaymentLink(['payload' => ['external_id' => 'B'], 'notification' => []]);

    // A new token would have invalidated the first — exactly one login.
    Http::assertSentCount(3);
});

it('keys the token per merchant account so two connections cannot share one', function () {
    Http::fake(fakeLogin());

    $a = new DigibloxClient('https://digiblox.test', 'a', 'k', 's', 'M-A');
    $b = new DigibloxClient('https://digiblox.test', 'b', 'k', 's', 'M-B');

    expect($a->tokenCacheKey())->not->toBe($b->tokenCacheKey());
});

it('does not decode the plain-string body a failed login returns', function () {
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response('Invalid Credentials', 401),
    ]);

    expect(fn () => digibloxClient()->createPaymentLink(['payload' => [], 'notification' => []]))
        ->toThrow(Asciisd\CashierCore\Exceptions\PaymentProcessingException::class, 'authenticate');
});

it('forgets a cached token on demand', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();
    $client->createPaymentLink(['payload' => ['external_id' => 'A'], 'notification' => []]);
    $client->forgetToken();
    $client->createPaymentLink(['payload' => ['external_id' => 'B'], 'notification' => []]);

    Http::assertSentCount(4); // two logins, two creates
});
