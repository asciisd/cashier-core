<?php

use Asciisd\CashierCore\Drivers\Jenapay\JenapayHashService;

beforeEach(function () {
    $this->hashService = new JenapayHashService('test-password');
});

describe('hash formulas', function () {
    // Expected values precomputed with sha1(md5(strtoupper(concat))).
    it('computes the sale hash from order number, amount, currency, description', function () {
        expect($this->hashService->forSale('DEP-123', '100.00', 'USD', 'Test deposit'))
            ->toBe('90af0a249d1f4a4b027ea36b32733e83420fbb39');
    });

    it('computes the callback hash including the payment id', function () {
        expect($this->hashService->forCallback('pay-1', 'DEP-123', '100.00', 'USD', 'Test deposit'))
            ->toBe('65bc088322dd30aa959df1b049d96a8a3592d2dc');
    });

    it('computes the payment action hash (void/retry/status)', function () {
        expect($this->hashService->forPaymentAction('pay-1'))
            ->toBe('9979ef69cd2d66a3607a6031abee0d2471708023');
    });

    it('computes the capture/refund hash with amount', function () {
        expect($this->hashService->forPaymentAmountAction('pay-1', '50.00'))
            ->toBe('531ec9ddf44d26bbffd554dcca4b6ae9796e55f5');
    });

    it('computes the order status hash', function () {
        expect($this->hashService->forOrderStatus('DEP-123'))
            ->toBe('fd217fefd8b53e884a56a63d3da73833f1845067');
    });

    it('is sensitive to amount formatting', function () {
        expect($this->hashService->forSale('DEP-123', '100', 'USD', 'Test deposit'))
            ->not->toBe($this->hashService->forSale('DEP-123', '100.00', 'USD', 'Test deposit'));
    });
});

describe('verifyCallback', function () {
    it('accepts a payload with a valid hash', function () {
        $payload = [
            'id' => 'pay-1',
            'order_number' => 'DEP-123',
            'order_amount' => '100.00',
            'order_currency' => 'USD',
            'order_description' => 'Test deposit',
            'hash' => '65bc088322dd30aa959df1b049d96a8a3592d2dc',
        ];

        expect($this->hashService->verifyCallback($payload))->toBeTrue();
    });

    it('rejects a payload with a tampered amount', function () {
        $payload = [
            'id' => 'pay-1',
            'order_number' => 'DEP-123',
            'order_amount' => '999.00',
            'order_currency' => 'USD',
            'order_description' => 'Test deposit',
            'hash' => '65bc088322dd30aa959df1b049d96a8a3592d2dc',
        ];

        expect($this->hashService->verifyCallback($payload))->toBeFalse();
    });

    it('rejects a payload without a hash', function () {
        expect($this->hashService->verifyCallback(['id' => 'pay-1']))->toBeFalse();
    });

    it('verifies a payload using payment_id and nested order fields', function () {
        // Same values as the accepted payload above, in the shape the status
        // endpoint uses. Without the fallbacks these hash as empty strings and
        // verification fails unconditionally.
        $payload = [
            'payment_id' => 'pay-1',
            'order' => [
                'number' => 'DEP-123',
                'amount' => '100.00',
                'currency' => 'USD',
                'description' => 'Test deposit',
            ],
            'hash' => '65bc088322dd30aa959df1b049d96a8a3592d2dc',
        ];

        expect($this->hashService->verifyCallback($payload))->toBeTrue();
    });

    it('still rejects a tampered payload in the nested shape', function () {
        $payload = [
            'payment_id' => 'pay-1',
            'order' => [
                'number' => 'DEP-123',
                'amount' => '999.00',
                'currency' => 'USD',
                'description' => 'Test deposit',
            ],
            'hash' => '65bc088322dd30aa959df1b049d96a8a3592d2dc',
        ];

        expect($this->hashService->verifyCallback($payload))->toBeFalse();
    });
});
