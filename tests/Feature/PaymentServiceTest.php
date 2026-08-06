<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FeeConfigurationContract;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Enums\SettlementMode;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\ChargeCreated;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\PaymentService;
use Asciisd\CashierCore\Testing\FakeLedger;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    RecordingChargeProvider::reset();

    config()->set('cashier-core.connections.recording', [
        'driver' => 'recording',
        'class' => RecordingChargeProvider::class,
        'marker' => 'primary',
    ]);

    // A second account on the same driver — the refund tests prove the stored
    // connection (not the driver) picks the credentials.
    config()->set('cashier-core.connections.recording_b', [
        'driver' => 'recording',
        'class' => RecordingChargeProvider::class,
        'marker' => 'secondary',
    ]);
});

/**
 * A container-buildable provider that records what it was asked to do and
 * which connection config it was built with.
 */
class RecordingChargeProvider implements PaymentProcessorInterface, PreparesChargeData
{
    public static ?array $lastChargeData = null;

    public static ?array $lastConfig = null;

    public static ?PaymentResult $nextChargeResult = null;

    public static ?PaymentResult $retrieveResult = null;

    public static ?Throwable $retrieveThrows = null;

    public static bool $preparesChargeData = false;

    public function __construct(private readonly array $config = []) {}

    public static function reset(): void
    {
        self::$lastChargeData = null;
        self::$lastConfig = null;
        self::$nextChargeResult = null;
        self::$retrieveResult = null;
        self::$retrieveThrows = null;
        self::$preparesChargeData = false;
    }

    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
    {
        if (self::$preparesChargeData) {
            $paymentData['prepared_for'] = $connection;
        }

        return $paymentData;
    }

    public function charge(array $data): PaymentResult
    {
        self::$lastChargeData = $data;
        self::$lastConfig = $this->config;

        return self::$nextChargeResult ?? new PaymentResult(
            success: true,
            transactionId: 'rec-tx-1',
            status: PaymentStatus::Pending,
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'USD'),
            metadata: ['redirect_url' => 'https://psp.test/pay/rec-tx-1'],
            processorResponse: ['ok' => true],
        );
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        self::$lastConfig = $this->config;

        return new RefundResult(
            success: true,
            refundId: 'rec-refund-1',
            originalTransactionId: $transactionId,
            status: RefundStatus::Succeeded,
            amount: $amount ?? 0,
            currency: 'USD',
        );
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        self::$lastConfig = $this->config;

        if (self::$retrieveThrows) {
            throw self::$retrieveThrows;
        }

        return self::$retrieveResult;
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new BadMethodCallException('Not supported');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new BadMethodCallException('Not supported');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new BadMethodCallException('Not supported');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new BadMethodCallException('Not supported');
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new BadMethodCallException('Not supported');
    }

    public function validatePaymentData(array $data): array
    {
        return $data;
    }

    public function supports(string $feature): bool
    {
        return $feature === 'refund';
    }

    public function getName(): string
    {
        return 'recording';
    }
}

