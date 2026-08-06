<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\PaymentService;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
});

function fakeSeamCustomer(): CustomerContract
{
    return new class implements CustomerContract
    {
        public function cashierId(): int|string
        {
            return 11;
        }

        public function cashierEmail(): string
        {
            return 'faked@example.com';
        }

        public function cashierName(): string
        {
            return 'Faked Customer';
        }

        public function cashierLocale(): string
        {
            return 'en';
        }
    };
}

it('records charges instead of reaching a PSP and persists the transaction', function () {
    $fake = Cashier::fake(['aps']);

    $result = app(PaymentService::class)->processPayment(fakeSeamCustomer(), ['amount' => 100], 'aps');

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->transactionId)->toStartWith('fake-tx-')
        ->and($result->getRedirectUrl())->not->toBeNull();

    $fake->assertCharged(fn (array $data, string $connection) => $connection === 'aps' && $data['amount'] === 100);
    $fake->assertChargedOn('aps');
    $fake->assertChargedCount(1);

    $transaction = Transaction::query()->sole();

    expect($transaction->provider)->toBe('aps')
        ->and($transaction->connection)->toBe('aps')
        ->and($transaction->status)->toBe(PaymentStatus::Pending);
});

it('keeps the configured driver when faking an existing connection', function () {
    config()->set('cashier-core.connections.payport_sar', ['driver' => 'payport', 'api_key' => 'real-key']);

    Cashier::fake(['payport_sar']);

    app(PaymentService::class)->processPayment(fakeSeamCustomer(), ['amount' => 100], 'payport_sar');

    $transaction = Transaction::query()->sole();

    expect($transaction->provider)->toBe('payport')
        ->and($transaction->connection)->toBe('payport_sar');
});

it('fakes every configured connection when called without arguments', function () {
    config()->set('cashier-core.connections.aps', ['driver' => 'aps']);
    config()->set('cashier-core.connections.payport', ['driver' => 'payport']);

    $fake = Cashier::fake();

    app(PaymentService::class)->processPayment(fakeSeamCustomer(), ['amount' => 10], 'aps');
    app(PaymentService::class)->processPayment(fakeSeamCustomer(), ['amount' => 20], 'payport');

    $fake->assertChargedCount(2);
    $fake->assertChargedOn('payport', fn (array $data) => $data['amount'] === 20);
});

it('returns the queued result for a connection', function () {
    $fake = Cashier::fake(['aps']);

    $fake->whenCharging('aps', new PaymentResult(
        success: false,
        transactionId: 'declined-1',
        status: PaymentStatus::Failed,
        amount: 100,
        currency: 'USD',
        message: 'Card declined',
    ));

    $result = app(PaymentService::class)->processPayment(fakeSeamCustomer(), ['amount' => 100], 'aps');

    expect($result->isFailed())->toBeTrue();

    $transaction = Transaction::query()->sole();

    expect($transaction->status)->toBe(PaymentStatus::Failed)
        ->and($transaction->error_message)->toBe('Card declined');
});

it('supports refunds and retrievals against the fake', function () {
    $fake = Cashier::fake(['aps']);

    Transaction::query()->create([
        'user_id' => 11,
        'provider' => 'aps',
        'connection' => 'aps',
        'provider_transaction_id' => 'fake-tx-r',
        'type' => Asciisd\CashierCore\Enums\TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ]);

    app(PaymentService::class)->processRefund('fake-tx-r', 50);

    $fake->assertRefunded(fn (array $refund) => $refund['transaction_id'] === 'fake-tx-r' && $refund['amount'] === 50.0);
});

it('asserts nothing charged when no charge ran', function () {
    Cashier::fake(['aps'])->assertNothingCharged();
});

it('configures a driver-defaulted connection via fakeConnection', function () {
    Cashier::fakeConnection('aps_binance', ['app_secret' => 'other-secret']);

    $config = Connections::get('aps_binance');

    expect($config['driver'])->toBe('aps')
        ->and($config['app_secret'])->toBe('other-secret')
        ->and($config['merchant_guid'])->toBe('aps_binance-merchant-guid');

    expect(app(Asciisd\CashierCore\Connections\ConnectionRegistry::class)->get('aps_binance'))
        ->toBeInstanceOf(Asciisd\CashierCore\Drivers\Aps\ApsProvider::class);
});
