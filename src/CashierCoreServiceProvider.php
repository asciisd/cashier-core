<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Contracts\ResolvesFundingAccount;
use Asciisd\CashierCore\Registry\PaymentProviderRegistry;
use Asciisd\CashierCore\Support\NullLedger;
use Asciisd\CashierCore\Support\PassthroughFundingAccountResolver;
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

    public function boot(): void
    {
        $this->publishConfiguration();
        $this->loadMigrations();
        $this->registerCommands();
        $this->registerRateLimiter();
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
