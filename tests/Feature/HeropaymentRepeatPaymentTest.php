<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\SettlementMode;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\DepositHeldForReview;
use Asciisd\CashierCore\Events\RepeatPaymentReceived;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Asciisd\CashierCore\Testing\FakeLedger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/*
 * Production txn 2525 (2026-10-23): a $2,500 deposit invoiced at $2,517.37
 * (2,525.14 USDT), underpaid at 2,498.50 USDT and credited $2,473.62. Nine
 * hours later the customer sent 398.50 USDT more to the same static address.
 * Heropayment raised that as a new payment under the same externalOrderId,
 * and the pipeline dropped it as a duplicate of the settled original.
 */
const REPEAT_ORDER_ID = 'DEP-01M5K2E2X42ZJP6WRMQFZD4TX3';
const REPEAT_ORIGINAL_PAYMENT = '48fda69d-bb31-44a9-af8a-5cce81981d85';
const REPEAT_SECOND_PAYMENT = '61d246d0-f70d-45ac-ba46-f2074537b8c3';
const REPEAT_ORIGINAL_EXPECTED = 2525.14342661;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.connections.heropayment', HeropaymentPayoutApi::CONFIG);
    // Below the 1.05% shortfall on txn 2525, so it is reconciled as it was in production.
    config()->set('cashier-core.settlement.tolerance_percent', 0.5);

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function repeatCallback(array $overrides = []): array
{
    return array_merge([
        'id' => REPEAT_ORIGINAL_PAYMENT,
        'status' => 'finished',
        'sequence' => 'original',
        'invoice' => [
            'id' => '7670c26b-06e0-46a0-8e96-ee908e317454',
            'orderId' => REPEAT_ORDER_ID,
            'customerId' => '10930',
            'priceAmount' => 2517.37,
            'priceCurrency' => 'usd',
        ],
        'externalOrderId' => REPEAT_ORDER_ID,
        'transactionType' => 'deposit',
        'payCurrency' => 'usdttrc20',
        'payAmount' => REPEAT_ORIGINAL_EXPECTED,
        'paidAmount' => 2498.5,
        'payAddress' => 'TEisUiqiLZA96JTtNHjnUzuoyBBCXYm2HR',
        'merchantAmountUsdt' => 2481.51437004,
    ], $overrides);
}

