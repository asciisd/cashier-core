<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\ConvertsChargeCurrency;
use Asciisd\CashierCore\DataObjects\ChargeConversion;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\RefusingCurrencyConverter;

it('carries the converted amount, the rate and the margin', function () {
    $conversion = new ChargeConversion(
        amount: 31.130,
        rate: 0.3067,
        marginPct: 1.5,
        currency: 'KWD',
    );

    expect($conversion->amount)->toBe(31.130)
        ->and($conversion->rate)->toBe(0.3067)
        ->and($conversion->marginPct)->toBe(1.5)
        ->and($conversion->currency)->toBe('KWD')
        ->and($conversion->toArray())->toBe([
            'amount' => 31.130,
            'rate' => 0.3067,
            'margin_pct' => 1.5,
            'currency' => 'KWD',
        ]);
});

it('refuses to convert when no converter is bound rather than guessing a rate', function () {
    $converter = new RefusingCurrencyConverter;

    expect($converter)->toBeInstanceOf(ConvertsChargeCurrency::class);

    $converter->convert(100.0, 'USD', 'KWD');
})->throws(
    PaymentProcessingException::class,
    'No ConvertsChargeCurrency implementation is bound',
);

it('is bound to the refusing default when the host binds nothing', function () {
    expect(app(ConvertsChargeCurrency::class))
        ->toBeInstanceOf(RefusingCurrencyConverter::class);
});
