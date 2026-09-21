<?php

declare(strict_types=1);

use Asciisd\CashierCore\Jobs\ProcessPaymentProviderWebhook;
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
