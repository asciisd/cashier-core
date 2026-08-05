<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Registry\PaymentProviderRegistry;
use Asciisd\CashierCore\Tests\Fixtures\FakeConnectionProvider;

beforeEach(function () {
    config()->set('cashier-core.connections', [
        'fake' => [
            'driver' => 'fake',
            'class' => FakeConnectionProvider::class,
            'api_key' => 'secret-1',
        ],
        'fake_second' => [
            'driver' => 'fake',
            'api_key' => 'secret-2',
        ],
    ]);

    config()->set('cashier-core.drivers', [
        'fake' => FakeConnectionProvider::class,
    ]);

    config()->set('cashier-core.default_connection', 'fake');
});

it('resolves a provider by connection name with its connection config', function () {
    $provider = app(ConnectionRegistry::class)->get('fake');

    expect($provider)->toBeInstanceOf(FakeConnectionProvider::class)
        ->and($provider->connectionConfig()['api_key'])->toBe('secret-1');
});

it('resolves the provider class from the driver map when the connection has none', function () {
    $provider = app(ConnectionRegistry::class)->get('fake_second');

    expect($provider)->toBeInstanceOf(FakeConnectionProvider::class)
        // Each connection carries its own credentials even on a shared driver.
        ->and($provider->connectionConfig()['api_key'])->toBe('secret-2');
});

it('resolves the default connection', function () {
    expect(app(ConnectionRegistry::class)->getDefault())
        ->toBeInstanceOf(FakeConnectionProvider::class);
});

it('throws for an unknown connection', function () {
    app(ConnectionRegistry::class)->get('missing');
})->throws(ProcessorNotFoundException::class);

it('throws when neither the connection nor the driver map supplies a class', function () {
    config()->set('cashier-core.connections.orphan', ['driver' => 'unmapped']);

    app(ConnectionRegistry::class)->get('orphan');
})->throws(ProcessorNotFoundException::class);

it('accepts backed enums wherever a connection name is expected', function () {
    $case = ConnectionRegistryTestDriver::Fake;

    expect(app(ConnectionRegistry::class)->has($case))->toBeTrue()
        ->and(app(ConnectionRegistry::class)->get($case))->toBeInstanceOf(FakeConnectionProvider::class);
});

it('keeps the deprecated 1.x registry name resolvable', function () {
    expect(app(PaymentProviderRegistry::class)->get('fake'))
        ->toBeInstanceOf(FakeConnectionProvider::class);
});

it('sees runtime config changes despite being a singleton', function () {
    $registry = app(ConnectionRegistry::class);

    expect($registry->has('late'))->toBeFalse();

    config()->set('cashier-core.connections.late', [
        'driver' => 'fake',
    ]);

    expect($registry->has('late'))->toBeTrue()
        ->and($registry->getAvailable())->toContain('late');
});

it('falls back to the legacy transactions.providers map when unconfigured', function () {
    config()->set('cashier-core.connections', []);
    config()->set('transactions.providers', [
        'legacy' => ['driver' => 'fake', 'class' => FakeConnectionProvider::class],
    ]);

    expect(Connections::names())->toBe(['legacy'])
        ->and(app(ConnectionRegistry::class)->get('legacy'))->toBeInstanceOf(FakeConnectionProvider::class);
});

enum ConnectionRegistryTestDriver: string
{
    case Fake = 'fake';
}
