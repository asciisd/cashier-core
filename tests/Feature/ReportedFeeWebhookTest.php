<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\FeeSource;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Testing\FakeLedger;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

/**
 * A $100 Apple Pay deposit quoted at 3%: $103.10 requested, $3.09 estimated
 * fee, charged as 31.62 KWD.
 */
function reportedFeeDeposit(FeeSource $source = FeeSource::Reported): Transaction
{
    return Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'myfatoorah',
        'connection' => 'myfatoorah_apple_pay',
        'provider_transaction_id' => 'mf-apple-1',
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 100.00,
        'requested_amount' => 103.10,
        'charged_amount' => 103.10,
        'psp_fee_amount' => 3.09,
        'markup_amount' => 0,
        'settlement_mode' => 'deducted',
        'fee_source' => $source,
        'estimated_psp_fee_amount' => $source === FeeSource::Reported ? 3.09 : null,
        'currency' => 'USD',
        'charge_currency' => 'KWD',
        'charge_amount' => 31.6200,
        'conversion_rate' => 0.30670000,
        'metadata' => ['trading_account_login' => 555555],
    ]);
}

/**
 * @param  array<string, mixed>  $metadata
 */
function settleReportedFee(Transaction $transaction, array $metadata): Transaction
{
    app(WebhookProcessor::class)->applyUpdate('myfatoorah', $transaction, new TransactionWebhookUpdate(
        status: PaymentStatus::Succeeded,
        processorResponse: [],
        metadata: $metadata,
        amount: 31.62,
        currency: 'KWD',
    ));

    return $transaction->fresh();
}

it('credits the unused estimate on top of the deposit', function () {
    // Apple Pay over KNET: 0.75% + 0.100 KD = 0.337 KD, $1.10 at the charge's rate.
    $fresh = settleReportedFee(reportedFeeDeposit(), [
        'settlement_reported_fee' => 0.337,
        'settlement_reported_fee_currency' => 'KWD',
    ]);

    expect($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and((float) $fresh->amount)->toBe(101.99)
        ->and((float) $fresh->psp_fee_amount)->toBe(1.10)
        ->and((float) $fresh->estimated_psp_fee_amount)->toBe(3.09)
        ->and((float) $fresh->charged_amount)->toBe(103.10);

    $this->ledger->assertMoved('credit', fn (array $movement) => $movement['amount'] === 101.99);
});

it('credits the deposit as quoted when the fee matches the estimate', function () {
    // Apple Pay over a credit card: 3% of 31.62 KD = 0.9486 KD, $3.09.
    $fresh = settleReportedFee(reportedFeeDeposit(), [
        'settlement_reported_fee' => 0.9486,
        'settlement_reported_fee_currency' => 'KWD',
    ]);

    expect((float) $fresh->amount)->toBe(100.0)
        ->and((float) $fresh->psp_fee_amount)->toBe(3.09);

    $this->ledger->assertMoved('credit', fn (array $movement) => $movement['amount'] === 100.0);
});

it('absorbs a fee above the estimate and never credits less than quoted', function () {
    $fresh = settleReportedFee(reportedFeeDeposit(), [
        'settlement_reported_fee' => 1.0,
        'settlement_reported_fee_currency' => 'KWD',
    ]);

    expect((float) $fresh->amount)->toBe(100.0)
        ->and((float) $fresh->psp_fee_amount)->toBe(3.26);

    $this->ledger->assertMoved('credit', fn (array $movement) => $movement['amount'] === 100.0);
});

it('reads a fee reported in the account currency as is', function () {
    $fresh = settleReportedFee(reportedFeeDeposit(), [
        'settlement_reported_fee' => 1.50,
        'settlement_reported_fee_currency' => 'USD',
    ]);

    expect((float) $fresh->amount)->toBe(101.59)
        ->and((float) $fresh->psp_fee_amount)->toBe(1.50);
});

it('keeps the estimate when the fee is missing, unpriced or in a foreign currency', function (array $metadata) {
    $fresh = settleReportedFee(reportedFeeDeposit(), $metadata);

    expect((float) $fresh->amount)->toBe(100.0)
        ->and((float) $fresh->psp_fee_amount)->toBe(3.09);
})->with([
    'missing' => [[]],
    'no currency' => [['settlement_reported_fee' => 0.337]],
    'unrelated currency' => [['settlement_reported_fee' => 0.337, 'settlement_reported_fee_currency' => 'EUR']],
    'negative' => [['settlement_reported_fee' => -1, 'settlement_reported_fee_currency' => 'KWD']],
    'not a number' => [['settlement_reported_fee' => 'n/a', 'settlement_reported_fee_currency' => 'KWD']],
]);

it('ignores a reported fee on a configured-fee deposit', function () {
    $fresh = settleReportedFee(reportedFeeDeposit(FeeSource::Configured), [
        'settlement_reported_fee' => 0.337,
        'settlement_reported_fee_currency' => 'KWD',
    ]);

    expect((float) $fresh->amount)->toBe(100.0)
        ->and((float) $fresh->psp_fee_amount)->toBe(3.09);
});

it('does not settle a held deposit', function () {
    $transaction = reportedFeeDeposit();

    app(WebhookProcessor::class)->applyUpdate('myfatoorah', $transaction, new TransactionWebhookUpdate(
        status: PaymentStatus::Succeeded,
        processorResponse: [],
        metadata: ['settlement_reported_fee' => 0.337, 'settlement_reported_fee_currency' => 'KWD'],
        amount: 12.00,
        currency: 'KWD',
    ));

    $fresh = $transaction->fresh();

    expect($fresh->status)->toBe(PaymentStatus::OnHold)
        ->and((float) $fresh->amount)->toBe(100.0);

    $this->ledger->assertMovedCount(0);
});
