<?php

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Drivers\Aps\ApsAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new ApsAdapter;
});

describe('mapStatus', function () {
    it('maps success statuses to Succeeded', function () {
        expect($this->adapter->mapStatus('done'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapStatus('completed'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps failure statuses to Failed', function () {
        expect($this->adapter->mapStatus('failed'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('error'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapStatus('expired'))->toBe(PaymentStatus::Failed);
    });

    it('maps canceled to Canceled', function () {
        expect($this->adapter->mapStatus('canceled'))->toBe(PaymentStatus::Canceled);
        expect($this->adapter->mapStatus('cancelled'))->toBe(PaymentStatus::Canceled);
    });

    it('maps pending_external (AML review) to Processing', function () {
        expect($this->adapter->mapStatus('pending_external'))->toBe(PaymentStatus::Processing);
    });

    it('maps in-flight and unknown statuses to Pending', function () {
        expect($this->adapter->mapStatus('pending'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('pending_sender'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus('something_new'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapStatus(null))->toBe(PaymentStatus::Pending);
    });
});

describe('fromProviderResponse', function () {
    it('builds a pending PaymentResult with the checkout url from `how`', function () {
        $result = $this->adapter->fromProviderResponse([
            'id' => '1a2d87dc-3a6e-48dd-be69-8ad6492cc8b4',
            'amount' => 1000,
            'amount_in' => 1000,
            'amount_out' => 975.5,
            'customer_fee' => 0,
            'how' => 'https://checkout.example.com/pay/abc',
        ]);

        expect($result)->toBeInstanceOf(PaymentResult::class)
            ->and($result->transactionId)->toBe('1a2d87dc-3a6e-48dd-be69-8ad6492cc8b4')
            ->and($result->status)->toBe(PaymentStatus::Pending)
            ->and($result->getRedirectUrl())->toBe('https://checkout.example.com/pay/abc')
            ->and($result->requiresAction())->toBeTrue()
            ->and($result->metadata['aps_amount_out'])->toBe(975.5);
    });
});

describe('fromWebhook', function () {
    it('unwraps the nested payload key and maps a completed deposit', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'b829f009-afe0-45c2-9996-8941f80bcb0e',
                'sep31_status' => 'completed',
                'status' => 'done',
                'refunded' => false,
                'amount_in' => 500,
                'amount_out' => 475.5,
                'external_message' => 'APPROVED',
            ],
        ]);

        expect($update)->toBeInstanceOf(TransactionWebhookUpdate::class)
            ->and($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(500)
            ->and($update->metadata['aps_transaction_id'])->toBe('b829f009-afe0-45c2-9996-8941f80bcb0e')
            ->and($update->errorMessage)->toBeNull();
    });

    it('handles a flat (non-nested) payload and failed status with message', function () {
        $update = $this->adapter->fromWebhook([
            'transaction_id' => 'tx-1',
            'status' => 'failed',
            'external_message' => 'DECLINED',
        ]);

        expect($update->status)->toBe(PaymentStatus::Failed)
            ->and($update->errorMessage)->toBe('DECLINED');
    });

    it('falls back to sep31_status when fiscal status is absent', function () {
        $update = $this->adapter->fromWebhook([
            'payload' => [
                'transaction_id' => 'tx-2',
                'sep31_status' => 'pending_external',
            ],
        ]);

        expect($update->status)->toBe(PaymentStatus::Processing);
    });
});

/*
 * The engine used to read the HOST app's `transactions.currency.default` here.
 * It happened to hold 'USD' in the app the code was extracted from, so the
 * coupling was invisible — any other host would have had its configured
 * currency silently ignored in favour of the literal default.
 */
it('takes the currency default from the package config, not a host config file', function () {
    config()->set('cashier-core.currency.default', 'EUR');
    config()->set('transactions.currency.default', 'JPY');

    $result = $this->adapter->fromProviderResponse(['id' => 'tx-3', 'amount' => 10]);

    expect($result->currency)->toBe('EUR');
});
