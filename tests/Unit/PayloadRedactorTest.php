<?php

declare(strict_types=1);

use Asciisd\CashierCore\Support\PayloadRedactor;

beforeEach(function () {
    config()->set('cashier-core.security.redact_keys', [
        'email', 'client_ip', 'card*', 'iban', 'account_info',
    ]);

    $this->redactor = new PayloadRedactor;
});

it('masks configured keys and leaves the rest', function () {
    $clean = $this->redactor->redact([
        'order_id' => 'DEP-123',
        'email' => 'user@example.com',
        'client_ip' => '203.0.113.7',
        'amount' => '100.00',
    ]);

    expect($clean)->toBe([
        'order_id' => 'DEP-123',
        'email' => PayloadRedactor::MASK,
        'client_ip' => PayloadRedactor::MASK,
        'amount' => '100.00',
    ]);
});

it('matches wildcards and is case-insensitive', function () {
    $clean = $this->redactor->redact([
        'CardType' => 'VISA',
        'card_number' => '424242******4242',
        'Email' => 'user@example.com',
        'cart_total' => '10',
    ]);

    expect($clean['CardType'])->toBe(PayloadRedactor::MASK)
        ->and($clean['card_number'])->toBe(PayloadRedactor::MASK)
        ->and($clean['Email'])->toBe(PayloadRedactor::MASK)
        ->and($clean['cart_total'])->toBe('10');
});

it('redacts nested structures recursively', function () {
    $clean = $this->redactor->redact([
        'payment_info' => [
            'account_info' => '455691******7441',
            'bank' => ['iban' => 'KW81CBKU0000000000001234560101', 'name' => 'CBK'],
        ],
    ]);

    expect($clean['payment_info']['account_info'])->toBe(PayloadRedactor::MASK)
        ->and($clean['payment_info']['bank']['iban'])->toBe(PayloadRedactor::MASK)
        ->and($clean['payment_info']['bank']['name'])->toBe('CBK');
});

it('strips query strings and fragments from URLs', function () {
    expect($this->redactor->redactUrl('https://pay.example.com/session?token=one-time-secret#step'))
        ->toBe('https://pay.example.com/session')
        ->and($this->redactor->redactUrl(null))->toBeNull()
        ->and($this->redactor->redactUrl('https://pay.example.com/plain'))
        ->toBe('https://pay.example.com/plain');
});