function chargeCustomer(int|string $id = 7): CustomerContract
{
    return new class($id) implements CustomerContract
    {
        public function __construct(private readonly int|string $id) {}

        public function cashierId(): int|string
        {
            return $this->id;
        }

        public function cashierEmail(): string
        {
            return 'customer@example.com';
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

function chargeFeeConfig(): FeeConfigurationContract
{
    return new class implements FeeConfigurationContract
    {
        public function settlementMode(): SettlementMode
        {
            return SettlementMode::Deducted;
        }

        public function feePercentage(): float
        {
            return 6.0;
        }

        public function feeFixed(): float
        {
            return 0.25;
        }

        public function markupPercentage(): float
        {
            return 1.0;
        }

        public function markupFixed(): float
        {
            return 0.0;
        }

        public function feeConfigurationId(): int|string|null
        {
            return 'test-method';
        }
    };
}

it('charges through the named connection and persists the transaction', function () {
    Event::fake([ChargeCreated::class]);

    $result = app(PaymentService::class)->processPayment(chargeCustomer(7), ['amount' => 100], 'recording');

    expect($result->status)->toBe(PaymentStatus::Pending);

    $transaction = Transaction::query()->sole();

    expect($transaction->provider)->toBe('recording')
        ->and($transaction->connection)->toBe('recording')
        ->and((int) $transaction->user_id)->toBe(7)
        ->and($transaction->type)->toBe(TransactionType::Deposit)
        ->and((float) $transaction->amount)->toBe(100.0)
        ->and($transaction->status)->toBe(PaymentStatus::Pending)
        ->and($transaction->metadata['user_email'])->toBe('customer@example.com');

    // Callers cannot pick a currency — the default is asserted onto the charge.
    expect(RecordingChargeProvider::$lastChargeData['currency'])->toBe('USD');

    Event::assertDispatched(
        ChargeCreated::class,
        fn (ChargeCreated $event) => $event->transaction->is($transaction)
    );
});

it('falls back to the configured default connection', function () {
    config()->set('cashier-core.default_connection', 'recording');

    app(PaymentService::class)->processPayment(chargeCustomer(), ['amount' => 50]);

    expect(Transaction::query()->sole()->connection)->toBe('recording');
});

it('grosses the charge up by the configured fees and stores the snapshot', function () {
    app(PaymentService::class)->processPayment(chargeCustomer(), ['amount' => 100], 'recording', chargeFeeConfig());

    // 6% + $0.25 deducted, 1% markup: solved so requested − pspFee = 101.00.
    expect((float) RecordingChargeProvider::$lastChargeData['amount'])->toBe(107.72);

    $transaction = Transaction::query()->sole();

    expect((float) $transaction->amount)->toBe(100.0)
        ->and((float) $transaction->requested_amount)->toBe(107.72)
        ->and((float) $transaction->charged_amount)->toBe(107.72)
        ->and((float) $transaction->psp_fee_amount)->toBe(6.71)
        ->and((float) $transaction->markup_amount)->toBe(1.0)
        ->and($transaction->settlement_mode)->toBe(SettlementMode::Deducted);
});

it('lets the provider shape its own charge payload', function () {
    RecordingChargeProvider::$preparesChargeData = true;

    app(PaymentService::class)->processPayment(chargeCustomer(), ['amount' => 100], 'recording');

    expect(RecordingChargeProvider::$lastChargeData['prepared_for'])->toBe('recording');
});

it('threads an explicit ledger account into the charge metadata', function () {
    app(PaymentService::class)->processPayment(
        chargeCustomer(),
        ['amount' => 100, 'trading_account_login' => 555555],
        'recording',
    );

    expect(RecordingChargeProvider::$lastChargeData['metadata']['trading_account_login'])->toBe(555555)
        ->and(Transaction::query()->sole()->metadata['trading_account_login'])->toBe(555555);
});

it('resolves the funding account through the bound resolver', function () {
    app()->instance(ResolvesFundingAccount::class, new class implements ResolvesFundingAccount
    {
        public function ledgerAccountFor(CustomerContract $customer, array $paymentData): ?int
        {
            return ($paymentData['reference'] ?? null) === 'ACC-1' ? 777777 : null;
        }

        public function fundingAccountIdFor(CustomerContract $customer, array $paymentData): ?int
        {
            return 42;
        }
    });

    app(PaymentService::class)->processPayment(
        chargeCustomer(),
        ['amount' => 100, 'reference' => 'ACC-1'],
        'recording',
    );

    $transaction = Transaction::query()->sole();

    expect($transaction->metadata['trading_account_login'])->toBe(777777)
        ->and((int) $transaction->trading_account_id)->toBe(42);
});

it('sanitizes the provider payload before persisting it', function () {
    RecordingChargeProvider::$nextChargeResult = new PaymentResult(
        success: true,
        transactionId: 'rec-tx-2',
        status: PaymentStatus::Pending,
        amount: 100,
        currency: 'USD',
        processorResponse: ['ok' => true, 'customer_email' => 'leak@psp.test'],
    );

    app(PaymentService::class)->processPayment(chargeCustomer(), ['amount' => 100], 'recording');

    expect(Transaction::query()->sole()->provider_payload)
        ->toBe(['ok' => true, 'customer_email' => '[redacted]']);
});

it('keeps the selected method columns when the provider knows nothing yet', function () {
    app(PaymentService::class)->processPayment(
        chargeCustomer(),
        ['amount' => 100],
        'recording',
        selectedMethodAttributes: [
            'payment_method_type' => 'credit_card',
            'payment_method_brand' => 'mada',
            'payment_method_display_name' => 'Mada',
        ],
    );

    $transaction = Transaction::query()->sole();

    expect($transaction->payment_method_type)->toBe('credit_card')
        ->and($transaction->payment_method_brand)->toBe('mada')
        ->and($transaction->payment_method_display_name)->toBe('Mada');
});

it('lets the provider snapshot override the selected method where it knows something', function () {
    RecordingChargeProvider::$nextChargeResult = new PaymentResult(
        success: true,
        transactionId: 'rec-tx-3',
        status: PaymentStatus::Pending,
        amount: 100,
        currency: 'USD',
        paymentMethodSnapshot: PaymentMethodSnapshot::fromCardData('visa', '4242'),
    );

    app(PaymentService::class)->processPayment(
        chargeCustomer(),
        ['amount' => 100],
        'recording',
        selectedMethodAttributes: [
            'payment_method_type' => 'credit_card',
            'payment_method_brand' => 'mada',
            'payment_method_display_name' => 'Mada',
        ],
    );

    $transaction = Transaction::query()->sole();

    expect($transaction->payment_method_brand)->toBe('visa')
        ->and($transaction->payment_method_last_four)->toBe('4242');
});

it('refunds through the account that took the charge', function () {
    Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => 'recording_b',
        'provider_transaction_id' => 'tx-refund-1',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ]);

    $result = app(PaymentService::class)->processRefund('tx-refund-1', 50);

    expect($result->isSuccessful())->toBeTrue()
        ->and(RecordingChargeProvider::$lastConfig['marker'])->toBe('secondary');
});

it('falls back to the driver when a transaction has no stored connection', function () {
    Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => null,
        'provider_transaction_id' => 'tx-refund-2',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Succeeded,
    ]);

    app(PaymentService::class)->processRefund('tx-refund-2');

    expect(RecordingChargeProvider::$lastConfig['marker'])->toBe('primary');
});

