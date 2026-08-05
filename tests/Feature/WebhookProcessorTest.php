<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\DepositHeldForReview;
use Asciisd\CashierCore\Events\DepositSucceeded;
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
