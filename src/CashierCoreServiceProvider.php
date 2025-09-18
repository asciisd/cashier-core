<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Contracts\PaymentFactoryInterface;
use Asciisd\CashierCore\Factory\PaymentFactory;
use Illuminate\Support\ServiceProvider;

class CashierCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cashier-core.php',
            'cashier-core'
        );

        $this->registerPaymentFactory();
        $this->registerPaymentProcessors();
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->loadMigrations();
        $this->registerCommands();
    }

    protected function registerPaymentFactory(): void
    {
        $this->app->singleton(PaymentFactoryInterface::class, function ($app) {
            return new PaymentFactory($app);
        });

        $this->app->alias(PaymentFactoryInterface::class, 'cashier.factory');
    }

    protected function registerPaymentProcessors(): void
    {
        $this->app->afterResolving(PaymentFactoryInterface::class, function (PaymentFactoryInterface $factory) {
            $processors = config('cashier-core.processors', []);

            foreach ($processors as $name => $config) {
                if (isset($config['class'])) {
                    $factory->register($name, $config['class']);
                    
                    // Bind the processor with its configuration
                    $this->app->when($config['class'])
                              ->needs('$config')
                              ->give($config['config'] ?? []);
                }
            }
        });
    }

    protected function publishConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cashier-core.php' => config_path('cashier-core.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }
    }

    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add Artisan commands here if needed
            ]);
        }
    }

    public function provides(): array
    {
        return [
            PaymentFactoryInterface::class,
            'cashier.factory',
        ];
    }
}
