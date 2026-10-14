<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Schema;

it('adds nullable payout columns to transactions', function () {
    expect(Schema::hasColumns('transactions', ['payout_state', 'payout_reference']))->toBeTrue();
});

it('casts payout_state to PayoutState and defaults both columns to null', function () {
    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-COLUMNS',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
    ]);

    expect($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->payout_reference)->toBeNull();

    $transaction->update(['payout_state' => PayoutState::Sent, 'payout_reference' => 'hero-1']);

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->payout_reference)->toBe('hero-1');
});

it('treats only sent and unknown as in flight', function () {
    expect(PayoutState::Sent->isInFlight())->toBeTrue()
        ->and(PayoutState::Unknown->isInFlight())->toBeTrue()
        ->and(PayoutState::Failed->isInFlight())->toBeFalse()
        ->and(PayoutState::Paid->isInFlight())->toBeFalse();
});
