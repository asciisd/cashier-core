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

describe('formatFiatAmount', function () {
    it('always emits exactly two decimals', function () {
        expect($this->adapter->formatFiatAmount(150))->toBe('150.00');
        expect($this->adapter->formatFiatAmount(150.5))->toBe('150.50');
        expect($this->adapter->formatFiatAmount('7.5'))->toBe('7.50');
        expect($this->adapter->formatFiatAmount(0.1 + 0.2))->toBe('0.30');
    });
});

describe('buildLinkPayload', function () {
    $config = [
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'crypto_currency' => 'USDT',
        'network' => 'TRON',
        'success_url' => 'https://shop.test/ok',
        'fail_url' => 'https://shop.test/no',
    ];

    it('builds a Flow A payload with a two-decimal string amount', function () use ($config) {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 150, 'currency' => 'USD'],
            $config,
        );

        expect($payload['payload'])->toBe([
            'external_id' => 'DEP-1',
            'merchant_id' => 'bkE0RmNjbEhCUmc9',
            'payment_method' => 'CRYPTO_DEPOSIT',
            'fiat_currency' => 'USD',
            'fiat_amount' => '150.00',
            'crypto_currency' => 'USDT',
            'network' => 'TRON',
        ]);
    });

    it('always sends a notification object, empty when no urls are configured', function () {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            ['merchant_id' => 'M1'],
        );

        expect($payload)->toHaveKey('notification')
            ->and($payload['notification'])->toBe([]);
    });

    it('adds username only for Flow B', function () use ($config) {
        $flowA = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            $config,
        );
        $flowB = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-2', 'amount' => 10, 'currency' => 'USD', 'guest_email' => 'a@b.test'],
            $config,
        );

        expect($flowA['payload'])->not->toHaveKey('username')
            ->and($flowB['payload']['username'])->toBe('a@b.test');
    });

    it('omits network when no crypto_currency is configured, since network alone is rejected', function () {
        $payload = $this->adapter->buildLinkPayload(
            ['external_id' => 'DEP-1', 'amount' => 10, 'currency' => 'USD'],
            ['merchant_id' => 'M1', 'network' => 'TRON'],
        );

        expect($payload['payload'])->not->toHaveKey('network');
    });
});

describe('reconciliation', function () {
    it('reconciles on total_amount, not on the fee-netted amount', function () {
        // Figures from a real settled deposit on this account.
        // Reconciling on `amount` would make this look 75.12 USDT short.
        $payload = [
            'expected_amount' => '15023.830599907475',
            'total_amount' => '15023.830598999999',
            'amount' => '14948.711446005',
            'status' => 'COMPLETED',
        ];

        expect($this->adapter->isCovered($payload))->toBeTrue();
    });

    it('does not treat a genuine underpayment as covered', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => '150.000000',
            'total_amount' => '120.000000',
            'amount' => '119.760000',
            'status' => 'PARTIALLY_PAID',
        ]))->toBeFalse();
    });

    it('treats an overpayment as covered', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => '150.000000',
            'total_amount' => '175.500000',
            'status' => 'OVERPAID',
        ]))->toBeTrue();
    });

    it('is not covered when no expected amount was recorded', function () {
        expect($this->adapter->isCovered([
            'expected_amount' => null,
            'total_amount' => '10.000000',
        ]))->toBeFalse();
    });

    it('does not treat a shortfall inside the same whole unit as covered', function () {
        // Guards the tolerance against a scale-0 bcmath comparison, which
        // truncates both sides to 150 and calls this covered.
        expect($this->adapter->isCovered([
            'expected_amount' => '150.99',
            'total_amount' => '150.01',
        ]))->toBeFalse();
    });

    it('computes gross received as amount plus system_fee', function () {
        expect($this->adapter->grossReceived([
            'amount' => '14948.711446005',
            'system_fee' => '75.119152995',
        ]))->toBe('15023.830599000');
    });
});

describe('fromWebhook', function () {
    it('maps COMPLETED to Succeeded, reconciling on total_amount, not the fee-netted amount', function () {
        // total_amount (150.000000) is what the payer sent; amount
        // (149.700000) is already net of the platform fee. Reconciling on
        // amount would look 0.30 short of a fully covered order.
        $update = $this->adapter->fromWebhook([
            'status' => 'COMPLETED',
            'expected_amount' => '150.000000',
            'total_amount' => '150.000000',
            'amount' => '149.700000',
            'currency' => 'USDT',
            'tx_hash' => '0xabc',
            'network' => 'TRON',
            'from_address' => '0xfrom',
            'to_address' => '0xto',
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->amount)->toBe(150.0)
            ->and($update->currency)->toBe('USDT')
            ->and($update->metadata['covered'])->toBeTrue()
            ->and($update->errorMessage)->toBeNull()
            ->and($update->metadata['tx_hash'])->toBe('0xabc')
            ->and($update->metadata['network'])->toBe('TRON')
            ->and($update->metadata['from_address'])->toBe('0xfrom')
            ->and($update->metadata['to_address'])->toBe('0xto');
    });

    it('maps PARTIALLY_PAID to OnHold, not Failed, with an errorMessage naming the verdict', function () {
        $update = $this->adapter->fromWebhook([
            'status' => 'PARTIALLY_PAID',
            'expected_amount' => '150.000000',
            'total_amount' => '120.000000',
            'amount' => '119.760000',
            'currency' => 'USDT',
        ]);

        expect($update->status)->toBe(PaymentStatus::OnHold)
            ->and($update->metadata['covered'])->toBeFalse()
            ->and($update->errorMessage)->toContain('PARTIALLY_PAID');
    });

    it('maps OVERPAID to Succeeded and covered', function () {
        $update = $this->adapter->fromWebhook([
            'status' => 'OVERPAID',
            'expected_amount' => '150.000000',
            'total_amount' => '175.500000',
        ]);

        expect($update->status)->toBe(PaymentStatus::Succeeded)
            ->and($update->metadata['covered'])->toBeTrue();
    });

    it('maps an unrecognised verdict to OnHold, never to Succeeded', function () {
        $update = $this->adapter->fromWebhook([
            'status' => 'SOMETHING_NEW',
            'expected_amount' => '150.000000',
            'total_amount' => '150.000000',
        ]);

        expect($update->status)->toBe(PaymentStatus::OnHold);
    });
});
