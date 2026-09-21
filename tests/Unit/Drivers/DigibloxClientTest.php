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

it('clears the cached token on a 401 so the next call re-mints rather than wedging for the TTL', function () {
    // A 401 here means our cached token was superseded out-of-band (e.g. an
    // ops login on the Digiblox dashboard for the same merchant, which
    // invalidates the previous token). Without dropping the cache, every
    // subsequent call would fail with the same 401 for up to the 3300s TTL.
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::sequence()
            ->push('Unauthorized', 401)
            ->push(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();

    expect(fn () => $client->createPaymentLink(['payload' => ['external_id' => 'A'], 'notification' => []]))
        ->toThrow(Asciisd\CashierCore\Exceptions\PaymentProcessingException::class);

    // The 401 above must have forgotten the cached token on its own — no
    // explicit forgetToken() call here — so this second call re-mints.
    $client->createPaymentLink(['payload' => ['external_id' => 'B'], 'notification' => []]);

    Http::assertSentCount(4); // two logins, two creates
});

it('treats a 500 on createPaymentLink as ambiguous, distinct from a clean 4xx rejection', function () {
    // Per spec pitfall #13: on a 500 the link may already exist, and the same
    // external_id must not be resent blind.
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response('Internal Server Error', 500),
    ]));

    expect(fn () => digibloxClient()->createPaymentLink(['payload' => ['external_id' => 'DEP-1'], 'notification' => []]))
        ->toThrow(Asciisd\CashierCore\Exceptions\PaymentProcessingException::class, 'do NOT resend');
});

it('throws with error message when createPaymentLink receives a 400 with message as string', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['message' => 'external_id: DEP-1 is already used'],
            400,
        ),
    ]));

    expect(fn () => digibloxClient()->createPaymentLink(['payload' => ['external_id' => 'DEP-1'], 'notification' => []]))
        ->toThrow(
            Asciisd\CashierCore\Exceptions\PaymentProcessingException::class,
            'external_id: DEP-1 is already used',
        );
});

it('throws with all error messages when createPaymentLink receives a 400 with message as array', function () {
    Http::fake(array_merge(fakeLogin(), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(
            ['message' => ['external_id must be a string', 'fiat_amount must have 2 decimals']],
            400,
        ),
    ]));

    expect(fn () => digibloxClient()->createPaymentLink(['payload' => [], 'notification' => []]))
        ->toThrow(
            Asciisd\CashierCore\Exceptions\PaymentProcessingException::class,
            'external_id must be a string; fiat_amount must have 2 decimals',
        );
});

it('mints a token anyway when the refresh lock times out, rather than failing the payment', function () {
    Cache::flush();
    Http::fake(array_merge(fakeLogin('first-token'), fakeLogin('second-token'), [
        'https://digiblox.test/gateway/api/v1/v3/payments/guests' => Http::response(['paymentLink' => 'u'], 201),
    ]));

    $client = digibloxClient();

    // Acquire the lock to simulate a slow lock holder.
    $lock = Cache::lock($client->tokenCacheKey().':refresh', 10);
    $lock->get();

    try {
        // This call should time out waiting for the lock, then mint anyway.
        $client->createPaymentLink(['payload' => ['external_id' => 'DEP-1'], 'notification' => []]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/guests'));
    } finally {
        $lock->release();
    }
});

describe('searchDeposits', function () {
    it('sends the fixed query string, varying only fV', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
                ['totalItems' => 0, 'result' => []],
            ),
        ]));

        digibloxClient()->searchDeposits('DEP-42');

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return str_contains($request->url(), '/v3/deposits/merchant')
                && $query['fV'] === 'DEP-42'
                && $query['fB'] === 'external_transaction_id'
                && $query['fO'] === 'EQ'
                && $query['fT'] === 'S'
                && $query['sB'] === 'created_at'
                && $query['sD'] === 'desc'
                && $query['limit'] === '25'
                && $query['offset'] === '0';
        });
    });

    it('returns an empty list for totalItems 0 without treating it as an error', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
                ['totalItems' => 0, 'result' => []],
            ),
        ]));

        expect(digibloxClient()->searchDeposits('DEP-unknown'))->toBe([]);
    });

    it('returns every row, not just the first — one link can take several payments', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response([
                'totalItems' => 2,
                'result' => [
                    ['external_transaction_id' => 'DEP-1', 'status' => 'CONFIRMED', 'tx_hash' => '0xaaa'],
                    ['external_transaction_id' => 'DEP-1', 'status' => 'CONFIRMED', 'tx_hash' => '0xbbb'],
                ],
            ]),
        ]));

        $rows = digibloxClient()->searchDeposits('DEP-1');

        expect($rows)->toHaveCount(2)
            ->and(array_column($rows, 'tx_hash'))->toBe(['0xaaa', '0xbbb']);
    });

    it('throws with error message on non-2xx response, not returning an empty array', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/deposits/merchant*' => Http::response(
                ['message' => 'Invalid merchant ID'],
                401,
            ),
        ]));

        expect(fn () => digibloxClient()->searchDeposits('DEP-1'))
            ->toThrow(
                Asciisd\CashierCore\Exceptions\PaymentProcessingException::class,
                'Invalid merchant ID',
            );
    });
});

describe('guest flow', function () {
    it('reports a missing guest as a normal 200, not an error', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/check-guest-exists' => Http::response(
                ['exists' => false, 'id' => null],
            ),
        ]));

        expect(digibloxClient()->checkGuestExists('nobody@test.dev'))
            ->toBe(['exists' => false, 'id' => null]);
    });

    it('returns the permanent user id for an existing guest', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/check-guest-exists' => Http::response(
                ['exists' => true, 'id' => 'V0FrTk5FR3NUeE9wL2lqcE9Rc2h2Zz09'],
            ),
        ]));

        expect(digibloxClient()->checkGuestExists('known@test.dev'))
            ->toBe(['exists' => true, 'id' => 'V0FrTk5FR3NUeE9wL2lqcE9Rc2h2Zz09']);
    });

    it('registers a guest with the email beside the pii object, not inside it', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/create-guest-with-pii' => Http::response(
                ['message' => 'user create/updated successfully'],
            ),
        ]));

        $created = digibloxClient()->createGuestWithPii('new@test.dev', [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dob' => '1990-01-01',
            'phone' => '+15551234567',
            'address' => '123 Main Street',
            'city' => 'New York',
            'country' => 'USA',
            'zipCode' => '10001',
        ]);

        expect($created)->toBeTrue();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/auth/create-guest-with-pii')
            && $request['email'] === 'new@test.dev'
            && $request['pii']['country'] === 'USA'
            && ! isset($request['pii']['email']));
    });

    it('surfaces every entry when field validation returns an array of messages', function () {
        Http::fake(array_merge(fakeLogin(), [
            'https://digiblox.test/gateway/api/v1/v3/auth/create-guest-with-pii' => Http::response([
                'message' => ['firstName string is required', 'lastName string is required'],
            ], 400),
        ]));

        expect(fn () => digibloxClient()->createGuestWithPii('bad@test.dev', []))
            ->toThrow(
                Asciisd\CashierCore\Exceptions\PaymentProcessingException::class,
                'firstName string is required; lastName string is required',
            );
    });
});
