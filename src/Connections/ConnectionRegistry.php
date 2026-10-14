<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Connections;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use BackedEnum;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves provider instances from named connections.
 *
 * Replaces both the 1.x PaymentFactory (config `cashier-core.processors`) and
 * the 1.x PaymentProviderRegistry (app-owned `transactions.providers`): one
 * resolution path, package-owned config, container-built instances carrying
 * their connection's config array.
 *
 * Config is read per call rather than cached at construction, so the class is
 * safe to bind as a singleton and honest under runtime config changes (tests,
 * tenant switches).
 */
class ConnectionRegistry
{
    public function __construct(protected Container $container) {}

    /**
     * Resolve the provider behind a connection name (or a BackedEnum whose
     * value names a connection or driver).
     *
     * @throws ProcessorNotFoundException
     */
    public function get(BackedEnum|string $connection): PaymentProcessorInterface
    {
        $name = $connection instanceof BackedEnum ? (string) $connection->value : $connection;

        $config = Connections::get($name);

        if ($config === null) {
            throw new ProcessorNotFoundException("Payment connection '{$name}' not found.");
        }

        $class = $this->providerClass($name, $config);

        return $this->container->make($class, ['config' => $config]);
    }

    public function getDefault(): PaymentProcessorInterface
    {
        $default = config('cashier-core.default_connection')
            ?? config('transactions.default_provider', 'manual');

        return $this->get((string) $default);
    }

    public function has(BackedEnum|string $connection): bool
    {
        $name = $connection instanceof BackedEnum ? (string) $connection->value : $connection;

        return Connections::exists($name);
    }

    /**
     * @return list<string>
     */
    public function getAvailable(): array
    {
        return Connections::names();
    }

    /**
     * The provider class for a connection: an explicit `class` key wins,
     * otherwise the driver map supplies it.
     *
     * @param  array<string, mixed>  $config
     * @return class-string<PaymentProcessorInterface>
     *
     * @throws ProcessorNotFoundException
     */
    protected function providerClass(string $name, array $config): string
    {
        $class = $config['class'] ?? null;

        if (! is_string($class) || $class === '') {
            $driver = Connections::normalizeDriver($config['driver'] ?? null);
            $class = $driver === null ? null : config("cashier-core.drivers.{$driver}");
        }

        if (! is_string($class) || ! class_exists($class)) {
            throw new ProcessorNotFoundException(
                "Payment connection '{$name}' resolves to no provider class — set `class` on the connection or map its driver in cashier-core.drivers."
            );
        }

        return $class;
    }
}
