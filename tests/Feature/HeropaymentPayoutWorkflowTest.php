<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\PayoutFundsInsufficient;
use Asciisd\CashierCore\Events\WithdrawalMarkedPaid;
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
use Illuminate\Support\Facades\Log;

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

it('releases the claim and fails cleanly when the preflight throws unexpectedly', function () {
    HeropaymentPayoutApi::fake(['balance' => fn () => Http::response('"ok"')]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($fresh->payout_state)->toBeNull()
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
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

// --- applyPayoutUpdate / syncPayout -----------------------------------------

function hpReceipt(string $status, array $payload = []): PayoutReceipt
{
    return (new \Asciisd\CashierCore\Drivers\Heropayment\HeropaymentAdapter)
        ->payoutReceipt(HeropaymentPayoutApi::withdrawal(array_merge(['status' => $status], $payload)));
}

it('closes the withdrawal as Succeeded on finished, without touching amount', function () {
    Event::fake([WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    $applied = hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished', [
        'paidAmount' => 6.85714285,
        'outcomeHash' => '0xabc',
        'outcomeAmount' => 99.1,
    ]));

    $fresh = $transaction->fresh();

    expect($applied)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->payout_state)->toBe(PayoutState::Paid)
        ->and((string) $fresh->amount)->toBe('100.00')
        ->and($fresh->metadata['payout_outcome']['outcomeHash'])->toBe('0xabc');

    Event::assertDispatched(WithdrawalMarkedPaid::class, fn ($event) => $event->actor?->guard === 'system');
    expect(AdminAction::query()->where('action', 'withdrawal.payout-paid')->exists())->toBeTrue();
    $this->ledger->assertNothingMoved();
});

it('keeps the withdrawal Processing and alerts on failed or refunded', function (string $status) {
    Event::fake([WithdrawalPayoutFailed::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt($status));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Failed)
        ->and($transaction->fresh()->metadata['payout_error'])->toBe("Heropayment status: {$status}")
        ->and($transaction->fresh()->error_message)->toBeNull();

    Event::assertDispatched(WithdrawalPayoutFailed::class);
})->with(['failed', 'refunded']);

it('refreshes the payload but changes nothing else on an in-progress status', function () {
    Event::fake([WithdrawalPayoutFailed::class, WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('exchanging'));

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->provider_payload['status'])->toBe('exchanging');

    Event::assertNotDispatched(WithdrawalPayoutFailed::class);
    Event::assertNotDispatched(WithdrawalMarkedPaid::class);
});

it('treats paid as final and ignores a late failed', function () {
    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);
    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished'));

    expect(hpWorkflow()->applyPayoutUpdate($transaction->fresh(), hpReceipt('failed')))->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
});

it('resolves an unknown payout and drops the leftover claim', function () {
    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('sending'));

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

// --- Review Focus 2 ---------------------------------------------------------

it('logs critically and changes nothing when finished arrives for a cancelled withdrawal', function () {
    Log::spy();

    $transaction = hpWithdrawal(['status' => PaymentStatus::Canceled, 'payout_state' => PayoutState::Failed]);

    expect(hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished')))->toBeFalse()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Canceled);

    Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'cancelled'))->once();
});

// --- syncPayout -------------------------------------------------------------------

it('syncs a sent payout from the provider', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'finished']))]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    $result = hpWorkflow()->syncPayout($transaction, hpActor());

    expect($result->ok)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
    expect(AdminAction::query()->where('action', 'withdrawal.sync-payout')->exists())->toBeTrue();
});

it('reports when the provider has no such payout', function () {
    HeropaymentPayoutApi::fake();

    // A Sent row the provider cannot find is reported, never moved: only an
    // unknown send is resolved to Failed by a definitive not-found (Final-2).
    $result = hpWorkflow()->syncPayout($transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]), hpActor());

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('No payout found')
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Sent);
});

it('only syncs payouts that are sent or unknown', function () {
    expect(hpWorkflow()->syncPayout(hpWithdrawal(), hpActor())->ok)->toBeFalse();
});

// --- Final-1: Failed never reverts to Sent ------------------------------------

it('ignores a waiting that arrives after failed', function () {
    Event::fake([WithdrawalPayoutFailed::class, WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Failed,
        'metadata' => ['ledger_account' => 70001, 'payout_error' => 'Heropayment status: failed'],
    ]);

    expect(hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('waiting')))->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Failed)
        ->and($transaction->fresh()->metadata['payout_error'])->toBe('Heropayment status: failed');
});

it('closes the withdrawal when finished arrives after failed', function () {
    $transaction = hpWithdrawal(['payout_state' => PayoutState::Failed]);

    expect(hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished')))->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
});

it('announces a paid payout exactly once when finished is delivered twice', function () {
    Event::fake([WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished'));
    hpWorkflow()->applyPayoutUpdate($transaction->fresh(), hpReceipt('finished'));

    Event::assertDispatchedTimes(WithdrawalMarkedPaid::class, 1);
});

// --- Final-2: a failed lookup is never "not found" ------------------------------

it('refuses to resend an unknown payout when the lookup fails', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(['message' => 'internal server error'], 500)]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('Could not confirm the earlier payout at the provider; nothing was sent. Try Check status later.')
        ->and($fresh->payout_state)->toBe(PayoutState::Unknown)
        ->and($fresh->provider_transaction_id)->toBe('WD-01TEST')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});

it('refuses to resend an unknown payout when the lookup times out', function () {
    HeropaymentPayoutApi::fake(['lookup' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    expect(hpWorkflow()->sendPayout($transaction, hpActor())->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});

it('applies an unknown payout found at the provider without sending again', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'sending']))]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toContain('earlier payout was found')
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Sent);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});

