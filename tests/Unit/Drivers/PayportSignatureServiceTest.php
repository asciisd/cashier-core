<?php

use Asciisd\CashierCore\Drivers\Payport\PayportSignatureService;

/**
 * The reference implementation from Payport's documentation, reproduced here so
 * the service is checked against the vendor's algorithm rather than against
 * itself.
 */
function payportReferenceSignature(array $params, string $apiKey): string
{
    $flatten = function (array $params) use (&$flatten): string {
        $data = array_filter($params, fn ($var) => $var !== '' && $var !== null);
        ksort($data);

        $out = '';
        foreach ($data as $value) {
            $out .= is_array($value) ? $flatten($value) : '|'.trim((string) $value);
        }

        return $out;
    };

    return strtolower(sha1($apiKey.$flatten($params)));
}

it('matches the vendor reference implementation', function () {
    $params = [
        'invoice_id' => 1,
        'merchant_id' => 1,
        'order_id' => '1',
        'amount' => 100,
        'amount_currency' => 1300000,
        'currency' => 'UZS',
        'order_desc' => null,
        'merchant_amount' => 100,
        'status' => 1,
        'account_info' => '111111******8888',
        'fiat_currency' => 'UZS',
        'fiat_amount' => 1300000,
        'payment_system_type' => 'card_number',
    ];

    $service = new PayportSignatureService('api5-key');

    expect($service->sign($params))->toBe(payportReferenceSignature($params, 'api5-key'));
});

it('verifies a correctly signed callback', function () {
    $service = new PayportSignatureService('api5-key');

    $payload = ['invoice_id' => '99', 'order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = $service->sign($payload);

    expect($service->verify($payload))->toBeTrue();
});

it('rejects a callback whose payload was tampered with', function () {
    $service = new PayportSignatureService('api5-key');

    $payload = ['invoice_id' => '99', 'order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = $service->sign($payload);
    $payload['status'] = '-1';

    expect($service->verify($payload))->toBeFalse();
});

it('rejects a callback signed with a different api key', function () {
    $signer = new PayportSignatureService('other-merchant-key');

    $payload = ['invoice_id' => '99', 'order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = $signer->sign($payload);

    expect((new PayportSignatureService('api5-key'))->verify($payload))->toBeFalse();
});

// Payport signs a callback with "Api3_key or Api5_key, depending on which Api
// the invoice was created using", so the charging key is not always the one that
// signed the callback coming back.
it('accepts a callback signed with an alternate key', function () {
    $payload = ['invoice_id' => '99', 'order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = (new PayportSignatureService('api3-key'))->sign($payload);

    $service = new PayportSignatureService('api5-key', ['api3-key']);

    expect($service->verify($payload))->toBeTrue()
        // Our own requests are still signed with the primary key only.
        ->and($service->sign(['a' => '1']))->toBe((new PayportSignatureService('api5-key'))->sign(['a' => '1']));
});

it('still rejects a callback signed with a key that is on neither list', function () {
    $payload = ['invoice_id' => '99', 'order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = (new PayportSignatureService('someone-elses-key'))->sign($payload);

    expect((new PayportSignatureService('api5-key', ['api3-key']))->verify($payload))->toBeFalse();
});

it('ignores a blank alternate key rather than trusting the empty string', function () {
    $payload = ['order_id' => 'DEP-1', 'status' => '1'];
    $payload['signature'] = strtolower(sha1('|DEP-1|1'));

    expect((new PayportSignatureService('api5-key', ['']))->verify($payload))->toBeFalse();
});

it('rejects a callback with no signature at all', function () {
    expect((new PayportSignatureService('api5-key'))->verify(['order_id' => 'DEP-1']))->toBeFalse();
});

it('excludes the signature field from its own digest', function () {
    $service = new PayportSignatureService('api5-key');

    $payload = ['order_id' => 'DEP-1', 'status' => '1'];
    $signed = $payload + ['signature' => $service->sign($payload)];

    // Signing the payload with the signature still attached must not reproduce
    // it — otherwise verify() would be comparing the digest against itself.
    expect($service->sign($signed))->not->toBe($signed['signature'])
        ->and($service->verify($signed))->toBeTrue();
});

it('ignores empty and null values, matching the vendor filter', function () {
    $service = new PayportSignatureService('api5-key');

    expect($service->sign(['a' => '1', 'b' => '', 'c' => null, 'd' => '2']))
        ->toBe($service->sign(['a' => '1', 'd' => '2']));
});

it('orders values by key rather than by insertion', function () {
    $service = new PayportSignatureService('api5-key');

    expect($service->sign(['status' => '1', 'amount' => '50']))
        ->toBe($service->sign(['amount' => '50', 'status' => '1']));
});

it('walks nested groups such as payment_info', function () {
    $service = new PayportSignatureService('api5-key');

    $nested = ['order_id' => 'DEP-1', 'payment_info' => ['upi_id' => '0123456']];

    expect($service->sign($nested))
        ->toBe(payportReferenceSignature($nested, 'api5-key'))
        // The nested value contributes to the digest — a group that was ignored
        // would let an attacker rewrite the payee details freely.
        ->and($service->sign($nested))->not->toBe($service->sign(['order_id' => 'DEP-1']));
});
