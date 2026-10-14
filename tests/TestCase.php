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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // The encrypted payload casts need a key; testbench ships none.
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