it('reports a failed lookup on sync and changes nothing', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(['message' => 'internal server error'], 500)]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    $result = hpWorkflow()->syncPayout($transaction, hpActor());

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toBe('The provider lookup failed; try again later.')
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown);

    $audit = AdminAction::query()->where('action', 'withdrawal.sync-payout')->sole();
    expect($audit->context)->toMatchArray(['found' => false, 'lookup_failed' => true]);
});

it('marks an unknown payout the provider never received as failed, so it can be resent', function (array $metadata) {
    Event::fake([WithdrawalPayoutFailed::class]);
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown, 'metadata' => $metadata]);

    $result = hpWorkflow()->syncPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toBe('No payout exists at the provider; marked failed so it can be resent or cancelled.')
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->metadata['payout_error'])->toBe('No payout found at the provider for order WD-01TEST after an unknown send.')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY)
        ->and($fresh->error_message)->toBeNull();

    $audit = AdminAction::query()->where('action', 'withdrawal.sync-payout')->sole();
    expect($audit->context)->toMatchArray(['found' => false, 'resolved' => 'failed']);
    Event::assertDispatched(WithdrawalPayoutFailed::class);

    expect(hpWorkflow()->sendPayout($fresh, hpActor())->ok)->toBeTrue()
        ->and($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST-2');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['externalOrderId'] === 'WD-01TEST-2');
})->with([
    'no claim' => [['ledger_account' => 70001]],
    'stale claim' => fn () => [['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->subMinutes(11)->toIso8601String()]],
]);

it('leaves an unknown payout alone on a not-found while its send claim is fresh', function () {
    Event::fake([WithdrawalPayoutFailed::class]);
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    $result = hpWorkflow()->syncPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('No payout found')
        ->and($fresh->payout_state)->toBe(PayoutState::Unknown)
        ->and($fresh->metadata)->toHaveKey(TransferClaim::METADATA_KEY);

    Event::assertNotDispatched(WithdrawalPayoutFailed::class);
});

// --- Minor-5: hold is logged for ops -------------------------------------------

it('logs a warning for ops when a payout is put on hold', function () {
    Log::spy();

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    expect(hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('hold')))->toBeTrue()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Sent);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'hold')
            && ($context['transaction_id'] ?? null) === $transaction->id
            && ($context['order_id'] ?? null) === 'WD-01TEST')
        ->once();
});

// --- Final-3: locked post-send writes; the attempt id is saved before the POST --

it('does not overwrite a payout that was paid while the send was in flight', function () {
    Event::fake([WithdrawalPayoutSent::class]);

    $transaction = hpWithdrawal();

    HeropaymentPayoutApi::fake(['withdrawal' => function () use ($transaction) {
        // Heropayments calls back before the create response returns.
        hpWorkflow()->applyPayoutUpdate(Transaction::query()->findOrFail($transaction->id), hpReceipt('finished', ['outcomeHash' => '0xabc']));

        return Http::response(HeropaymentPayoutApi::withdrawal());
    }]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->payout_state)->toBe(PayoutState::Paid)
        ->and($fresh->metadata['payout_outcome']['outcomeHash'])->toBe('0xabc')
        ->and($fresh->metadata)->toHaveKey('paid_at')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Event::assertNotDispatched(WithdrawalPayoutSent::class);
});

it('does not overwrite a paid payout when a send outcome turns out unknown', function () {
    $transaction = hpWithdrawal();

    HeropaymentPayoutApi::fake(['withdrawal' => function () use ($transaction) {
        hpWorkflow()->applyPayoutUpdate(Transaction::query()->findOrFail($transaction->id), hpReceipt('finished'));

        throw new ConnectionException('cURL error 28: timed out');
    }]);

    hpWorkflow()->sendPayout($transaction, hpActor());

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Paid)
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('saves the new attempt id before the resend is posted', function () {
    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Failed,
        'metadata' => ['ledger_account' => 70001, 'payout_error' => 'Payout address not valid'],
    ]);

    $inFlight = null;

    HeropaymentPayoutApi::fake(['withdrawal' => function () use ($transaction, &$inFlight) {
        $inFlight = Transaction::query()->findOrFail($transaction->id);

        return Http::response(HeropaymentPayoutApi::withdrawal(['externalOrderId' => 'WD-01TEST-2']));
    }]);

    expect(hpWorkflow()->sendPayout($transaction, hpActor())->ok)->toBeTrue()
        ->and($inFlight?->provider_transaction_id)->toBe('WD-01TEST-2')
        ->and($inFlight?->metadata['payout_attempts'][0]['order_id'])->toBe('WD-01TEST')
        ->and($inFlight?->metadata)->toHaveKey(TransferClaim::METADATA_KEY);

    $fresh = $transaction->fresh();

    expect($fresh->provider_transaction_id)->toBe('WD-01TEST-2')
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->metadata['payout_attempts'])->toHaveCount(1);
});
