<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Connections;

use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use BackedEnum;

/**
 * Read access to the named payment connections.
 *
 * A connection is one PSP account: a driver plus the credentials and routing
 * values for that account. Several connections may share a driver — two APS
 * merchant accounts are two connections — which is why payment methods name a
 * connection rather than a provider.
 *
 * The registry instantiates connections; this class only answers questions
 * about them, so admin options, request validation and webhook signature
 * matching all read the same source. Drivers are plain strings here — host
 * applications may layer their own enum over them for display and casts.
 */
final class Connections
{
    /**
     * Every configured connection name.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * The raw config for one connection, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $connection): ?array
    {
        $config = self::all()[$connection] ?? null;

        return is_array($config) ? $config : null;
    }

    public static function exists(string $connection): bool
    {
        return self::get($connection) !== null;
    }

    /**
     * The driver behind a connection — the value persisted to
     * `transactions.provider`.
     *
     * Throws rather than defaulting: a method naming a connection that no
     * longer exists must fail loudly at charge time. Silently falling back to
     * a default account is how a charge ends up authenticated with the wrong
     * merchant's credentials and rejected with nothing logged.
     *
     * @throws ProcessorNotFoundException
     */
    public static function driverFor(string $connection): string
    {
        $config = self::get($connection);

        if ($config === null) {
            throw new ProcessorNotFoundException("Payment connection '{$connection}' is not configured.");
        }

        $driver = self::normalizeDriver($config['driver'] ?? null);

        if ($driver === null) {
            throw new ProcessorNotFoundException("Payment connection '{$connection}' declares no valid driver.");
        }

        return $driver;
    }

    /**
     * Connection names sharing a driver, in config order.
     *
     * Used by webhook controllers: every account of a driver posts to the
     * same callback URL, so each account's secret is tried in turn — a
     * matching signature is itself the proof of which account sent it.
     *
     * @return list<string>
     */
    public static function forDriver(BackedEnum|string $driver): array
    {
        $wanted = $driver instanceof BackedEnum ? (string) $driver->value : $driver;

        $names = [];

        foreach (self::all() as $name => $config) {
            if (! is_array($config)) {
                continue;
            }

            if (self::normalizeDriver($config['driver'] ?? null) === $wanted) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * The currencies a connection accepts payment in, lowercased.
     *
     * @return list<string>
     */
    public static function currencies(string $connection): array
    {
        $currencies = self::get($connection)['currencies'] ?? [];

        if (! is_array($currencies)) {
            return [];
        }

        return array_values(array_map(
            fn ($currency): string => strtolower((string) $currency),
            $currencies
        ));
    }

    /**
     * A driver value from config, normalized to its string form. Hosts may
     * declare drivers as enum cases; the package treats them as strings.
     */
    public static function normalizeDriver(mixed $driver): ?string
    {
        if ($driver instanceof BackedEnum) {
            return (string) $driver->value;
        }

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function all(): array
    {
        $connections = (array) config('cashier-core.connections', []);

        if ($connections !== []) {
            return $connections;
        }

        // Transitional fallback: hosts migrating from the app-owned
        // `transactions.providers` map keep working until the config moves.
        return (array) config('transactions.providers', []);
    }
}
