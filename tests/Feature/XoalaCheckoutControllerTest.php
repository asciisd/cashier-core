<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Drivers\Xoala\XoalaSignatureService;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Cashier::fakeConnection('xoala');
});

function xoalaTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'xoala',
        'connection' => 'xoala',
        'provider_transaction_id' => 'DEP-1',
        'type' => TransactionType::Deposit,
        'amount' => 50.0,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ], $overrides));
}

function xoalaBridgeUrl(string $id = 'DEP-1'): string
{
    return URL::temporarySignedRoute('cashier.checkout.xoala', now()->addMinutes(30), ['transaction' => $id]);
}

it('renders an auto-submitting form for a pending deposit', function () {
    xoalaTransaction();

    $response = $this->get(xoalaBridgeUrl());

    $response->assertOk()
        ->assertSee('https://secure-checkout-sandbox.xoala.com/transaction/Checkout', false)
        ->assertSee('name="merchantTransactionId" value="DEP-1"', false)
        ->assertSee('name="amount" value="50.00"', false);
});

it('renders a checksum the signature service agrees with', function () {
    xoalaTransaction();

    // Not merely "a checksum is present": the digest itself is the thing that
    // has to be right, and a form rendering the wrong one fails at the hosted
    // page with no useful message.
    $expected = (new XoalaSignatureService('11344', 'xoala-secure-key'))->forCheckout(
        'TestPartner',
        '50.00',
        'DEP-1',
        'https://members.example.com/payment/success',
    );

    $this->get(xoalaBridgeUrl())
        ->assertOk()
        ->assertSee('name="checksum" value="'.$expected.'"', false);
});

it('renders the charge leg on a converted deposit', function () {
    xoalaTransaction(['charge_amount' => 375.0, 'charge_currency' => 'SAR']);

    $this->get(xoalaBridgeUrl())
        ->assertOk()
        ->assertSee('name="amount" value="375.00"', false)
        ->assertSee('name="currency" value="SAR"', false);
});

it('refuses an unsigned url', function () {
    xoalaTransaction();

    $this->get('/cashier/xoala/checkout/DEP-1')->assertForbidden();
});

it('refuses an expired url', function () {
    xoalaTransaction();

    $url = URL::temporarySignedRoute('cashier.checkout.xoala', now()->subMinute(), ['transaction' => 'DEP-1']);

    $this->get($url)->assertForbidden();
});

it('refuses a url signed for a different transaction', function () {
    xoalaTransaction();

    // Tamper with the path while keeping a valid-looking signature.
    $url = str_replace('DEP-1', 'DEP-2', xoalaBridgeUrl());

    $this->get($url)->assertForbidden();
});

it('404s an unknown transaction', function () {
    $this->get(xoalaBridgeUrl('DEP-missing'))->assertNotFound();
});

it('404s a transaction belonging to another driver', function () {
    xoalaTransaction(['provider' => 'payport']);

    $this->get(xoalaBridgeUrl())->assertNotFound();
});

it('refuses to re-present a settled deposit as payable', function () {
    xoalaTransaction(['status' => PaymentStatus::Succeeded]);

    $this->get(xoalaBridgeUrl())->assertStatus(409);
});

it('refuses to re-present a failed deposit', function () {
    xoalaTransaction(['status' => PaymentStatus::Failed]);

    $this->get(xoalaBridgeUrl())->assertStatus(409);
});

it('offers a manual submit button when javascript is unavailable', function () {
    xoalaTransaction();

    $this->get(xoalaBridgeUrl())->assertSee('<noscript>', false);
});
