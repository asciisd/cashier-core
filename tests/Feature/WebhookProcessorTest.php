<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Drivers\Aps\ApsAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\DepositHeldForReview;
use Asciisd\CashierCore\Events\DepositSucceeded;
use Asciisd\CashierCore\Events\FeeDriftDetected;
use Asciisd\CashierCore\Events\FundsCredited;
use Asciisd\CashierCore\Events\TransactionStatusChanged;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);
    config()->set('cashier-core.security.redact_keys', ['email', 'card*']);

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function processorDeposit(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'aps',
        'provider_transaction_id' => 'aps-tx-1',
        'type' => TransactionType::Deposit,
        'status' => PaymentStatus::Pending,
        'amount' => 100,
        'currency' => 'USD',
        'metadata' => ['trading_account_login' => 70001],
    ], $overrides));
}

/**
 * A completed APS callback for a `settlement_mode: added` deposit (production
 * txn 688): a 100.00 order, APS's own 6.25 customer fee added at checkout,
 * 106.25 debited from the customer, 100.00 settled to the merchant.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apsFeeAddedCallback(array $overrides = []): array
{
    return [
        'payload' => array_merge([
            'transaction_id' => 'aps-tx-1',
            'external_id' => 'DEP-01KZQX1SR2TE7WPP53M0R4X6Z3',
            'status' => 'completed',
            'fiscal_status' => 'done',
            'amount' => 100,
            'amount_in' => 106.25,
            'amount_out' => 100,
            'customer_fee' => 6.25,
            'merchant_fee' => 0,
        ], $overrides),
    ];
}

function succeededWebhook(array $args = []): TransactionWebhookUpdate
{
    return new TransactionWebhookUpdate(...array_merge([
        'status' => PaymentStatus::Succeeded,
        'processorResponse' => ['status' => 'done'],
    ], $args));
}

it('settles a deposit, credits the ledger once, and emits the events', function () {
    Event::fake([TransactionStatusChanged::class, DepositSucceeded::class, FundsCredited::class]);

    $transaction = processorDeposit();
    $processor = app(WebhookProcessor::class);

    expect($processor->applyUpdate('aps', $transaction, succeededWebhook()))->toBeTrue()
        // A duplicate delivery is recognised against the committed row.
        ->and($processor->applyUpdate('aps', $transaction, succeededWebhook()))->toBeFalse();

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->mt5_ticket_number)->not->toBeNull();

    $this->ledger->assertMovedCount(1);
    $this->ledger->assertMoved('credit', fn (array $m) => $m['account'] === 70001 && $m['amount'] === 100.0);

    Event::assertDispatched(DepositSucceeded::class);
    Event::assertDispatched(FundsCredited::class);
    Event::assertDispatched(
        TransactionStatusChanged::class,
        fn (TransactionStatusChanged $e) => $e->from === PaymentStatus::Pending && $e->to === PaymentStatus::Succeeded,
    );
});

it('keeps the caller instance in step with the committed row', function () {
    $transaction = processorDeposit(['amount' => 100, 'requested_amount' => 100]);

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook([
        'metadata' => [
            'settlement_expected_payment' => '100',
            'settlement_actually_paid' => '40',
        ],
    ]));

    // The settlement reconciled the amount down; a caller that keeps using its
    // own instance (sync commands do) must see the committed figures.
    expect((float) $transaction->amount)->toBe(40.0)
        ->and($transaction->status)->toBe(PaymentStatus::Succeeded);

    $this->ledger->assertMoved('credit', fn (array $m) => $m['amount'] === 40.0);
});

it('holds a success whose amount deviates beyond tolerance instead of crediting', function () {
    Event::fake([DepositHeldForReview::class]);
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);

    $transaction = processorDeposit(['requested_amount' => 100]);

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook(['amount' => 60]));

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::OnHold)
        ->and($fresh->metadata['hold_reason'] ?? null)->toContain('deviates');

    $this->ledger->assertNothingMoved();
    Event::assertDispatched(DepositHeldForReview::class);
});

/*
 * Regression, production txns 673/681/687/688. Under `settlement_mode: added`
 * the PSP appends its own fee at checkout, so `requested_amount` (100.00) and
 * `charged_amount` (106.25) differ by exactly that fee. The adapter used to
 * report the customer's debit, which put every such deposit 6.25% outside the
 * 1% band — no APS deposit was ever credited by its own callback, each waited
 * for an operator to run the sync action (which omits the amount entirely and
 * so never trips the guard).
 */
it('credits a fee-added APS deposit whose callback carries the PSP fee', function () {
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);

    $transaction = processorDeposit([
        'amount' => 100,
        'requested_amount' => 100,
        'charged_amount' => 106.25,
        'psp_fee_amount' => 6.25,
        'markup_amount' => 0,
        'settlement_mode' => 'added',
    ]);

    $update = (new ApsAdapter)->fromWebhook(apsFeeAddedCallback());

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, $update);

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->metadata['hold_reason'] ?? null)->toBeNull();

    // The ledger is credited the net deposit, never the customer's debit.
    $this->ledger->assertMoved('credit', fn (array $m) => $m['amount'] === 100.0);
});

it('still holds an APS callback that reports less than the invoiced order', function () {
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);

    $transaction = processorDeposit(['requested_amount' => 100, 'psp_fee_amount' => 6.25]);

    $update = (new ApsAdapter)->fromWebhook(apsFeeAddedCallback(['amount' => 50, 'amount_out' => 50]));

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, $update);

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::OnHold)
        ->and($fresh->metadata['hold_reason'] ?? null)->toContain('deviates');

    $this->ledger->assertNothingMoved();
});

