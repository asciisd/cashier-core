<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Digiblox\DigibloxAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;

beforeEach(function () {
    $this->adapter = new DigibloxAdapter;
});

describe('mapDepositStatus', function () {
    it('maps CONFIRMED to Succeeded — the only status to fulfil on', function () {
        expect($this->adapter->mapDepositStatus('CONFIRMED'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps both rejection forms to Failed', function () {
        expect($this->adapter->mapDepositStatus('REJECTED'))->toBe(PaymentStatus::Failed);
        expect($this->adapter->mapDepositStatus('REJECTED_BY_ADMIN'))->toBe(PaymentStatus::Failed);
    });

    it('maps every in-flight status to Pending', function () {
        foreach (['SENT_DEPOSIT_ADDRESS', 'SENT_TOKEN', 'PENDING', 'PUBLISHED'] as $status) {
            expect($this->adapter->mapDepositStatus($status))->toBe(PaymentStatus::Pending);
        }
    });

    it('maps an unknown status to Pending so a new status cannot break us', function () {
        expect($this->adapter->mapDepositStatus('SOMETHING_NEW'))->toBe(PaymentStatus::Pending);
        expect($this->adapter->mapDepositStatus(null))->toBe(PaymentStatus::Pending);
    });
});

describe('mapWebhookStatus', function () {
    it('maps COMPLETED and OVERPAID to Succeeded — both are covered orders', function () {
        expect($this->adapter->mapWebhookStatus('COMPLETED'))->toBe(PaymentStatus::Succeeded);
        expect($this->adapter->mapWebhookStatus('OVERPAID'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps PARTIALLY_PAID to OnHold — the funds are real, the order is not covered', function () {
        expect($this->adapter->mapWebhookStatus('PARTIALLY_PAID'))->toBe(PaymentStatus::OnHold);
    });

    it('maps an unknown verdict to OnHold, never to Succeeded', function () {
        expect($this->adapter->mapWebhookStatus('SOMETHING_NEW'))->toBe(PaymentStatus::OnHold);
    });

    it('does not treat the deposit vocabulary as a webhook verdict', function () {
        expect($this->adapter->mapWebhookStatus('CONFIRMED'))->toBe(PaymentStatus::OnHold);
    });
});

describe('mapTransferStatus', function () {
    it('maps CONFIRMED to Succeeded', function () {
        expect($this->adapter->mapTransferStatus('CONFIRMED'))->toBe(PaymentStatus::Succeeded);
    });

    it('maps all four terminal failures to Failed', function () {
        foreach (['FAILED', 'REJECTED', 'DROPPED', 'EXPIRED'] as $status) {
            expect($this->adapter->mapTransferStatus($status))->toBe(PaymentStatus::Failed);
        }
    });

    it('maps FINALIZE to Pending — confirmed on-chain but not yet terminal', function () {
        expect($this->adapter->mapTransferStatus('FINALIZE'))->toBe(PaymentStatus::Pending);
    });

    it('maps every other in-progress status, and unknowns, to Pending', function () {
        foreach (['QUEUED', 'PENDING', 'ADMIN_APPROVED', 'SIGNATURE_PENDING', 'BLOCKCHAIN_PENDING', 'WHATEVER'] as $status) {
            expect($this->adapter->mapTransferStatus($status))->toBe(PaymentStatus::Pending);
        }
    });
});
