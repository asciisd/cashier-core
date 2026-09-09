<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\ConvertsChargeCurrency;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;
use Asciisd\CashierCore\Registry\PaymentProviderRegistry;
use Asciisd\CashierCore\Support\NullLedger;
use Asciisd\CashierCore\Support\PassthroughFundingAccountResolver;
use Asciisd\CashierCore\Support\RefusingCurrencyConverter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CashierCoreServiceProvider extends ServiceProvider
{
    /**
     * Bundled driver classes, merged under host-declared entries so a host
     * (or plugin) mapping wins over the default.
     *
     * @var array<string, class-string>
     */
    private const BUNDLED_DRIVERS = [
        'aps' => Drivers\Aps\ApsProvider::class,
        'jenapay' => Drivers\Jenapay\JenapayProvider::class,
        'heropayment' => Drivers\Heropayment\HeropaymentProvider::class,
        'payport' => Drivers\Payport\PayportProvider::class,
        'sticpay' => Drivers\Sticpay\SticpayProvider::class,
        'myfatoorah' => Drivers\Myfatoorah\MyfatoorahProvider::class,
        'xoala' => Drivers\Xoala\XoalaProvider::class,
        'manual' => Drivers\Internal\ManualProvider::class,
        'bank_transfer' => Drivers\Internal\BankTransferProvider::class,
        'crypto' => Drivers\Internal\CryptoProvider::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cashier-core.php',
            'cashier-core'
        );

        config([
            'cashier-core.drivers' => array_merge(
                self::BUNDLED_DRIVERS,
                (array) config('cashier-core.drivers', []),
            ),
        ]);

        $this->registerConnectionRegistry();
        $this->registerLedger();
        $this->registerCurrencyConverter();
        $this->registerFundingAccountResolver();
    }

    /**
     * The default resolver honors an explicit `trading_account_login` and
     * nothing else; hosts that deposit by account reference bind their own.
     */
    protected function registerFundingAccountResolver(): void
    {
        if (! $this->app->bound(ResolvesFundingAccount::class)) {
            $this->app->singleton(ResolvesFundingAccount::class, PassthroughFundingAccountResolver::class);
        }
    }

    /**
     * Hosts bind their real ledger before this provider registers (or after —
     * the guard only fills the gap). The NullLedger default refuses every
     * movement loudly instead of pretending funds moved.
     */
    protected function registerLedger(): void
    {
        if (! $this->app->bound(FundsLedger::class)) {
            $this->app->singleton(FundsLedger::class, NullLedger::class);
        }
    }

    /**
     * Prices a charge leg for a PSP that cannot be sent the account's currency.
     *
     * The default refuses. A host with no foreign-currency connection never
     * reaches it, and one that adds a foreign driver without binding a
     * converter gets a loud failure rather than an invoice in the wrong
     * currency at face value.
     */
    protected function registerCurrencyConverter(): void
    {
        if (! $this->app->bound(ConvertsChargeCurrency::class)) {
            $this->app->singleton(ConvertsChargeCurrency::class, RefusingCurrencyConverter::class);
        }
    }

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->loadMigrations();
        $this->registerCommands();
        $this->registerRateLimiter();
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cashier-core');
        $this->registerRoutes();
    }

    /**
     * The package's webhook endpoints. The group supplies prefix, middleware
     * and name prefix from config so the routes file itself stays bare — a
     * host reshaping the group reshapes every endpoint at once. Disable with
     * `cashier-core.routes.enabled` or Cashier::ignoreRoutes() to register
     * your own.
     */
    protected function registerRoutes(): void
    {
        if (! Cashier::$registersRoutes || ! config('cashier-core.routes.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('cashier-core.routes.prefix', 'api/webhooks'),
            'as' => config('cashier-core.routes.name_prefix', 'cashier.webhooks.'),
            'middleware' => config('cashier-core.routes.middleware', ['api']),

            /*
             * Middleware to strip from the group. A host whose `api` group
             * already appends its own throttle must drop it here, or that
             * limiter and the webhook limiter both apply and the tighter one
             * wins — silently capping PSP retry bursts at the general API
             * budget, which delays payment settlement.
             */
            'excluded_middleware' => (array) config('cashier-core.routes.without_middleware', []),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        });

        Route::group([
            'prefix' => config('cashier-core.routes.checkout.prefix', 'cashier'),
            'as' => config('cashier-core.routes.checkout.name_prefix', 'cashier.checkout.'),
            'middleware' => config('cashier-core.routes.checkout.middleware', ['signed', 'throttle:cashier-checkout']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/checkout.php');
        });
    }

    /**
     * A default limiter behind the `throttle:cashier-webhooks` middleware in
     * the default route group. A host that defines its own limiter under the
     * same name before this provider boots keeps its own.
     */
    protected function registerRateLimiter(): void
    {
        if (RateLimiter::limiter('cashier-webhooks') === null) {
            RateLimiter::for(
                'cashier-webhooks',
                fn (Request $request) => Limit::perMinute(120)->by($request->ip())
            );
        }

        if (RateLimiter::limiter('cashier-checkout') === null) {
            RateLimiter::for(
                'cashier-checkout',
                fn (Request $request) => Limit::perMinute(30)->by($request->ip())
            );
        }
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

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/cashier-core'),
            ], 'cashier-core-views');
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
                Console\InstallCommand::class,
                Console\CheckCommand::class,
                Console\PublishCommand::class,
                Console\PurgeCommand::class,
                Console\EncryptHistoricalCommand::class,
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
