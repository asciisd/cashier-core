<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Models\Transaction;

/**
 * Static configuration seams for the package, following the house pattern.
 *
 * Unlike some earlier packages, the service provider actually reads these
 * flags — calling ignoreRoutes() / ignoreMigrations() in a host provider's
 * register() genuinely prevents the registration.
 */
final class Cashier
{
    public const VERSION = '2.0.0-dev';

    /**
     * Indicates if the package's webhook routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if the package's migrations will be loaded.
     */
    public static bool $runsMigrations = true;

    /**
     * Configure the package to not register its webhook routes.
     */
    public static function ignoreRoutes(): void
    {
        static::$registersRoutes = false;
    }

    /**
     * Configure the package to not load its migrations.
     */
    public static function ignoreMigrations(): void
    {
        static::$runsMigrations = false;
    }

    /**
     * The transaction model class the pipeline reads and writes.
     *
     * @return class-string<Transaction>
     */
    public static function transactionModel(): string
    {
        return config('cashier-core.models.transaction', Transaction::class);
    }

    /**
     * The host application's customer model, when bound.
     *
     * @return class-string<CustomerContract>|null
     */
    public static function customerModel(): ?string
    {
        return config('cashier-core.models.customer');
    }
}
