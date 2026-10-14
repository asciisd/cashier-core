<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPayoutWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    HeropaymentPayoutApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function hpJobWithdrawal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'payout_state' => PayoutState::Sent,
        'amount' => 100,
        'currency' => 'USD',
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function hpRunJob(array $payload): ProcessPayoutWebhook
{
    $job = (new ProcessPayoutWebhook('heropayment', $payload, 'heropayment'))->withFakeQueueInteractions();

    $job->handle(app(ConnectionRegistry::class), app(WithdrawalWorkflow::class), new TransferClaim);

    return $job;
}

it('closes a withdrawal from a finished callback without crediting the ledger', function () {
    $transaction = hpJobWithdrawal();

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'finished']));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
    $this->ledger->assertNothingMoved();
});

it('ignores a callback for an unknown order id', function () {
    hpRunJob(HeropaymentPayoutApi::withdrawal(['externalOrderId' => 'WD-NOPE', 'status' => 'finished']))
        ->assertNotReleased();

    expect(Transaction::query()->count())->toBe(0);
});

it('never matches a deposit row with the same order id', function () {
    $deposit = hpJobWithdrawal(['type' => TransactionType::Deposit, 'payout_state' => null]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'finished']));

    expect($deposit->fresh()->status)->toBe(PaymentStatus::Processing);
});

it('re-queues a callback that arrives while a send still holds the claim', function () {
    $transaction = hpJobWithdrawal([
        'payout_state' => null,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'waiting']))->assertReleased(30);

    expect($transaction->fresh()->payout_state)->toBeNull();
});

it('applies a callback to an unknown payout even though its claim is still held', function () {
    $transaction = hpJobWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'sending']))->assertNotReleased();

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent);
});
