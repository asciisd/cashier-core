<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;

/*
 * The composition rules come from checkout-docs.xoala.com:
 *   checkout : memberId|totype|amount|merchantTransactionId|merchantRedirectUrl|secureKey
 *   callback : paymentId|merchantTransactionId|amount|status|secureKey
 *   inquiry  : memberId|secureKey|<id>
 * Each is hashed here against a literal md5() of the documented string, so the
 * service is checked against the vendor's rule rather than against itself.
 */

it('signs a checkout request the way the docs compose it', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    expect($service->forCheckout('PartnerName', '50.00', 'DEP-1', 'https://app.test/return'))
        ->toBe(md5('11344|PartnerName|50.00|DEP-1|https://app.test/return|secure-key'));
});

it('reproduces the callback example printed in the documentation', function () {
    // The docs' worked example: 77251|011E1D8A5C034|156.00|N|<merchant secret key>
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    expect($service->forCallback('77251', '011E1D8A5C034', '156.00', 'N'))
        ->toBe(md5('77251|011E1D8A5C034|156.00|N|merchant-secret'));
});

it('verifies a callback signed with the short transactionStatus', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'status' => 'capturesuccess',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');

    expect($service->verifyCallback($payload))->toBeTrue();
});

it('verifies a redirect-back POST, where the short status arrives as `status`', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'status' => 'N',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'N');

    expect($service->verifyCallback($payload))->toBeTrue();
});

it('rejects a callback whose amount was tampered with', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.00',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');
    $payload['amount'] = '1560.00';

    expect($service->verifyCallback($payload))->toBeFalse();
});

it('rejects a callback carrying no checksum at all', function () {
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    expect($service->verifyCallback(['paymentId' => '77251', 'amount' => '1.00']))->toBeFalse();
});

it('does not treat a differently formatted amount as equivalent', function () {
    // The digest is over the STRING. 156.0 and 156.00 are different inputs, and
    // quietly reformatting one to match the other would be forging agreement.
    $service = new XoalaSignatureService('11344', 'merchant-secret');

    $payload = [
        'paymentId' => '77251',
        'merchantTransactionId' => '011E1D8A5C034',
        'amount' => '156.0',
        'transactionStatus' => 'Y',
    ];
    $payload['checksum'] = $service->forCallback('77251', '011E1D8A5C034', '156.00', 'Y');

    expect($service->verifyCallback($payload))->toBeFalse();
});

it('signs an inquiry with the id actually being sent', function () {
    $service = new XoalaSignatureService('11344', 'secure-key');

    expect($service->forInquiry('DEP-1'))->toBe(md5('11344|secure-key|DEP-1'));
});

it('formats amounts to the two-decimal shape the checksum is computed over', function () {
    expect(XoalaSignatureService::amount(50))->toBe('50.00')
        ->and(XoalaSignatureService::amount(156.5))->toBe('156.50')
        ->and(XoalaSignatureService::amount('1000'))->toBe('1000.00')
        ->and(XoalaSignatureService::amount(0.1 + 0.2))->toBe('0.30');
});
