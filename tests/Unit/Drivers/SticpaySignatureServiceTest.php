<?php

use Asciisd\CashierCore\Drivers\Sticpay\SticpaySignatureService;

/**
 * The reference implementation from Sticpay's documentation (§2-2, §2-6 B),
 * reproduced here so the service is checked against the vendor's algorithm
 * rather than against itself.
 */
function sticpayReferenceSignature(array $ordered, string $apiKey, string $signType = 'MD5'): string
{
    $pairs = [];

    foreach ($ordered as $field => $value) {
        $pairs[] = "{$field}={$value}";
    }

    $pairs[] = "key={$apiKey}";

    $canonical = implode('&', $pairs);

    return strtoupper($signType) === 'SHA256'
        ? strtolower(hash('sha256', $canonical))
        : strtolower(md5($canonical));
}

function sticpayPayParams(array $overrides = []): array
{
    return array_merge([
        'merchant_email' => 'merchant@sticpay.com',
        'order_no' => '1234',
        'order_time' => '2018-03-14 20:52:10',
        'order_amount' => '125.03',
        'order_currency' => 'USD',
    ], $overrides);
}

function sticpayCallbackParams(array $overrides = []): array
{
    return array_merge(sticpayPayParams(), [
        'transaction_code' => 'ftjok58jg',
        'transaction_time' => '2018-03-13 15:05:14',
    ], $overrides);
}

it('builds the canonical string documented in section 2-2', function () {
    $service = new SticpaySignatureService('your_api_key');

    // Asserted literally, not just via its digest: the string IS the contract,
    // and a digest-only assertion would pass against a wrong string that the
    // reference helper happens to reproduce.
    expect($service->canonical(sticpayPayParams(), SticpaySignatureService::PAY))
        ->toBe('merchant_email=merchant@sticpay.com&order_no=1234&order_time=2018-03-14 20:52:10'
            .'&order_amount=125.03&order_currency=USD&key=your_api_key');
});

it('builds the canonical callback string documented in section 2-6', function () {
    $service = new SticpaySignatureService('your_api_key');

    expect($service->canonical(sticpayCallbackParams(), SticpaySignatureService::CALLBACK))
        ->toBe('merchant_email=merchant@sticpay.com&order_no=1234&order_time=2018-03-14 20:52:10'
            .'&order_amount=125.03&order_currency=USD&transaction_code=ftjok58jg'
            .'&transaction_time=2018-03-13 15:05:14&key=your_api_key');
});

it('matches the vendor reference implementation', function (string $signType) {
    $service = new SticpaySignatureService('your_api_key', $signType);

    expect($service->sign(sticpayPayParams(), SticpaySignatureService::PAY))
        ->toBe(sticpayReferenceSignature(sticpayPayParams(), 'your_api_key', $signType));
})->with(['MD5', 'SHA256']);

/*
 * The regression this whole class exists to prevent. Payport ksorts its
 * signature payload; Sticpay does not. A service that sorted would produce a
 * plausible-looking hash that Sticpay rejects with 809 on every request.
 */
it('signs in the documented field order rather than by key', function () {
    $service = new SticpaySignatureService('your_api_key');

    $inOrder = sticpayPayParams();
    $reversed = array_reverse($inOrder, preserve_keys: true);

    $ksorted = $inOrder;
    ksort($ksorted);

    expect($service->sign($reversed, SticpaySignatureService::PAY))
        ->toBe($service->sign($inOrder, SticpaySignatureService::PAY))
        ->and($service->canonical($inOrder, SticpaySignatureService::PAY))
        ->not->toBe(implode('&', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($ksorted),
            $ksorted,
        )).'&key=your_api_key');
});

it('produces a different digest for each hash type', function () {
    $params = sticpayPayParams();

    expect((new SticpaySignatureService('your_api_key', 'MD5'))->sign($params, SticpaySignatureService::PAY))
        ->not->toBe((new SticpaySignatureService('your_api_key', 'SHA256'))->sign($params, SticpaySignatureService::PAY));
});

it('throws rather than signing a string that is missing a required field', function () {
    $service = new SticpaySignatureService('your_api_key');

    // Silently signing a short string is the failure mode this guards: it
    // yields a well-formed hash that never verifies, with nothing to show why.
    $service->canonical(sticpayPayParams(['order_currency' => null]), SticpaySignatureService::PAY);
})->throws(InvalidArgumentException::class, 'order_currency');

it('treats an empty string as a missing field', function () {
    $service = new SticpaySignatureService('your_api_key');

    $service->canonical(sticpayPayParams(['order_no' => '']), SticpaySignatureService::PAY);
})->throws(InvalidArgumentException::class, 'order_no');

it('verifies a correctly signed callback', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = sticpayCallbackParams();
    $params['sign'] = $service->sign($params, SticpaySignatureService::CALLBACK);

    expect($service->verifyCallback($params))->toBeTrue();
});