it('recovers a missed success via sync through the webhook pipeline and credits the ledger', function () {
    $ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $ledger);

    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => 'recording',
        'provider_transaction_id' => 'tx-sync-1',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
        'metadata' => ['trading_account_login' => 555555],
    ]);

    RecordingChargeProvider::$retrieveResult = new PaymentResult(
        success: true,
        transactionId: 'tx-sync-1',
        status: PaymentStatus::Succeeded,
        amount: 100,
        currency: 'USD',
        metadata: ['payment_processor' => 'CardScheme'],
        processorResponse: ['state' => 'done'],
    );

    expect(app(PaymentService::class)->syncTransaction($transaction))->toBeTrue();

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->payment_processor)->toBe('CardScheme')
        ->and($fresh->mt5_ticket_number)->not->toBeNull();

    $ledger->assertMoved(
        'credit',
        fn (array $movement) => $movement['account'] === 555555 && $movement['amount'] === 100.0
    );
});

it('reports sync unsupported when the provider cannot be queried', function () {
    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => 'recording',
        'provider_transaction_id' => 'tx-sync-2',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    RecordingChargeProvider::$retrieveThrows = new BadMethodCallException('no remote ledger to read');

    expect(app(PaymentService::class)->syncTransaction($transaction))->toBeFalse()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('refuses to sync a transaction with no provider transaction id', function () {
    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => 'recording',
        'provider_transaction_id' => null,
        'type' => TransactionType::Withdrawal,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    expect(app(PaymentService::class)->syncTransaction($transaction))->toBeFalse();
});

it('returns false when the provider does not know the transaction', function () {
    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'recording',
        'connection' => 'recording',
        'provider_transaction_id' => 'tx-sync-3',
        'type' => TransactionType::Deposit,
        'amount' => 100,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    RecordingChargeProvider::$retrieveResult = null;

    expect(app(PaymentService::class)->syncTransaction($transaction))->toBeFalse()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Pending);
});
