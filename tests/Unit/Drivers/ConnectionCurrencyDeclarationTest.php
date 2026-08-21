<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Contracts\PreparesChargeData;
use Asciisd\CashierCore\Drivers\Payport\PayportProvider;
use Asciisd\CashierCore\Drivers\Sticpay\SticpayProvider;

/*
 * Payport and Sticpay both let a connection pin the currency it invoices in,
 * and both used to resolve it privately inside charge(). PaymentService never
 * saw it, so it converted nothing and handed the driver the account-currency
 * figure, which the PSP then labelled EGP/SAR — the KWD incident's exact shape,
 * reachable by setting one env var.
 *
 * These pin the seam itself: the declared currency has to be visible on the
 * data PaymentService inspects, and the no-currency connection (production
 * today) has to keep resolving to the account currency so nothing converts.
 */

function currencyDeclarationCustomer(): CustomerContract
{
    return new class implements CustomerContract
    {
        public function cashierId(): int|string
        {
            return 11;
        }

        public function cashierEmail(): string
        {
            return 'customer@example.com';
        }

        public function cashierName(): string
        {
            return 'Test Customer';
        }

        public function cashierLocale(): string
        {
            return 'en';
        }
    };
}

/**
 * @return array{0: string, 1: PreparesChargeData}
 */
function currencyDeclarationDrivers(): array
{
    return [
        'payport' => fn (array $config) => new PayportProvider(array_merge([
            'base_url' => 'https://api.payme.center',
            'api_key' => 'test-api-key',
        ], $config)),
        'sticpay' => fn (array $config) => new SticpayProvider(array_merge([
            'base_url' => 'https://api.sticpay.com',
            'merchant_email' => 'merchant@example.com',
            'api_key' => 'test-api-key',
        ], $config)),
    ];
}

dataset('foreign capable drivers', currencyDeclarationDrivers());

it('declares the connection currency where PaymentService can convert it', function (Closure $make) {
    $provider = $make(['currency' => 'EGP']);

    expect($provider)->toBeInstanceOf(PreparesChargeData::class);

    $prepared = $provider->prepareChargeData(
        currencyDeclarationCustomer(),
        'foreign',
        ['amount' => 100.0, 'currency' => 'USD'],
    );

    expect($prepared['currency'])->toBe('EGP');
})->with('foreign capable drivers');

it('leaves a connection with no currency on the account currency, so nothing converts', function (Closure $make) {
    config()->set('cashier-core.currency.default', 'USD');

    // Blank, not absent: `PAYPORT_CURRENCY=` in an env file leaves the key
    // present holding an empty string, which is how an operator unsets it.
    $prepared = $make(['currency' => ''])->prepareChargeData(
        currencyDeclarationCustomer(),
        'domestic',
        ['amount' => 100.0, 'currency' => 'USD'],
    );

    expect($prepared['currency'])->toBe('USD');
})->with('foreign capable drivers');

it('upper-cases a lower-case connection currency so the engine and the PSP agree', function (Closure $make) {
    $prepared = $make(['currency' => 'sar'])->prepareChargeData(
        currencyDeclarationCustomer(),
        'foreign',
        ['amount' => 100.0, 'currency' => 'USD'],
    );

    expect($prepared['currency'])->toBe('SAR');
})->with('foreign capable drivers');