/*
 * §2-6 B says the callback digest is MD5, but the callback carries its own
 * `sign_type` field and §1 says the encryption type is a merchant-side setting.
 * Accepting either is the only reading under which all three statements hold.
 */
it('accepts a callback signed with the other hash type', function (string $configured, string $arriving) {
    $signer = new SticpaySignatureService('your_api_key', $arriving);

    $params = sticpayCallbackParams(['sign_type' => $arriving]);
    $params['sign'] = $signer->sign($params, SticpaySignatureService::CALLBACK);

    expect((new SticpaySignatureService('your_api_key', $configured))->verifyCallback($params))->toBeTrue();
})->with([
    'md5 configured, sha256 arriving' => ['MD5', 'SHA256'],
    'sha256 configured, md5 arriving' => ['SHA256', 'MD5'],
]);

it('accepts a callback whose sign_type field is absent or wrong', function () {
    $signer = new SticpaySignatureService('your_api_key', 'SHA256');

    $params = sticpayCallbackParams();
    $params['sign'] = $signer->sign($params, SticpaySignatureService::CALLBACK);

    // The payload's claim is a hint, not a gate — every supported type is tried.
    expect((new SticpaySignatureService('your_api_key', 'MD5'))->verifyCallback($params))->toBeTrue()
        ->and((new SticpaySignatureService('your_api_key', 'MD5'))->verifyCallback($params + ['sign_type' => 'GOST']))->toBeTrue();
});

it('rejects a callback whose amount was altered after signing', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = sticpayCallbackParams();
    $params['sign'] = $service->sign($params, SticpaySignatureService::CALLBACK);
    $params['order_amount'] = '99999.00';

    expect($service->verifyCallback($params))->toBeFalse();
});

it('rejects a callback signed with a different api key', function () {
    $params = sticpayCallbackParams();
    $params['sign'] = (new SticpaySignatureService('someone-elses-key'))->sign($params, SticpaySignatureService::CALLBACK);

    expect((new SticpaySignatureService('your_api_key'))->verifyCallback($params))->toBeFalse();
});

it('rejects a callback with no signature at all', function () {
    expect((new SticpaySignatureService('your_api_key'))->verifyCallback(sticpayCallbackParams()))->toBeFalse();
});

it('rejects a callback that is missing a signed field instead of throwing', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = sticpayCallbackParams();
    $params['sign'] = $service->sign($params, SticpaySignatureService::CALLBACK);
    unset($params['transaction_code']);

    // An unverifiable callback is a rejected one — the controller must answer
    // 403, not 500.
    expect($service->verifyCallback($params))->toBeFalse();
});

it('excludes the sign field from its own digest', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = sticpayCallbackParams();
    $signed = $params + ['sign' => $service->sign($params, SticpaySignatureService::CALLBACK)];

    expect($service->verifyCallback($signed))->toBeTrue()
        // Extra fields never enter the digest, so a callback carrying more than
        // the seven signed ones still verifies.
        ->and($service->verifyCallback($signed + ['fee' => '442', 'customer_email' => 'a@b.test']))->toBeTrue();
});

it('puts interface_version last before the key on a withdraw signature', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = [
        'merchant' => 'merchant@sticpay.com',
        'customer' => 'customer@sticpay.com',
        'amount' => '100',
        'currency_code' => 'USD',
        'interface_version' => 'live',
    ];

    expect($service->canonical($params, SticpaySignatureService::WITHDRAW))
        ->toBe('merchant=merchant@sticpay.com&customer=customer@sticpay.com&amount=100'
            .'&currency_code=USD&interface_version=live&key=your_api_key')
        ->and($service->canonical($params + ['order_id' => '1234'], SticpaySignatureService::WITHDRAW_WITH_ORDER))
        ->toBe('merchant=merchant@sticpay.com&customer=customer@sticpay.com&amount=100'
            .'&currency_code=USD&order_id=1234&interface_version=live&key=your_api_key');
});

/*
 * §5.2 documents the transaction-detail signature without `interface_version`;
 * §6.2 documents the same lookup with it. Both orders are kept so the client
 * can retry once when Sticpay answers 809.
 */
it('offers both readings of the transaction-detail signature', function () {
    $service = new SticpaySignatureService('your_api_key');

    $params = [
        'merchant' => 'merchant@sticpay.com',
        'order_id' => '1234',
        'request_datetime' => '2018-12-31 14:00:00',
        'interface_version' => 'sandbox',
    ];

    expect($service->canonical($params, SticpaySignatureService::DETAIL_BY_ORDER))
        ->toBe('merchant=merchant@sticpay.com&order_id=1234&request_datetime=2018-12-31 14:00:00'
            .'&interface_version=sandbox&key=your_api_key')
        ->and($service->canonical($params, SticpaySignatureService::DETAIL_BY_ORDER_LEGACY))
        ->toBe('merchant=merchant@sticpay.com&order_id=1234&request_datetime=2018-12-31 14:00:00'
            .'&key=your_api_key');
});
