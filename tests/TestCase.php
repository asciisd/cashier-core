<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests;

use Asciisd\CashierCore\CashierCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('cashier-core.processors.stripe.config', [
            'secret_key' => 'sk_test_fake_key',
            'public_key' => 'pk_test_fake_key',
            'currency' => 'USD',
        ]);

        config()->set('cashier-core.processors.paypal.config', [
            'client_id' => 'fake_client_id',
            'client_secret' => 'fake_client_secret',
            'mode' => 'sandbox',
            'currency' => 'USD',
        ]);
    }
}
