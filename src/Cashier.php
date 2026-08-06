<?php

declare(strict_types=1);

namespace Asciisd\CashierCore;

use Asciisd\CashierCore\Connections\Connections;
use Asciisd\CashierCore\Contracts\CustomerContract;
use Asciisd\CashierCore\Models\Refund;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Testing\CashierFake;
use Asciisd\CashierCore\Testing\FakeProvider;

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
     * The refund model, so a host can extend it the way it extends Transaction.
     *
     * @return class-string<Refund>
     */
    public static function refundModel(): string
    {
        return config('cashier-core.models.refund', Refund::class);
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

    /**
     * Point connections at a recording fake so no charge ever reaches a PSP.
     *
     * With no argument, every configured connection is faked (or the default
     * connection, when none are configured yet); pass names to fake a subset —
     * a name that isn't configured is created as a bare `driver = name`
     * connection. Returns the recorder for queuing results and asserting.
     *
     * @param  list<string>  $connections
     */
    public static function fake(array $connections = []): CashierFake
    {
        $fake = new CashierFake;

        app()->instance(CashierFake::class, $fake);

        if ($connections === []) {
            $connections = Connections::names()
                ?: [(string) config('cashier-core.default_connection', 'manual')];
        }

        foreach ($connections as $name) {
            config(["cashier-core.connections.{$name}" => array_merge(
                ['driver' => $name],
                (array) config("cashier-core.connections.{$name}", []),
                ['class' => FakeProvider::class, '__connection' => $name],
            )]);
        }

        return $fake;
    }

    /**
     * Configure a named connection with driver-appropriate test credentials —
     * the seam for exercising real drivers (with Http::fake) and the
     * WebhookSimulator without a .env full of sandbox secrets.
     *
     * The driver is taken from the overrides, the whole name, or the prefix
     * before the first underscore — so `fakeConnection('aps_binance')` is
     * enough to get a second APS account.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fakeConnection(string $name, array $overrides = []): void
    {
        $drivers = (array) config('cashier-core.drivers', []);

        $driver = $overrides['driver']
            ?? (isset($drivers[$name]) ? $name : null)
            ?? (isset($drivers[explode('_', $name)[0]]) ? explode('_', $name)[0] : $name);

        $defaults = match ($driver) {
            'aps' => [
                'base_url' => "https://{$name}.test",
                'merchant_guid' => "{$name}-merchant-guid",
                'app_token' => "{$name}-token",
                'app_secret' => "{$name}-secret",
                'callback_secret' => "{$name}-callback-secret",
                'deposit_method' => "{$name}-deposit-guid",
                'redirect_url' => 'https://members.example.com/payment/success',
                'webhook_url' => 'https://members.example.com/api/webhooks/aps',
                'checkout_host_map' => ['api.pci-gw.com' => 'form.pci-gw.com'],
            ],
            'jenapay' => [
                'checkout_url' => 'https://checkout.jenapay.test',
                'api_url' => 'https://api.jenapay.test',
                'merchant_key' => 'test-merchant',
                'password' => 'test-password',
                'success_url' => 'https://members.example.com/payment/success',
                'cancel_url' => 'https://members.example.com/payment/failed',
            ],
            'heropayment' => [
                'base_url' => 'https://hero.test',
                'api_key' => 'test-key',
                'api_secret' => 'test-secret',
                'success_url' => 'https://members.example.com/payment/success',
                'fail_url' => 'https://members.example.com/payment/failed',
                'webhook_url' => 'https://members.example.com/api/webhooks/heropayment',
                'currencies' => ['usdttrc20', 'usdt20', 'usdc'],
            ],
            'payport' => [
                'base_url' => 'https://payport.test',
                'api_key' => 'test-api5-key',
                'success_url' => 'https://members.example.com/payment/success',
                'cancel_url' => 'https://members.example.com/payment/failed',
                'webhook_url' => 'https://members.example.com/api/webhooks/payport',
            ],
            'sticpay' => [
                'base_url' => 'https://sticpay.test',
                'merchant_email' => 'merchant@sticpay.test',
                'api_key' => 'test-sticpay-key',
                'sign_type' => 'MD5',
                'interface_version' => 'live',
                'success_url' => 'https://members.example.com/payments/sticpay/return/success',
                'failure_url' => 'https://members.example.com/payments/sticpay/return/failure',
                'referrer_url' => 'https://members.example.com/payments/sticpay/return/cancel',
                'callback_url' => 'https://members.example.com/api/webhooks/sticpay',
                'confirm_with_detail_api' => false,
            ],
            default => [],
        };

        config(["cashier-core.connections.{$name}" => array_merge(
            $defaults,
            ['driver' => $driver],
            $overrides,
        )]);
    }
}
