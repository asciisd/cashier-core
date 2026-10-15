<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Aps\ApsProvider;
use Asciisd\CashierCore\Drivers\Aps\ApsRequestSigner;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The throwaway key pair from APS's published test vector.
const APS_TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIAAgroGhza8H+6iRgbrSENejPZyCx9jyyuN0MH48r++zoAoGCCqGSM49
AwEHoUQDQgAEdN3uINmPVb1oVuQb3NdP51Qy9eggH8MOyK3DuLlONNRtnnJq+7AX
h4wMLgJklRozEvknH/7Zt2M3qkEyEXvqlg==
-----END EC PRIVATE KEY-----
PEM;

const APS_TEST_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEdN3uINmPVb1oVuQb3NdP51Qy9egg
H8MOyK3DuLlONNRtnnJq+7AXh4wMLgJklRozEvknH/7Zt2M3qkEyEXvqlg==
-----END PUBLIC KEY-----
PEM;

const APS_TEST_URL = 'https://fpf-api.proc-gw.com/api/v3/61c21492-fd83-4180-adf6-8b0f4e0423bc/transactions';
const APS_TEST_BODY = '{"amount":100.2,"fields":{"transaction":{"deposit_method":"06d34870-a9d3-4baa-afce-995c1f385d1d:81180240-65f1-4ed0-9f1f-be1e0b4fa985"}}}';

function apsVerifies(string $signedData, string $signature): bool
{
    return openssl_verify($signedData, (string) base64_decode($signature, true), APS_TEST_PUBLIC_KEY, OPENSSL_ALGO_SHA256) === 1;
}

it('accepts the signature APS publishes for its test vector', function () {
    $signedData = '2026-09-26T10:00:00ZPOST'.APS_TEST_URL.APS_TEST_BODY;

    expect(apsVerifies($signedData, 'MEYCIQDZZ6R/7gvoXqzKC3Nf0K5M0XC4PQ1y+Q2MJCRx8Rr0ggIhAIImtw8QWwuUE/bZJAGe+mAg6D3brWrOPfnLNSETQpVj'))->toBeTrue();
});

it('signs timestamp, method, url and body glued together', function () {
    $signer = new ApsRequestSigner('token', APS_TEST_PRIVATE_KEY);

    $signed = $signer->sign(new PsrRequest('post', APS_TEST_URL, [], APS_TEST_BODY), '2026-09-26T10:00:00Z');

    expect($signed->getHeaderLine('X-App-Token'))->toBe('token')
        ->and($signed->getHeaderLine('X-Sign-Timestamp'))->toBe('2026-09-26T10:00:00Z')
        ->and($signed->hasHeader('X-App-Secret'))->toBeFalse()
        ->and(apsVerifies('2026-09-26T10:00:00ZPOST'.APS_TEST_URL.APS_TEST_BODY, $signed->getHeaderLine('X-Signature')))->toBeTrue();
});

it('signs a bodiless GET with its percent-escapes left untouched', function () {
    $url = 'https://fpf-api.proc-gw.com/api/v3/guid/info?payment_methods=1d21c7e3%3A492e48b4';
    $signer = new ApsRequestSigner('token', APS_TEST_PRIVATE_KEY);

    $signed = $signer->sign(new PsrRequest('GET', $url), '2026-09-26T10:00:00Z');

    expect((string) $signed->getUri())->toBe($url)
        ->and(apsVerifies('2026-09-26T10:00:00ZGET'.$url, $signed->getHeaderLine('X-Signature')))->toBeTrue();
});

it('sorts and lower-cases query names on the wire and in the signature', function () {
    $signer = new ApsRequestSigner('token', APS_TEST_PRIVATE_KEY);

    $signed = $signer->sign(new PsrRequest('GET', 'http://FPF-API.proc-gw.com/x?Foo=Bar&AAA=Test'), '2026-09-26T10:00:00Z');

    $expected = 'https://fpf-api.proc-gw.com/x?aaa=Test&foo=Bar';

    expect((string) $signed->getUri())->toBe($expected)
        ->and(apsVerifies('2026-09-26T10:00:00ZGET'.$expected, $signed->getHeaderLine('X-Signature')))->toBeTrue();
});

it('stamps the current time in RFC 3339 UTC', function () {
    $signed = (new ApsRequestSigner('token', APS_TEST_PRIVATE_KEY))->sign(new PsrRequest('GET', APS_TEST_URL));

    expect($signed->getHeaderLine('X-Sign-Timestamp'))->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/');
});

it('reads the key from a file path or from an env value with escaped newlines', function () {
    $path = tempnam(sys_get_temp_dir(), 'aps');
    file_put_contents($path, APS_TEST_PRIVATE_KEY);

    foreach ([$path, str_replace("\n", '\n', APS_TEST_PRIVATE_KEY)] as $key) {
        $signed = (new ApsRequestSigner('token', $key))->sign(new PsrRequest('GET', APS_TEST_URL), '2026-09-26T10:00:00Z');

        expect(apsVerifies('2026-09-26T10:00:00ZGET'.APS_TEST_URL, $signed->getHeaderLine('X-Signature')))->toBeTrue();
    }

    unlink($path);
});

it('refuses a key it cannot load', function () {
    new ApsRequestSigner('token', 'not a key');
})->throws(PaymentProcessingException::class);

it('signs provider requests when a private key is configured', function () {
    Http::fake(['*' => Http::response(['id' => 'tx-1'])]);

    $provider = new ApsProvider([
        'base_url' => 'https://fpf-api.proc-gw.com',
        'merchant_guid' => 'merchant-guid',
        'app_token' => 'token',
        'private_key' => APS_TEST_PRIVATE_KEY,
        'callback_secret' => 'callback-secret',
    ]);

    $provider->refund('tx-1', 10.5);

    Http::assertSent(function (Request $request) {
        $signedData = $request->header('X-Sign-Timestamp')[0].'POST'.$request->url().$request->body();

        return $request->url() === 'https://fpf-api.proc-gw.com/api/v3/merchant-guid/tx-1/refund'
            && ! $request->hasHeader('X-App-Secret')
            && apsVerifies($signedData, $request->header('X-Signature')[0]);
    });
});

it('keeps the legacy secret header when no private key is configured', function () {
    Http::fake(['*' => Http::response(['id' => 'tx-1'])]);

    (new ApsProvider(['merchant_guid' => 'g', 'app_token' => 'token', 'app_secret' => 'secret']))->refund('tx-1');

    Http::assertSent(fn (Request $request) => $request->header('X-App-Secret') === ['secret']
        && ! $request->hasHeader('X-Signature'));
});

it('requires a private key or an app secret', function () {
    new ApsProvider(['merchant_guid' => 'g', 'app_token' => 'token']);
})->throws(PaymentProcessingException::class);

it('rejects every callback when no secret is configured at all', function () {
    $provider = new ApsProvider(['merchant_guid' => 'g', 'app_token' => 'token', 'private_key' => APS_TEST_PRIVATE_KEY]);

    expect($provider->verifyRawSignature('{}', hash_hmac('sha256', '{}', '')))->toBeFalse();
});
