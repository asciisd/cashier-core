<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\PayoutFundsInsufficient;
use Asciisd\CashierCore\Events\WithdrawalPayoutFailed;
use Asciisd\CashierCore\Events\WithdrawalPayoutSent;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    HeropaymentPayoutApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function hpWithdrawal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
        'withdrawal_method' => 'crypto',
        'withdrawal_details' => ['payout_address' => 'TXyzCustomer', 'payout_currency' => 'usdttrc20'],
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function hpActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

function hpWorkflow(): WithdrawalWorkflow
{
    return app(WithdrawalWorkflow::class);
}

it('sends the payout and records it as sent while status stays Processing', function () {
    Event::fake([WithdrawalPayoutSent::class]);
    HeropaymentPayoutApi::fake();

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());

    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->payout_reference)->toBe('hero-wd-1')
        ->and($fresh->metadata)->toHaveKey('payout_sent_at')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['externalOrderId'] === 'WD-01TEST'
        && $request['customerId'] === '70001'
        && $request['priceAmount'] === '100.00');

    Event::assertDispatched(WithdrawalPayoutSent::class);
    expect(AdminAction::query()->where('action', 'withdrawal.send-payout')->exists())->toBeTrue();
    $this->ledger->assertNothingMoved();
});

it('refuses non-Processing rows and rows already sent', function (array $overrides, string $message) {
    HeropaymentPayoutApi::fake();

    $result = hpWorkflow()->sendPayout(hpWithdrawal($overrides), hpActor());

    expect($result->ok)->toBeFalse()->and($result->message)->toContain($message);
    Http::assertNothingSent();
})->with([
    'pending' => [['status' => PaymentStatus::Pending], 'Processing'],
    'sent' => [['payout_state' => PayoutState::Sent], 'already been sent'],
    'paid' => [['payout_state' => PayoutState::Paid], 'already been sent'],
]);

it('refuses a withdrawal whose connection cannot send payouts', function () {
    $result = hpWorkflow()->sendPayout(hpWithdrawal(['provider' => 'manual']), hpActor());

    expect($result->ok)->toBeFalse()->and($result->message)->toContain('cannot send payouts');
});

it('leaves the row untouched and alerts ops when the balance is short', function () {
    Event::fake([PayoutFundsInsufficient::class]);
    HeropaymentPayoutApi::fake(['balance' => '50.00']);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('50.00')
        ->and($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
    Event::assertDispatched(PayoutFundsInsufficient::class, fn ($event) => $event->balance === '50.00');
});

it('records a rejection in metadata, never in error_message', function () {
    Event::fake([WithdrawalPayoutFailed::class]);
    HeropaymentPayoutApi::fake(['withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->metadata['payout_error'])->toBe('Payout address not valid')
        ->and($fresh->error_message)->toBeNull()
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Event::assertDispatched(WithdrawalPayoutFailed::class);
});

it('marks an ambiguous send unknown and keeps the claim so cancel is refused', function () {
    HeropaymentPayoutApi::fake(['withdrawal' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('Do not resend')
        ->and($fresh->payout_state)->toBe(PayoutState::Unknown)
        ->and($fresh->metadata)->toHaveKey(TransferClaim::METADATA_KEY);

    expect(hpWorkflow()->cancel($fresh, hpActor())->ok)->toBeFalse();
    $this->ledger->assertNothingMoved();
});

it('marks an unexpected exception during send unknown and keeps the claim', function () {
    HeropaymentPayoutApi::fake(['withdrawal' => fn () => throw new RuntimeException('boom')]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('Do not resend')
        ->and($fresh->payout_state)->toBe(PayoutState::Unknown)
        ->and($fresh->metadata)->toHaveKey(TransferClaim::METADATA_KEY);
});

it('resends a failed payout under a new attempt id and records the old one', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Failed,
        'metadata' => ['ledger_account' => 70001, 'payout_error' => 'Payout address not valid'],
    ]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->provider_transaction_id)->toBe('WD-01TEST-2')
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->metadata['payout_attempts'][0])->toMatchArray([
            'order_id' => 'WD-01TEST',
            'state' => 'failed',
            'error' => 'Payout address not valid',
        ]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['externalOrderId'] === 'WD-01TEST-2');
});

it('does not burn an attempt id when preflight refuses a resend', function () {
    HeropaymentPayoutApi::fake(['balance' => '1.00']);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Failed]);

    hpWorkflow()->sendPayout($transaction, hpActor());

    expect($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST');
});

it('re-uses the same order id when resending an unknown payout that lookup cannot find', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->ok)->toBeTrue()
        ->and($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/payments/order/WD-01TEST'));
});

// --- Review Focus 5 ---------------------------------------------------------

it('fails cleanly and releases the claim when payout details are missing', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['withdrawal_details' => ['payout_currency' => 'usdttrc20']]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('payout_address')
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

// --- cancel / markPaid ----------------------------------------------------------

it('refuses cancel while a payout is in flight and allows it after a failure', function () {
    expect(hpWorkflow()->cancel(hpWithdrawal(['payout_state' => PayoutState::Sent]), hpActor())->ok)->toBeFalse();

    $failed = hpWithdrawal([
        'provider_transaction_id' => 'WD-02TEST',
        'payout_state' => PayoutState::Failed,
    ]);

    expect(hpWorkflow()->cancel($failed, hpActor())->ok)->toBeTrue();
    $this->ledger->assertMoved('correct', fn (array $m) => $m['amount'] === 100.0);
});

// --- Review Focus 1 ---------------------------------------------------------

it('cancel re-checks payout_state under the lock', function () {
    $stale = hpWithdrawal();                              // loaded while payout_state was null
    Transaction::query()->whereKey($stale->id)->update(['payout_state' => PayoutState::Sent->value]);

    $result = hpWorkflow()->cancel($stale, hpActor());

    expect($result->ok)->toBeFalse()
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Processing);
    $this->ledger->assertNothingMoved();
});

it('marks a sent payout paid when an admin closes it by hand', function () {
    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->markPaid($transaction, hpActor(), ['payout_reference' => 'checked in back office']);

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Paid)
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('leaves payout_state null when a never-sent withdrawal is marked paid', function () {
    $transaction = hpWithdrawal();

    hpWorkflow()->markPaid($transaction, hpActor());

    expect($transaction->fresh()->payout_state)->toBeNull();
});
