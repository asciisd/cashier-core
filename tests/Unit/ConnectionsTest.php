<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;

beforeEach(function () {
    config()->set('cashier-core.connections', [
        'acme' => ['driver' => 'acme', 'api_key' => 'k1'],
        'acme_second' => ['driver' => 'acme', 'api_key' => 'k2'],
        'crypto_pay' => ['driver' => ConnectionsTestDriver::CryptoPay, 'currencies' => ['USDTTRC20', 'usdc']],
    ]);
});

it('lists configured connection names', function () {
    expect(Connections::names())->toBe(['acme', 'acme_second', 'crypto_pay']);
});

it('answers existence and raw config', function () {
    expect(Connections::exists('acme'))->toBeTrue()
        ->and(Connections::exists('nope'))->toBeFalse()
        ->and(Connections::get('acme'))->toMatchArray(['api_key' => 'k1']);
});

it('resolves the driver string behind a connection', function () {
    expect(Connections::driverFor('acme'))->toBe('acme');
});

it('normalizes enum-declared drivers to their string value', function () {
    // Hosts may keep declaring drivers with their own enum cases.
    expect(Connections::driverFor('crypto_pay'))->toBe('crypto_pay');
});

it('throws loudly for an unknown connection', function () {
    Connections::driverFor('ghost');
})->throws(ProcessorNotFoundException::class);

it('throws for a connection with no valid driver', function () {
    config()->set('cashier-core.connections.bad', ['api_key' => 'x']);

    Connections::driverFor('bad');
})->throws(ProcessorNotFoundException::class);

it('finds every connection sharing a driver, by string or enum', function () {
    expect(Connections::forDriver('acme'))->toBe(['acme', 'acme_second'])
        ->and(Connections::forDriver(ConnectionsTestDriver::CryptoPay))->toBe(['crypto_pay']);
});

it('lowercases connection currencies', function () {
    expect(Connections::currencies('crypto_pay'))->toBe(['usdttrc20', 'usdc'])
        ->and(Connections::currencies('acme'))->toBe([]);
});

enum ConnectionsTestDriver: string
{
    case CryptoPay = 'crypto_pay';
}
