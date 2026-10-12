<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    PayCrossBorderApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function pcbWithdrawal(array $overrides = [], ?array $beneficiary = null): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'paycrossborder',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
        'withdrawal_method' => 'crypto',
        'withdrawal_details' => [
            'payout_address' => 'TXyzCustomerWalletAddress000001',
            'payout_currency' => 'usdt',
            'payout_extra_id' => 'TRC20',
            'beneficiary' => $beneficiary ?? PayCrossBorderApi::beneficiary(),
        ],
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function pcbActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

function pcbWorkflow(): WithdrawalWorkflow
{
    return app(WithdrawalWorkflow::class);
}

it('sends the payout and records PCB\'s transReference', function () {
    PayCrossBorderApi::fake();

    $result = pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->payout_reference)->toBe('197930');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api')
        && $request['payout']['transactionInfo']['sendClientTrxReference'] === PayCrossBorderApi::REFERENCE
        && $request['payout']['receiver']['customer']['receiverFirstName'] === 'Kuldeep');
    $this->ledger->assertNothingMoved();
});

it('closes the withdrawal when PCB reports Payout Pass', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Payout Pass']))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    $result = pcbWorkflow()->syncPayout($transaction, pcbActor());

    expect($result->ok)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
});

it('marks the payout failed with PCB\'s reason on Payout Fail', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction([
        'statusName' => 'Payout Fail',
        'vendorResultDescription' => 'Beneficiary account closed',
    ]))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    pcbWorkflow()->syncPayout($transaction, pcbActor());
    $fresh = $transaction->fresh();

    expect($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->payout_state->isInFlight())->toBeFalse()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->metadata['payout_error'])->toBe('PayCrossBorder Payout Fail: Beneficiary account closed');
});

it('keeps a Technical Failure in flight as unknown', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Technical Failure']))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    pcbWorkflow()->syncPayout($transaction, pcbActor());

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown)
        ->and($transaction->fresh()->payout_state->isInFlight())->toBeTrue();
});

it('records a PCB refusal as a failed payout', function () {
    PayCrossBorderApi::fake(['payout' => Http::response(['resultCode' => '1001', 'resultDescription' => 'Invalid wallet'], 400)]);

    $result = pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());

    expect($result->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Failed)
        ->and($transaction->fresh()->metadata['payout_error'])->toContain('Invalid wallet');
});

it('marks a timed-out payout unknown and keeps the claim', function () {
    PayCrossBorderApi::fake(['payout' => fn () => throw new ConnectionException('cURL error 28')]);

    pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown)
        ->and($transaction->fresh()->metadata)->toHaveKey(TransferClaim::METADATA_KEY);
});

it('fails cleanly before calling PCB when the beneficiary is incomplete', function () {
    PayCrossBorderApi::fake();

    $result = pcbWorkflow()->sendPayout(
        $transaction = pcbWithdrawal(beneficiary: PayCrossBorderApi::beneficiary(['dateOfBirth' => null])),
        pcbActor(),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('dateOfBirth is required for crypto payouts')
        ->and($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNothingSent();
});