/**
 * The second payment's callback: its own id and sequence, the original's
 * order id and invoice, and a `payAmount` equal to what it paid.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function secondPaymentCallback(array $overrides = []): array
{
    return repeatCallback(array_merge([
        'id' => REPEAT_SECOND_PAYMENT,
        'sequence' => '0249afb9bb8d9936ee9a712947e14705',
        'payAmount' => 398.5,
        'paidAmount' => 398.5,
        'merchantAmountUsdt' => 394.95036006,
    ], $overrides));
}

function deliverHeropayment(array $payload): void
{
    (new ProcessPaymentProviderWebhook('heropayment', $payload, 'heropayment'))
        ->handle(app(ConnectionRegistry::class), app(WebhookProcessor::class));
}

function repeatOriginal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 3653,
        'provider' => 'heropayment',
        'connection' => 'heropayment',
        'provider_transaction_id' => REPEAT_ORDER_ID,
        'type' => TransactionType::Deposit,
        'status' => PaymentStatus::Pending,
        'amount' => 2500,
        'requested_amount' => 2517.37,
        'charged_amount' => 2517.37,
        'psp_fee_amount' => 17.37,
        'markup_amount' => 0,
        'settlement_mode' => SettlementMode::Deducted,
        'currency' => 'USD',
        'metadata' => ['trading_account_login' => 10930, 'heropayment_invoice_id' => '7670c26b-06e0-46a0-8e96-ee908e317454'],
    ], $overrides));
}

function repeatTransaction(): ?Transaction
{
    return Transaction::query()
        ->where('provider_transaction_id', REPEAT_ORDER_ID.HeropaymentProvider::REPEAT_SEPARATOR.REPEAT_SECOND_PAYMENT)
        ->first();
}

it('opens a transaction of its own for a second payment to the same address', function () {
    Event::fake([RepeatPaymentReceived::class]);

    $original = repeatOriginal();

    deliverHeropayment(repeatCallback());
    deliverHeropayment(secondPaymentCallback());

    $original->refresh();
    $repeat = repeatTransaction();

    // The original is exactly as the first payment left it.
    expect($original->status)->toBe(PaymentStatus::Succeeded)
        ->and((float) $original->amount)->toBe(2473.62);

    // The repeat is the same fraction of the original $2,500 deposit that
    // 398.50 USDT is of the 2,525.14 USDT invoice.
    $expected = round(2500 * 398.5 / REPEAT_ORIGINAL_EXPECTED, 2);

    expect($repeat)->not->toBeNull()
        ->and($repeat->status)->toBe(PaymentStatus::Succeeded)
        ->and($repeat->user_id)->toBe(3653)
        ->and($repeat->connection)->toBe('heropayment')
        ->and($repeat->reference)->not->toBe($original->reference)
        ->and((float) $repeat->amount)->toBe($expected)
        ->and((float) $repeat->requested_amount)->toBe(2517.37)
        ->and($repeat->metadata['repeat_of'])->toBe($original->reference)
        ->and($repeat->metadata['heropayment_payment_id'])->toBe(REPEAT_SECOND_PAYMENT)
        ->and($repeat->mt5_ticket_number)->not->toBeNull();

    $this->ledger->assertMovedCount(2);
    $this->ledger->assertMoved('credit', fn (array $m) => $m['account'] === 10930 && $m['amount'] === $expected);

    Event::assertDispatched(RepeatPaymentReceived::class, fn (RepeatPaymentReceived $e) => $e->transaction->is($repeat) && $e->original->is($original));
});

it('creates one repeat transaction across all of its callbacks and credits it once', function () {
    repeatOriginal();
    deliverHeropayment(repeatCallback());

    deliverHeropayment(secondPaymentCallback(['status' => 'waiting']));
    deliverHeropayment(secondPaymentCallback(['status' => 'confirming']));
    deliverHeropayment(secondPaymentCallback(['status' => 'exchanging']));
    deliverHeropayment(secondPaymentCallback(['status' => 'finished']));
    deliverHeropayment(secondPaymentCallback(['status' => 'finished']));

    expect(Transaction::query()->where('provider', 'heropayment')->count())->toBe(2)
        ->and(repeatTransaction()->status)->toBe(PaymentStatus::Succeeded);

    $this->ledger->assertMovedCount(2);
});

it('treats a different payment id as a repeat even when the sequence says original', function () {
    repeatOriginal();
    deliverHeropayment(repeatCallback());

    deliverHeropayment(secondPaymentCallback(['sequence' => 'original']));

    expect(repeatTransaction())->not->toBeNull();
    $this->ledger->assertMovedCount(2);
});

it('still ignores a late callback for the original payment itself', function () {
    repeatOriginal();
    deliverHeropayment(repeatCallback());
    deliverHeropayment(repeatCallback(['status' => 'confirming']));

    expect(Transaction::query()->count())->toBe(1);
    $this->ledger->assertMovedCount(1);
});

it('restores the invoice of an original that was never reconciled', function () {
    repeatOriginal();
    deliverHeropayment(repeatCallback(['paidAmount' => REPEAT_ORIGINAL_EXPECTED]));
    deliverHeropayment(secondPaymentCallback());

    expect((float) repeatTransaction()->amount)->toBe(round(2500 * 398.5 / REPEAT_ORIGINAL_EXPECTED, 2));
});

it('holds a repeat it cannot measure against the original invoice', function () {
    Event::fake([DepositHeldForReview::class]);

    // An original that never received a callback has no recorded expectation.
    $original = repeatOriginal(['metadata' => [
        'trading_account_login' => 10930,
        'heropayment_payment_id' => REPEAT_ORIGINAL_PAYMENT,
    ]]);

    deliverHeropayment(secondPaymentCallback());

    expect(repeatTransaction()->status)->toBe(PaymentStatus::OnHold)
        ->and($original->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->ledger->assertNothingMoved();
    Event::assertDispatched(DepositHeldForReview::class);
});

it('syncs a repeat payment by its own payment id', function () {
    Http::fake(['hero.test/v2/payments/'.REPEAT_SECOND_PAYMENT => Http::response(secondPaymentCallback())]);

    $result = app(ConnectionRegistry::class)->get('heropayment')
        ->retrieve(REPEAT_ORDER_ID.HeropaymentProvider::REPEAT_SEPARATOR.REPEAT_SECOND_PAYMENT);

    expect($result->status)->toBe(PaymentStatus::Succeeded);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/payments/'.REPEAT_SECOND_PAYMENT));
});

it('recovers repeat payments from the payment list', function () {
    Http::fake(['hero.test/v2/payments*' => Http::response([
        'count' => 2,
        'items' => [secondPaymentCallback(), repeatCallback()],
    ])]);

    repeatOriginal();
    deliverHeropayment(repeatCallback());

    $this->artisan('cashier:heropayment-repeats', ['--dry-run' => true])->assertSuccessful();

    expect(repeatTransaction())->toBeNull();

    $this->artisan('cashier:heropayment-repeats')->assertSuccessful();
    $this->artisan('cashier:heropayment-repeats')->assertSuccessful();

    expect(repeatTransaction()->status)->toBe(PaymentStatus::Succeeded);
    $this->ledger->assertMovedCount(2);
});
