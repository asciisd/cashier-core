<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Myfatoorah\MyfatoorahSignatureService;

beforeEach(function () {
    $this->service = new MyfatoorahSignatureService('test-webhook-secret');
});

/**
 * The `Data` object of the PAYMENT_STATUS_CHANGED sample event, verbatim from
 * references/webhooks.md, trimmed to the fields the signature reads plus
 * enough neighbours to prove the builder ignores them.
 *
 * @return array<string, mixed>
 */
function myfatoorahPaymentEventData(): array
{
    return [
        'Invoice' => [
            'Id' => '6409988',
            'Status' => 'PAID',
            'Reference' => '2026000073',
            'ExternalIdentifier' => 'asdqwd-f13sdf-fasjkz',
        ],
        'Transaction' => [
            'Id' => '86781',
            'Status' => 'SUCCESS',
            'PaymentId' => '07076409988323998875',
            'PaymentMethod' => 'VISA/MASTER',
        ],
        'Amount' => [
            'DisplayCurrency' => 'KWD',
            'ValueInDisplayCurrency' => '1',
        ],
    ];
}

describe('canonical', function () {
    it('builds the string MyFatoorah documents for PAYMENT_STATUS_CHANGED', function () {
        // Transcribed literally from references/webhooks.md, the "Webhook
        // Signature" block under the Payment Status Data Model.
        $expected = 'Invoice.Id=6409988,Invoice.Status=PAID,Transaction.Status=SUCCESS,Transaction.PaymentId=07076409988323998875,Invoice.ExternalIdentifier=asdqwd-f13sdf-fasjkz';

        expect($this->service->canonical(
            MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED,
            myfatoorahPaymentEventData(),
        ))->toBe($expected);
    });

    it('replaces a null value with the empty string and keeps the key', function () {
        $data = myfatoorahPaymentEventData();
        $data['Invoice']['ExternalIdentifier'] = null;

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data))
            ->toEndWith(',Invoice.ExternalIdentifier=');
    });

    it('keeps the key when the field is absent entirely', function () {
        $data = myfatoorahPaymentEventData();
        unset($data['Transaction']['PaymentId']);

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data))
            ->toContain(',Transaction.PaymentId=,');
    });

    /*
     * The field order is MyFatoorah's, not the payload's. Signing the fields
     * in payload order is one of the three mistakes that fail identically to
     * each other — see pitfalls.md entry 3.
     */
    it('takes its field order from the event definition, not the payload', function () {
        $data = myfatoorahPaymentEventData();
        $reordered = ['Transaction' => $data['Transaction'], 'Amount' => $data['Amount'], 'Invoice' => $data['Invoice']];

        expect($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $reordered))
            ->toBe($this->service->canonical(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data));
    });
});

describe('sign', function () {
    it('is base64 of the binary HMAC-SHA256, not hex', function () {
        $canonical = $this->service->canonical(
            MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED,
            myfatoorahPaymentEventData(),
        );

        expect($this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, myfatoorahPaymentEventData()))
            ->toBe(base64_encode(hash_hmac('sha256', $canonical, 'test-webhook-secret', true)));
    });
});

describe('verify', function () {
    it('accepts its own signature', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeTrue();
    });

    it('rejects a signature made with another secret', function () {
        $data = myfatoorahPaymentEventData();
        $other = (new MyfatoorahSignatureService('someone-elses-secret'))
            ->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $other))->toBeFalse();
    });

    it('rejects a tampered signed field', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        $data['Transaction']['Status'] = 'FAILED';

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeFalse();
    });

    it('ignores a tampered unsigned field', function () {
        $data = myfatoorahPaymentEventData();
        $signature = $this->service->sign(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data);

        $data['Amount']['ValueInDisplayCurrency'] = '9999';

        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, $data, $signature))->toBeTrue();
    });

    it('rejects an empty signature', function () {
        expect($this->service->verify(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED, myfatoorahPaymentEventData(), ''))->toBeFalse();
    });
});

describe('supports', function () {
    it('supports the payment status event only', function () {
        expect($this->service->supports(MyfatoorahSignatureService::PAYMENT_STATUS_CHANGED))->toBeTrue()
            ->and($this->service->supports(2))->toBeFalse()
            ->and($this->service->supports(7))->toBeFalse();
    });
});
