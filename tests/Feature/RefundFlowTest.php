<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\RefundFailed;
use Asciisd\CashierCore\Events\RefundSucceeded;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Models\Refund;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\PaymentService;
use Illuminate\Support\Facades\Event;

/*
 * Refunds were never persisted. processRefund() called the driver, returned a
 * DTO and wrote nothing, so `$transaction->refunds()->sum('amount')` — the
 * expression every "has this already been refunded?" guard was built on —
 * always read zero, and the same transaction could be refunded again and
 * again. These pin the balance arithmetic that replaces it.
 */

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);

    RefundingProvider::reset();

    config()->set('cashier-core.connections.refunding', [
        'driver' => 'refunding',
        'class' => RefundingProvider::class,
    ]);
});

function refundableTransaction(float $amount = 100.0): Transaction
{
    return Transaction::query()->create([
        'user_id' => 1,
        'reference' => 'TX-'.uniqid(),
        'provider' => 'refunding',
        'connection' => 'refunding',
        'provider_transaction_id' => 'psp-'.uniqid(),
        'type' => TransactionType::Deposit,
        'status' => PaymentStatus::Succeeded,
        'amount' => $amount,
        'currency' => 'USD',
    ]);
}

it('persists a refund row so the refunded total is a fact, not a recomputation', function () {
    $transaction = refundableTransaction(100.0);

    $result = app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 40.0, 'duplicate charge');

    expect($result->isSuccessful())->toBeTrue();

    $refund = Refund::query()->where('transaction_id', $transaction->id)->sole();

    expect((float) $refund->amount)->toBe(40.0)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('psp-refund-1')
        ->and($refund->currency)->toBe('USD')
        ->and($refund->reason)->toBe('duplicate charge')
        ->and($refund->processed_at)->not->toBeNull()
        ->and($refund->failed_at)->toBeNull();
});

it('refuses a second refund that would exceed what is left', function () {
    $transaction = refundableTransaction(100.0);
    $service = app(PaymentService::class);

    $service->processRefund($transaction->provider_transaction_id, 70.0);

    expect(fn () => $service->processRefund($transaction->provider_transaction_id, 40.0))
        ->toThrow(PaymentProcessingException::class, 'exceeds the 30 still refundable');

    expect(Refund::query()->where('transaction_id', $transaction->id)->count())->toBe(1);
});

it('refuses any refund once the full amount is returned', function () {
    $transaction = refundableTransaction(100.0);
    $service = app(PaymentService::class);

    $service->processRefund($transaction->provider_transaction_id, 100.0);

    expect(fn () => $service->processRefund($transaction->provider_transaction_id, 1.0))
        ->toThrow(PaymentProcessingException::class, 'already fully refunded');
});

it('lets partial refunds accumulate exactly to the charged amount', function () {
    $transaction = refundableTransaction(100.0);
    $service = app(PaymentService::class);

    $service->processRefund($transaction->provider_transaction_id, 60.0);
    $service->processRefund($transaction->provider_transaction_id, 40.0);

    $total = (float) Refund::query()->where('transaction_id', $transaction->id)->sum('amount');

    expect($total)->toBe(100.0)
        ->and(Refund::query()->where('transaction_id', $transaction->id)->count())->toBe(2);
});

it('refunds whatever is outstanding when no amount is given', function () {
    $transaction = refundableTransaction(100.0);
    $service = app(PaymentService::class);

    $service->processRefund($transaction->provider_transaction_id, 25.0);
    $service->processRefund($transaction->provider_transaction_id);

    expect((float) Refund::query()->where('transaction_id', $transaction->id)->sum('amount'))->toBe(100.0)
        ->and(RefundingProvider::$lastAmount)->toBe(75.0);
});

/*
 * The signature was ?int, so the driver received 95 for a 95.50 refund and the
 * customer was quietly shorted 50 cents.
 */
it('carries a fractional amount through to the driver intact', function () {
    $transaction = refundableTransaction(95.50);

    app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 95.50);

    expect(RefundingProvider::$lastAmount)->toBe(95.5)
        ->and((float) Refund::query()->sole()->amount)->toBe(95.5);
});

it('frees the balance again when the provider refuses', function () {
    $transaction = refundableTransaction(100.0);
    RefundingProvider::$succeeds = false;

    $result = app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 100.0);

    expect($result->isSuccessful())->toBeFalse();

    $refund = Refund::query()->sole();
    expect($refund->status)->toBe(RefundStatus::Failed)
        ->and($refund->failed_at)->not->toBeNull()
        ->and($refund->processed_at)->toBeNull();

    // A refusal must not consume the customer's refundable balance.
    RefundingProvider::$succeeds = true;
    app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 100.0);

    expect(Refund::query()->where('status', RefundStatus::Succeeded)->count())->toBe(1);
});

it('releases the reservation when the provider call throws', function () {
    $transaction = refundableTransaction(100.0);
    RefundingProvider::$throws = new PaymentProcessingException('gateway down');

    expect(fn () => app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 100.0))
        ->toThrow(PaymentProcessingException::class, 'gateway down');

    expect(Refund::query()->sole()->status)->toBe(RefundStatus::Failed);

    // Otherwise an outage would permanently consume the refundable balance.
    RefundingProvider::$throws = null;
    app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 100.0);

    expect(Refund::query()->where('status', RefundStatus::Succeeded)->count())->toBe(1);
});

it('rejects a zero or negative amount', function () {
    $transaction = refundableTransaction(100.0);

    expect(fn () => app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 0.0))
        ->toThrow(PaymentProcessingException::class, 'greater than zero');
});

it('dispatches the outcome events with the refund attached', function () {
    Event::fake([RefundSucceeded::class, RefundFailed::class]);

    $transaction = refundableTransaction(100.0);
    app(PaymentService::class)->processRefund($transaction->provider_transaction_id, 10.0);

    Event::assertDispatched(RefundSucceeded::class, fn (RefundSucceeded $e) => $e->transaction->is($transaction)
        && (float) $e->refund->amount === 10.0);
    Event::assertNotDispatched(RefundFailed::class);
});

class RefundingProvider implements PaymentProcessorInterface
{
    public static ?float $lastAmount = null;

    public static bool $succeeds = true;

    public static ?\Throwable $throws = null;

    public function __construct(public array $config = []) {}

    public static function reset(): void
    {
        self::$lastAmount = null;
        self::$succeeds = true;
        self::$throws = null;
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        self::$lastAmount = $amount;

        if (self::$throws !== null) {
            throw self::$throws;
        }

        return new RefundResult(
            success: self::$succeeds,
            refundId: 'psp-refund-1',
            originalTransactionId: $transactionId,
            status: self::$succeeds ? RefundStatus::Succeeded : RefundStatus::Failed,
            amount: $amount ?? 0.0,
            currency: 'USD',
            metadata: ['raw' => true],
        );
    }

    public function charge(array $data): PaymentResult
    {
        throw new BadMethodCallException('Not supported');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        return null;
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new BadMethodCallException('Not supported');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'refunding';
    }

    public function supports(string $feature): bool
    {
        return $feature === 'refund';
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
}
