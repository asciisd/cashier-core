<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\DataObjects\WithdrawalRequestData;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\WithdrawalApproved;
use Asciisd\CashierCore\Events\WithdrawalRequested;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function workflowCustomer(int $id = 1): CustomerContract
{
    return new class($id) implements CustomerContract
    {
        public function __construct(private readonly int $id) {}

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

function workflowRequest(array $overrides = []): WithdrawalRequestData
{
    return new WithdrawalRequestData(...array_merge([
        'amount' => 250.0,
        'currency' => 'USD',
        'driver' => 'manual',
        'method' => 'bank_transfer',
        'ledgerAccount' => 70001,
    ], $overrides));
}

function workflowActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

it('creates a pending withdrawal without touching the ledger in approve-first mode', function () {
    Event::fake([WithdrawalRequested::class]);

    $transaction = app(WithdrawalWorkflow::class)->request(workflowCustomer(), workflowRequest());

    expect($transaction->type)->toBe(TransactionType::Withdrawal)
        ->and($transaction->status)->toBe(PaymentStatus::Pending)
        ->and($transaction->mt5_ticket_number)->toBeNull()
        ->and($transaction->provider_transaction_id)->toStartWith('WD-')
        ->and($transaction->metadata['ledger_account'])->toBe(70001);

    $this->ledger->assertNothingMoved();
    Event::assertDispatched(WithdrawalRequested::class);
});

it('rejects an identical submission inside the duplicate window', function () {
    $workflow = app(WithdrawalWorkflow::class);
    $workflow->request(workflowCustomer(), workflowRequest());

    expect(fn () => $workflow->request(workflowCustomer(), workflowRequest()))
        ->toThrow(RuntimeException::class);

    expect(Transaction::query()->count())->toBe(1);
});

it('debits at submission in legacy mode and rolls back when the ledger refuses', function () {
    config()->set('cashier-core.withdrawals.approve_first', false);

    $workflow = app(WithdrawalWorkflow::class);
    $transaction = $workflow->request(workflowCustomer(), workflowRequest());

    expect($transaction->mt5_ticket_number)->not->toBeNull();
    $this->ledger->assertMoved('debit', fn (array $m) => $m['amount'] === 250.0);

    $this->ledger->refuseAll();

    expect(fn () => $workflow->request(workflowCustomer(2), workflowRequest(['amount' => 300.0])))
        ->toThrow(RuntimeException::class);

    // The refused submission's row was rolled back.
    expect(Transaction::withTrashed()->where('user_id', 2)->first()->trashed())->toBeTrue();
});

it('approves a pending withdrawal: debits, transitions, audits, announces', function () {
    Event::fake([WithdrawalApproved::class]);

    $transaction = app(WithdrawalWorkflow::class)->request(workflowCustomer(), workflowRequest());

    $result = app(WithdrawalWorkflow::class)->approve($transaction, workflowActor());

    expect($result->ok)->toBeTrue()
        ->and($result->transaction->status)->toBe(PaymentStatus::Processing)
        ->and($result->transaction->mt5_ticket_number)->not->toBeNull();

    $this->ledger->assertMoved('debit');

    $audit = AdminAction::query()->first();

    expect($audit->action)->toBe('withdrawal.approve')
        ->and($audit->actor_id)->toBe('9')
        ->and($audit->from_status)->toBe('pending')
        ->and($audit->to_status)->toBe('processing')
        ->and($audit->ip)->toBe('10.0.0.1');

    Event::assertDispatched(WithdrawalApproved::class);
});

it('leaves the withdrawal pending and releases the claim when the debit is refused', function () {
    $transaction = app(WithdrawalWorkflow::class)->request(workflowCustomer(), workflowRequest());

    $this->ledger->refuseAll();

    $result = app(WithdrawalWorkflow::class)->approve($transaction, workflowActor());

    expect($result->ok)->toBeFalse();

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::Pending)
        ->and($fresh->mt5_ticket_number)->toBeNull()
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

it('refuses to approve while another process holds a live claim', function () {
    $transaction = app(WithdrawalWorkflow::class)->request(workflowCustomer(), workflowRequest());
    $transaction->update(['metadata' => array_merge($transaction->metadata, [
        TransferClaim::METADATA_KEY => now()->subMinute()->toIso8601String(),
    ])]);

    $result = app(WithdrawalWorkflow::class)->approve($transaction, workflowActor());

    expect($result->ok)->toBeFalse();
    $this->ledger->assertNothingMoved();
});

it('rejects a debited withdrawal with exactly one refund', function () {
    config()->set('cashier-core.withdrawals.approve_first', false);

    $workflow = app(WithdrawalWorkflow::class);
    $transaction = $workflow->request(workflowCustomer(), workflowRequest());

    $result = $workflow->reject($transaction, workflowActor(), 'invalid details');

    expect($result->ok)->toBeTrue()
        ->and($result->transaction->status)->toBe(PaymentStatus::Failed)
        ->and($result->transaction->error_message)->toBe('invalid details');

    // One debit at submission + one corrective refund, nothing more.
    $this->ledger->assertMovedCount(2);
    $this->ledger->assertMoved('correct', fn (array $m) => $m['amount'] === 250.0);
});

it('marks a processing withdrawal paid under lock with payout details', function () {
    $workflow = app(WithdrawalWorkflow::class);
    $transaction = $workflow->request(workflowCustomer(), workflowRequest());
    $workflow->approve($transaction, workflowActor());

    $result = $workflow->markPaid($transaction->fresh(), workflowActor(), ['payout_reference' => 'BANK-REF-1']);

    expect($result->ok)->toBeTrue()
        ->and($result->transaction->status)->toBe(PaymentStatus::Succeeded)
        ->and($result->transaction->metadata['payout_reference'])->toBe('BANK-REF-1');
});

it('cancels a processing withdrawal, returning the debit', function () {
    $workflow = app(WithdrawalWorkflow::class);
    $transaction = $workflow->request(workflowCustomer(), workflowRequest());
    $workflow->approve($transaction, workflowActor());

    $result = $workflow->cancel($transaction->fresh());

    expect($result->ok)->toBeTrue()
        ->and($result->transaction->status)->toBe(PaymentStatus::Canceled)
        ->and($result->transaction->metadata['cancel_ledger_ticket'])->not->toBeNull();

    $this->ledger->assertMoved('correct');
});

it('refuses to cancel a withdrawal in a terminal state', function () {
    $workflow = app(WithdrawalWorkflow::class);
    $transaction = $workflow->request(workflowCustomer(), workflowRequest());
    $workflow->approve($transaction, workflowActor());
    $workflow->markPaid($transaction->fresh(), workflowActor());

    $result = $workflow->cancel($transaction->fresh());

    expect($result->ok)->toBeFalse();
});
