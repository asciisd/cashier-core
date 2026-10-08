<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Drivers\Digiblox\DigibloxProvider;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\WithdrawalMarkedPaid;
use Asciisd\CashierCore\Events\WithdrawalPayoutFailed;
use Asciisd\CashierCore\Events\WithdrawalPayoutOutcomeUnknown;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);
    config()->set('cashier-core.connections.digiblox', [
        'driver' => 'digiblox',
        'class' => DigibloxProvider::class,
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'bkE0RmNjbEhCUmc9',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'payout_assets' => ['usdttrc20' => ['asset' => 'USDT', 'network' => 'TRON', 'peg' => 'USD']],
    ]);

    app()->instance(FundsLedger::class, new FakeLedger);
});

function dbxWithdrawal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'digiblox',
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

function dbxActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

function dbxWorkflow(): WithdrawalWorkflow
{
    return app(WithdrawalWorkflow::class);
}

/** @param array<string, mixed> $options transfer: create response, status: lookup response */
function dbxApi(array $options = []): void
{
    Http::fake([
        'https://digiblox.test/gateway/api/v1/auth/login/jwt' => Http::response(['token' => 'jwt']),
        'https://digiblox.test/gateway/api/v1/v3/transfers/centralized' => $options['transfer'] ?? Http::response(['id' => 'Qk1Z', 'status' => 'QUEUED'], 202),
        'https://digiblox.test/gateway/api/v1/transfers/*' => $options['status'] ?? Http::response(['api_data' => ['transfer' => [
            'id' => 'Qk1Z', 'status' => 'CONFIRMED', 'tx_hash' => '0xabc', 'user_note' => 'WD-01TEST',
        ]]]),
    ]);
}

it('sends, then a sync with the stored reference closes the withdrawal as paid', function () {
    Event::fake([WithdrawalMarkedPaid::class]);
    dbxApi();

    expect(dbxWorkflow()->sendPayout($transaction = dbxWithdrawal(), dbxActor())->ok)->toBeTrue()
        ->and($transaction->fresh()->payout_reference)->toBe('Qk1Z');

    expect(dbxWorkflow()->syncPayout($transaction->fresh(), dbxActor())->ok)->toBeTrue();

    $fresh = $transaction->fresh();
    expect($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->payout_state)->toBe(PayoutState::Paid)
        ->and($fresh->metadata['payout_outcome']['outcomeHash'])->toBe('0xabc');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/gateway/api/v1/transfers/Qk1Z'));
    Event::assertDispatched(WithdrawalMarkedPaid::class);
});

it('marks a 5xx send unknown, announces it, and blocks a resend', function () {
    Event::fake([WithdrawalPayoutOutcomeUnknown::class]);
    dbxApi(['transfer' => Http::response(['message' => 'boom'], 500)]);

    $result = dbxWorkflow()->sendPayout($transaction = dbxWithdrawal(), dbxActor());

    expect($result->ok)->toBeFalse()->and($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown);
    Event::assertDispatched(WithdrawalPayoutOutcomeUnknown::class, fn ($e) => $e->orderId === 'WD-01TEST'
        && $e->transaction->is($transaction));

    // Claim is stale: the Unknown branch now tries a lookup, which Digiblox cannot do by order id.
    $stale = $transaction->fresh();
    $stale->update(['metadata' => array_merge($stale->metadata, [TransferClaim::METADATA_KEY => now()->subMinutes(11)->toIso8601String()])]);

    expect(dbxWorkflow()->sendPayout($stale->fresh(), dbxActor())->message)->toContain('Could not confirm');

    $creates = collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/transfers/centralized'));
    expect($creates)->toHaveCount(1);
});

function dbxUnknown(array $metadata = []): Transaction
{
    return dbxWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => array_merge(['ledger_account' => 70001], $metadata),
    ]);
}

