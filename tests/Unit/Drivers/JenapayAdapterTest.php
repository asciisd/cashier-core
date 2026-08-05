<?php

use Asciisd\CashierCore\Drivers\Jenapay\JenapayAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new JenapayAdapter;
});

describe('mapStatus', function () {
    it('maps settled to Succeeded', function () {
        expect($this->adapter->mapStatus('settled'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapStatus('success'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps declined to Failed', function () {
        expect($this->adapter->mapStatus('declined'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('fail'))->toBe(PaymentStatus::Failed);
    });

    it('maps the live singular "decline" to Failed', function () {
        // The status endpoint answers `decline`, not the spec's `declined`.
        expect($this->adapter->mapStatus('decline'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('DECLINE'))->toBe(PaymentStatus::Failed);
    });

    it('maps refund and void to Canceled', function () {
        expect($this->adapter->mapStatus('refund'))->toBe(PaymentStatus::Canceled);
        expect($this->adapter->mapStatus('void'))->toBe(PaymentStatus::Canceled);
    });

    it('maps 3ds/redirect to RequiresAction', function () {
        expect($this->adapter->mapStatus('3ds'))->toBe(PaymentStatus::RequiresAction);
        expect($this->adapter->mapStatus('redirect'))->toBe(PaymentStatus::RequiresAction);
    });

    it('maps in-flight and unknown statuses to Pending', function () {
        expect($this->adapter->mapStatus('pending'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('prepare'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus(null))->toBe(PaymentStatus::Pending);
    });
});

describe('fromProviderResponse', function () {
    it('builds a pending PaymentResult keyed by our order number', function () {
        $result = $this->adapter->fromProviderResponse([
            'redirect_url' => 'https://checkout.jenapay.test/session/abc',
            'order_number' => 'DEP-123',
            'order_amount' => '250.00',
            'order_currency' => 'USD',
        ]);

        expect($result->transactionId)->toBe('DEP-123')
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->amount)->toBe(250)
            ->and($result->getRedirectUrl())->toBe('https://checkout.jenapay.test/session/abc');
    });
});

describe('fromWebhook', function () {
    it('maps a settled sale callback', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-99',
            'order_number' => 'DEP-123',
            'order_amount' => '250.00',
            'order_currency' => 'USD',
            'type' => 'sale',
            'status' => 'settled',
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(250)
            ->and($update->metadata['jenapay_payment_id'])->toBe('pay-99')
            ->and($update->errorMessage)->toBeNull();
    });

    it('maps a declined callback with the decline reason', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-99',
            'order_number' => 'DEP-123',
            'status' => 'declined',
            'reason' => 'Insufficient funds',
        ]);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('Insufficient funds');
    });

    it('captures the masked card into a payment method snapshot', function () {
        $update = $this->adapter->fromWebhook([
            'id' => 'pay-99',
            'order_number' => 'DEP-123',
            'status' => 'settled',
            'card' => '411111****1111',
        ]);

        expect($update->paymentMethodSnapshot)->not->toBeNull()
            ->and($update->paymentMethodSnapshot->lastFour)->toBe('1111');
    });
});

describe('fromProviderPayload', function () {
    /**
     * Verbatim shape of a real `/api/v1/payment/status` answer: singular
     * `decline`, `payment_id` rather than `id`, and the order fields nested.
     */
    $declineResponse = [
        'payment_id' => '96c277ae-8d05-11f1-8aa9-f632cfa1b5b8',
        'date' => '2026-07-31 17:30:56',
        'status' => 'decline',
        'reason' => 'DECLINED AUTHENTICATION_FAILED',
        'order' => [
            'number' => 'DEP-01KYWKKVJRD5RQ4H8CWDF1E2X9',
            'amount' => '50.00',
            'currency' => 'USD',
            'description' => 'Deposit of USD 50.00',
        ],
        'card' => '537017******3351',
        'card_expiration_date' => '05/2030',
    ];

    it('maps a declined status response to Failed so sync moves the transaction', function () use ($declineResponse) {
        $result = $this->adapter->fromProviderPayload('DEP-01KYWKKVJRD5RQ4H8CWDF1E2X9', $declineResponse);

        expect($result->status)->toBe(PaymentStatus::Failed)
            ->and($result->success)->toBeFalse()
            ->and($result->message)->toBe('DECLINED AUTHENTICATION_FAILED')
            ->and($result->amount)->toBe(50)
            ->and($result->currency)->toBe('USD');
    });

    it('reads the payment id and nested order number into metadata', function () use ($declineResponse) {
        $result = $this->adapter->fromProviderPayload('DEP-01KYWKKVJRD5RQ4H8CWDF1E2X9', $declineResponse);

        expect($result->metadata['jenapay_payment_id'])->toBe('96c277ae-8d05-11f1-8aa9-f632cfa1b5b8')
            ->and($result->metadata['jenapay_order_number'])->toBe('DEP-01KYWKKVJRD5RQ4H8CWDF1E2X9');
    });

    it('carries the masked card so a synced transaction records the method', function () use ($declineResponse) {
        $result = $this->adapter->fromProviderPayload('DEP-01KYWKKVJRD5RQ4H8CWDF1E2X9', $declineResponse);

        expect($result->paymentMethodSnapshot)->not->toBeNull()
            ->and($result->paymentMethodSnapshot->lastFour)->toBe('3351');
    });
});