/*
 * Fee drift reads APS's merchant settlement from `aps_amount_out`, which is a
 * different question from the invoice guard and must stay answerable from the
 * same callback.
 */
it('still reports fee drift from the APS settlement figure after crediting', function () {
    Event::fake([FeeDriftDetected::class]);

    $transaction = processorDeposit([
        'amount' => 100,
        'requested_amount' => 100,
        'charged_amount' => 106.25,
        'psp_fee_amount' => 6.25,
        'markup_amount' => 0,
        'settlement_mode' => 'added',
    ]);

    // The order was 100.00 as invoiced, but APS settled only 90.00 to us.
    $update = (new ApsAdapter)->fromWebhook(apsFeeAddedCallback(['amount_out' => 90]));

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, $update);

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);

    Event::assertDispatched(
        FeeDriftDetected::class,
        fn (FeeDriftDetected $e) => $e->transaction->is($transaction) && $e->expected === 100.0 && $e->actual === 90.0,
    );
});

it('holds a success whose currency does not match the invoice', function () {
    $transaction = processorDeposit();

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook(['currency' => 'EUR']));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::OnHold);
    $this->ledger->assertNothingMoved();
});

it('lets a held deposit settle later through sync', function () {
    $transaction = processorDeposit(['status' => PaymentStatus::OnHold]);

    $applied = app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook(), source: 'sync');

    expect($applied)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);

    $this->ledger->assertMovedCount(1);
});

it('never credits a withdrawal that syncs to succeeded', function () {
    $transaction = processorDeposit([
        'type' => TransactionType::Withdrawal,
        'provider_transaction_id' => 'WD-1',
        'withdrawal_method' => 'bank_transfer',
    ]);

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook(), source: 'sync');

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
    $this->ledger->assertNothingMoved();
});

it('redacts the stored provider payload', function () {
    $transaction = processorDeposit();

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook([
        'processorResponse' => ['status' => 'done', 'email' => 'user@example.com', 'card_type' => 'VISA'],
    ]));

    $payload = $transaction->fresh()->provider_payload;

    expect($payload['status'])->toBe('done')
        ->and($payload['email'])->toBe('[redacted]')
        ->and($payload['card_type'])->toBe('[redacted]');
});

it('ignores soft-deleted transactions entirely', function () {
    $transaction = processorDeposit();
    $transaction->delete();

    app(WebhookProcessor::class)->process('aps', 'aps-tx-1', succeededWebhook());

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Pending);
    $this->ledger->assertNothingMoved();
});

it('backs off from a live transfer claim and takes over a stale one', function () {
    $held = processorDeposit(['metadata' => [
        'trading_account_login' => 70001,
        TransferClaim::METADATA_KEY => now()->subMinute()->toIso8601String(),
    ]]);

    app(WebhookProcessor::class)->applyUpdate('aps', $held, succeededWebhook());
    $this->ledger->assertNothingMoved();

    $stale = processorDeposit(['provider_transaction_id' => 'aps-tx-2', 'metadata' => [
        'trading_account_login' => 70001,
        TransferClaim::METADATA_KEY => now()->subMinutes(15)->toIso8601String(),
    ]]);

    app(WebhookProcessor::class)->applyUpdate('aps', $stale, succeededWebhook());

    $this->ledger->assertMovedCount(1);
    expect($stale->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

it('releases the claim when the ledger refuses, keeps it when the ledger throws', function () {
    $this->ledger->refuseAll();
    $refused = processorDeposit();

    app(WebhookProcessor::class)->applyUpdate('aps', $refused, succeededWebhook());

    expect($refused->fresh()->mt5_ticket_number)->toBeNull()
        ->and($refused->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    $throwing = new FakeLedger;
    $throwing->throwOnNextCall();
    app()->instance(FundsLedger::class, $throwing);
    app()->forgetInstance(WebhookProcessor::class);

    $errored = processorDeposit(['provider_transaction_id' => 'aps-tx-3']);

    app(WebhookProcessor::class)->applyUpdate('aps', $errored, succeededWebhook());

    // Outcome unknown — the claim survives so nothing re-credits until it
    // expires and a human can check ledger history.
    expect($errored->fresh()->metadata)->toHaveKey(TransferClaim::METADATA_KEY);
});

/*
 * Settlement reconciliation compares the settlement metadata against `amount`
 * and then OVERWRITES `amount` with the result. On a foreign charge `amount` is
 * the account currency while any settlement figure is in the charge currency,
 * so composing the two writes a PSP-leg figure into the column the ledger
 * credits — the original incident by another route. No foreign driver emits
 * these keys today; this is the latch that keeps it that way.
 */
it('never reconciles settlement against a converted charge leg', function () {
    $transaction = processorDeposit([
        'amount' => 100,
        'requested_amount' => 100,
        'charge_currency' => 'KWD',
        'charge_amount' => 30.6700,
        'conversion_rate' => 0.30670000,
    ]);

    app(WebhookProcessor::class)->applyUpdate('aps', $transaction, succeededWebhook([
        'metadata' => [
            'settlement_expected_payment' => '100',
            'settlement_actually_paid' => '40',
        ],
    ]));

    $fresh = $transaction->fresh();

    expect((float) $fresh->amount)->toBe(100.0)
        ->and($fresh->settled_amount)->toBeNull();

    $this->ledger->assertMoved('credit', fn (array $m) => $m['amount'] === 100.0);
});
