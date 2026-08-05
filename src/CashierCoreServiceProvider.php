<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Registry\PaymentProviderRegistry;
use Illuminate\Support\ServiceProvider;

class CashierCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier-core.php',
            'cashier-core'
        );

        $this->registerConnectionRegistry();
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->loadMigrations();
        $this->registerCommands();
    }

    /**
     * One resolution path for providers. The deprecated 1.x class name stays
     * resolvable (as a subclass) so existing injection sites survive the
     * upgrade unchanged.
     */
    protected function registerConnectionRegistry(): void
    {
        if (! $this->app->bound(ConnectionRegistry::class)) {
            $this->app->singleton(ConnectionRegistry::class);
        }

        if (! $this->app->bound(PaymentProviderRegistry::class)) {
            $this->app->singleton(PaymentProviderRegistry::class);
        }

        $this->app->alias(ConnectionRegistry::class, 'cashier.connections');
    }

    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cashier-core.php' => config_path('cashier-core.php'),
            ], 'cashier-core-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cashier-core-migrations');
        }
    }

    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole() && Cashier::$runsMigrations) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // cashier:install / cashier:check / cashier:publish / cashier:purge
            ]);
        }
    }

    public function provides(): array
    {
        return [
            ConnectionRegistry::class,
            PaymentProviderRegistry::class,
            'cashier.connections',
        ];
    }
}
