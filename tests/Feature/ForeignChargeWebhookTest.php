<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Services\Webhooks\WebhookProcessor;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.webhooks.amount_tolerance_percent', 1.0);
});

function foreignTransaction(): Transaction
{
    return Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'myfatoorah',
        'connection' => 'myfatoorah',
        'provider_transaction_id' => 'mf-1',
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 100.00,
        'requested_amount' => 100.00,
        'currency' => 'USD',
        'charge_currency' => 'KWD',
        'charge_amount' => 31.1300,
        'conversion_rate' => 0.30670000,
    ]);
}

it('settles a webhook reported in the charge currency', function () {
    $transaction = foreignTransaction();

    app(WebhookProcessor::class)->applyUpdate('myfatoorah', $transaction, new TransactionWebhookUpdate(
        status: PaymentStatus::Succeeded,
        processorResponse: [],
        amount: 31.130,
        currency: 'KWD',
    ));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('holds a webhook whose charge-currency amount is out of tolerance', function () {
    $transaction = foreignTransaction();

    app(WebhookProcessor::class)->applyUpdate('myfatoorah', $transaction, new TransactionWebhookUpdate(
        status: PaymentStatus::Succeeded,
        processorResponse: [],
        amount: 12.000,
        currency: 'KWD',
    ));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::OnHold);
});
