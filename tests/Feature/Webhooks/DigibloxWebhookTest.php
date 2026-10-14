<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Drivers\Digiblox\DigibloxAdapter;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;
use Asciisd\CashierCore\Testing\FakeLedger;
use Illuminate\Support\Facades\Queue;

function digibloxWebhookPayload(array $overrides = []): array
{
    return array_merge([
        'tx_hash' => '0x9c2f4b81e0a7d3c5f6b2a9184e7d0c3b5a8f1e6d4c2b7a90',
        'from_address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e',
        'to_address' => '0x0e8091C125FFc084cf4546218b1fB3700F4C6AE0',
        'currency' => 'USDT',
        'network' => 'ETHEREUM',
        'amount' => '149.700000',
        'confirmed' => true,
        'external_transaction_id' => 'DEP-1',
        'expected_amount' => '150.000000',
        'total_amount' => '150.000000',
        'status' => 'COMPLETED',
    ], $overrides);
}

/**
 * A real transaction row as charge() would have created it: fiat (USD),
 * because charge() invoices in fiat even though the widget settles in
 * crypto. This is the row the CRITICAL regression test applies the webhook
 * update to.
 */
function digibloxTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'digiblox',
        'provider_transaction_id' => 'DEP-1',
        'type' => TransactionType::Deposit,
        'status' => PaymentStatus::Pending,
        'amount' => 150.00,
        'requested_amount' => 150.00,
        'currency' => 'USD',
        'metadata' => ['trading_account_login' => 70001],
    ], $overrides));
}

beforeEach(function () {
    Queue::fake();

    config()->set('cashier-core.connections.digiblox', [
        'driver' => 'digiblox',
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
        'webhook_header_name' => 'X-Digiblox-Token',
        'webhook_header_value' => 'shared-secret',
    ]);

    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

it('accepts a delivery carrying the registered header', function () {
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload(), [
        'X-Digiblox-Token' => 'shared-secret',
    ])->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

it('rejects a delivery with a wrong or missing header', function () {
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload(), [
        'X-Digiblox-Token' => 'wrong',
    ])->assertForbidden();

    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload())->assertForbidden();

    Queue::assertNothingPushed();
});

it('answers 200 to a delivery it cannot match, rather than burning a retry', function () {
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload([
        'external_transaction_id' => 'DEP-nobody-knows',
    ]), ['X-Digiblox-Token' => 'shared-secret'])->assertOk();
});

it('deduplicates on tx_hash, so a second payment on one link is not dropped', function () {
    $headers = ['X-Digiblox-Token' => 'shared-secret'];

    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xaaa']), $headers)->assertOk();
    // Same order, different hash — a genuine second payment.
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xbbb']), $headers)->assertOk();
    // Same hash as the first — a retry.
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload(['tx_hash' => '0xaaa']), $headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 2);
});

it('accepts all three verdicts', function (string $status) {
    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload([
        'status' => $status,
        'tx_hash' => '0x'.md5($status),
    ]), ['X-Digiblox-Token' => 'shared-secret'])->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
})->with(['COMPLETED', 'PARTIALLY_PAID', 'OVERPAID']);

it('deduplicates a delivery with no tx_hash instead of reprocessing it forever', function () {
    $headers = ['X-Digiblox-Token' => 'shared-secret'];
    $payload = digibloxWebhookPayload();
    unset($payload['tx_hash']);

    $this->postJson(route('cashier.webhooks.digiblox'), $payload, $headers)->assertOk();
    // Identical malformed delivery, retried — must dedupe on the raw body,
    // not bypass the guard and reprocess indefinitely.
    $this->postJson(route('cashier.webhooks.digiblox'), $payload, $headers)->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class, 1);
});

it('rejects an unconfigured header when verification is enabled, rather than opening the endpoint', function () {
    // The static header is this driver's only authenticity mechanism.
    // "Unconfigured" must never mean "unchecked" — otherwise an operator who
    // forgets DIGIBLOX_WEBHOOK_HEADER_NAME gets a fully open endpoint, in
    // production, with no signal at all.
    config()->set('cashier-core.webhooks.verify_signature', true);
    config()->set('cashier-core.connections.digiblox', [
        'driver' => 'digiblox',
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
    ]);

    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload())->assertForbidden();

    Queue::assertNothingPushed();
});

it('accepts a delivery with no header when verification is explicitly disabled outside production', function () {
    config()->set('cashier-core.webhooks.verify_signature', false);
    config()->set('cashier-core.connections.digiblox', [
        'driver' => 'digiblox',
        'base_url' => 'https://digiblox.test',
        'username' => 'merchant_alpha',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'merchant_id' => 'M1',
    ]);

    $this->postJson(route('cashier.webhooks.digiblox'), digibloxWebhookPayload())->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
});

/*
 * CRITICAL regression: every successful deposit was being held and never
 * credited, because fromWebhook() reported the crypto total_amount/currency
 * ("150.000000" USDT) against a transaction invoiced in fiat (USD).
 * WebhookProcessor::deviationBeyondTolerance() saw "USDT != USD" on every
 * single COMPLETED webhook and flipped Succeeded -> OnHold, so DepositSucceeded
 * never fired and the ledger was never credited. This drives the real
 * DigibloxAdapter::fromWebhook() output through the real WebhookProcessor
 * against a real (fiat) transaction row — not a hand-built
 * TransactionWebhookUpdate — because a mock update is exactly what let this
 * survive nine task reviews.
 */
describe('the webhook pipeline, end to end (the bug that shipped)', function () {
    it('credits a COMPLETED deposit as Succeeded, not OnHold', function () {
        $transaction = digibloxTransaction();

        $update = app(DigibloxAdapter::class)->fromWebhook(digibloxWebhookPayload([
            'status' => 'COMPLETED',
            'expected_amount' => '150.000000',
            'total_amount' => '150.000000',
            'currency' => 'USDT',
        ]));

        $applied = app(WebhookProcessor::class)->applyUpdate('digiblox', $transaction, $update);

        $fresh = $transaction->fresh();

        expect($applied)->toBeTrue()
            ->and($fresh->status)->toBe(PaymentStatus::Succeeded)
            ->and($fresh->status)->not->toBe(PaymentStatus::OnHold);

        // The ledger was actually credited, for the invoiced fiat amount —
        // not silently skipped because the transaction was held.
        $this->ledger->assertMovedCount(1);
        $this->ledger->assertMoved('credit', fn (array $m) => $m['account'] === 70001
            && $m['amount'] === 150.0
            && $m['currency'] === 'USD');
    });

    it('still holds a PARTIALLY_PAID deposit as OnHold, funds real but order not covered', function () {
        $transaction = digibloxTransaction();

        $update = app(DigibloxAdapter::class)->fromWebhook(digibloxWebhookPayload([
            'status' => 'PARTIALLY_PAID',
            'expected_amount' => '150.000000',
            'total_amount' => '120.000000',
            'amount' => '119.760000',
            'currency' => 'USDT',
        ]));

        app(WebhookProcessor::class)->applyUpdate('digiblox', $transaction, $update);

        $fresh = $transaction->fresh();

        expect($fresh->status)->toBe(PaymentStatus::OnHold);

        $this->ledger->assertNothingMoved();
    });
});