it('links a transfer id after checking it at Digiblox', function () {
    dbxApi(['status' => Http::response(['api_data' => ['transfer' => ['id' => 'Qk1Z', 'status' => 'BLOCKCHAIN_PENDING', 'user_note' => 'WD-01TEST']]])]);

    $result = dbxWorkflow()->linkPayoutReference($transaction = dbxUnknown(), ' Qk1Z ', dbxActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->payout_reference)->toBe('Qk1Z')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
    expect(AdminAction::query()->where('action', 'withdrawal.link-payout-reference')->exists())->toBeTrue();
});

it('closes the withdrawal at once when the linked transfer is already confirmed', function () {
    dbxApi();

    dbxWorkflow()->linkPayoutReference($transaction = dbxUnknown(), 'Qk1Z', dbxActor());

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('refuses to link an id Digiblox does not know or that belongs to another order', function (mixed $status) {
    dbxApi(['status' => $status]);

    $result = dbxWorkflow()->linkPayoutReference($transaction = dbxUnknown(), 'Qk1Z', dbxActor());

    expect($result->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown)
        ->and($transaction->fresh()->payout_reference)->toBeNull();
})->with([
    'not found' => fn () => Http::response(['message' => 'Not Found'], 404),
    'other order' => fn () => Http::response(['api_data' => ['transfer' => ['id' => 'Qk1Z', 'status' => 'QUEUED', 'user_note' => 'WD-OTHER']]]),
]);

it('refuses both manual actions while the send claim is fresh, or when not unknown', function (array $overrides) {
    dbxApi();
    $transaction = dbxWithdrawal($overrides);

    expect(dbxWorkflow()->linkPayoutReference($transaction, 'Qk1Z', dbxActor())->ok)->toBeFalse()
        ->and(dbxWorkflow()->confirmPayoutNotSent($transaction, dbxActor(), 'checked dashboard')->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe($overrides['payout_state'] ?? null);
})->with([
    'fresh claim' => fn () => [['payout_state' => PayoutState::Unknown, 'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()]]],
    'sent' => [['payout_state' => PayoutState::Sent, 'payout_reference' => 'Qk1Z']],
    'never sent' => [[]],
]);

it('confirms an unknown payout was never sent, so it can be resent under a new order id', function () {
    Event::fake([WithdrawalPayoutFailed::class]);
    dbxApi();

    $result = dbxWorkflow()->confirmPayoutNotSent($transaction = dbxUnknown(), dbxActor(), 'Not in Digiblox dashboard');
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->metadata['payout_error'])->toBe('Confirmed not sent by admin: Not in Digiblox dashboard');
    expect(AdminAction::query()->where('action', 'withdrawal.confirm-not-sent')->sole()->context)
        ->toMatchArray(['order_id' => 'WD-01TEST', 'note' => 'Not in Digiblox dashboard']);
    Event::assertDispatched(WithdrawalPayoutFailed::class);

    expect(dbxWorkflow()->sendPayout($fresh, dbxActor())->ok)->toBeTrue()
        ->and($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST-2');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/transfers/centralized') && $r['user_note'] === 'WD-01TEST-2');
});

it('requires a note to confirm not sent', function () {
    expect(dbxWorkflow()->confirmPayoutNotSent($transaction = dbxUnknown(), dbxActor(), '   ')->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown);
});

it('links nothing when the payout was resolved by someone else during the lookup', function (mixed $status) {
    dbxApi(['status' => $status]);

    $transaction = dbxUnknown();
    Transaction::query()->whereKey($transaction->getKey())->update(['payout_state' => PayoutState::Failed->value]);

    $result = dbxWorkflow()->linkPayoutReference($transaction, 'Qk1Z', dbxActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeFalse()
        ->and($fresh->payout_reference)->toBeNull()
        ->and($fresh->payout_state)->toBe(PayoutState::Failed);
    expect(AdminAction::query()->where('action', 'withdrawal.link-payout-reference')->exists())->toBeFalse();
})->with([
    'reports sent' => fn () => Http::response(['api_data' => ['transfer' => ['id' => 'Qk1Z', 'status' => 'QUEUED', 'user_note' => 'WD-01TEST']]]),
    'reports failed' => fn () => Http::response(['api_data' => ['transfer' => ['id' => 'Qk1Z', 'status' => 'FAILED', 'user_note' => 'WD-01TEST']]]),
]);
