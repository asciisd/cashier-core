<?php

declare(strict_types=1);

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\Contracts\ConvertsChargeCurrency;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\DataObjects\ChargeConversion;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\PaymentService;

/**
 * A PSP that cannot be sent the account's currency — MyFatoorah's shape.
 */
class KwdOnlyProvider extends AbstractPaymentProcessor implements PreparesChargeData
{
    public static ?array $lastChargeData = null;

    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        $paymentData['currency'] = 'KWD';

        return $paymentData;
    }

    public function charge(array $data): PaymentResult
    {
        self::$lastChargeData = $data;

        return new PaymentResult(
            success: true,
            transactionId: 'mf-1',
            status: PaymentStatus::Pending,
            amount: (int) $data['amount'],
            currency: 'KWD',
        );
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        return new RefundResult(
            success: false,
            refundId: null,
            originalTransactionId: $transactionId,
            status: RefundStatus::Failed,
            amount: 0,
            currency: 'KWD',
        );
    }

    public function getName(): string
    {
        return 'kwdonly';
    }
}

/**
 * Deliberately does NOT implement PreparesChargeData: the point of the
 * same-currency test is that a driver which never declares a currency takes
 * the untouched path.
 */
class UsdOnlyProvider extends AbstractPaymentProcessor
{
    public function charge(array $data): PaymentResult
    {
        return new PaymentResult(
            success: true,
            transactionId: 'usd-1',
            status: PaymentStatus::Pending,
            amount: (int) $data['amount'],
            currency: 'USD',
        );
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        return new RefundResult(
            success: false,
            refundId: null,
            originalTransactionId: $transactionId,
            status: RefundStatus::Failed,
            amount: 0,
            currency: 'USD',
        );
    }

    public function getName(): string
    {
        return 'usdonly';
    }
}

class FixedRateConverter implements ConvertsChargeCurrency
{
    public function convert(float $amount, string $from, string $to): ChargeConversion
    {
        return new ChargeConversion(
            amount: round($amount * 0.3067 * 1.015, 3),
            rate: 0.3067,
            marginPct: 1.5,
            currency: $to,
        );
    }
}

function foreignChargeCustomer(): CustomerContract
{
    return new class implements CustomerContract
    {
        public function cashierId(): int|string
        {
            return 7;
        }

        public function cashierEmail(): string
        {
            return 'test@example.com';
        }

        public function cashierName(): string
        {
            return 'Test Customer';
        }

        public function cashierLocale(): string
        {
            return 'en';
        }
    };
}

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.currency.default', 'USD');

    config()->set('cashier-core.connections.kwdonly', [
        'driver' => 'kwdonly',
        'class' => KwdOnlyProvider::class,
    ]);

    config()->set('cashier-core.connections.usdonly', [
        'driver' => 'usdonly',
        'class' => UsdOnlyProvider::class,
    ]);

    app()->singleton(ConvertsChargeCurrency::class, FixedRateConverter::class);

    KwdOnlyProvider::$lastChargeData = null;
});

it('invoices the PSP in its own currency but keeps the transaction in the account currency', function () {
    app(PaymentService::class)->processPayment(
        customer: foreignChargeCustomer(),
        paymentData: ['amount' => 100.0],
        connection: 'kwdonly',
    );

    // The PSP was handed the converted figure...
    expect(KwdOnlyProvider::$lastChargeData['amount'])->toBe(31.130);

    // ...while the books stay in USD, which is what the ledger credits and what
    // every deposit limit and method range is configured in.
    $transaction = Transaction::query()->latest('id')->first();

    expect((float) $transaction->amount)->toBe(100.00)
        ->and($transaction->currency)->toBe('USD')
        ->and($transaction->charge_currency)->toBe('KWD')
        ->and((float) $transaction->charge_amount)->toBe(31.13)
        ->and((float) $transaction->conversion_rate)->toBe(0.3067);
});

it('leaves a same-currency charge completely untouched', function () {
    app(PaymentService::class)->processPayment(
        customer: foreignChargeCustomer(),
        paymentData: ['amount' => 100.0],
        connection: 'usdonly',
    );

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction->currency)->toBe('USD')
        ->and($transaction->charge_currency)->toBeNull()
        ->and($transaction->charge_amount)->toBeNull();
});
