<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Registry;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Illuminate\Support\Facades\App;

class PaymentProviderRegistry
{
    private array $providers = [];

    public function __construct()
    {
        $this->loadProviders();
    }

    /**
     * Load providers from config.
     */
    private function loadProviders(): void
    {
        $config = config('transactions.providers', []);

        foreach ($config as $name => $settings) {
            if (isset($settings['class'])) {
                $this->providers[$name] = $settings;
            }
        }
    }

    /**
     * Get a provider instance by name or backed enum.
     */
    public function get(\BackedEnum|string $provider): PaymentProcessorInterface
    {
        $providerName = $provider instanceof \BackedEnum ? $provider->value : $provider;

        if (! isset($this->providers[$providerName])) {
            throw new ProcessorNotFoundException("Payment provider '{$providerName}' not found.");
        }

        $config = $this->providers[$providerName];

        return App::make($config['class'], ['config' => $config]);
    }

    /**
     * Get the default provider.
     */
    public function getDefault(): PaymentProcessorInterface
    {
        $defaultProvider = config('transactions.default_provider', 'paytiko');

        return $this->get($defaultProvider);
    }

    /**
     * Check if a provider exists.
     */
    public function has(\BackedEnum|string $provider): bool
    {
        $providerName = $provider instanceof \BackedEnum ? $provider->value : $provider;

        return isset($this->providers[$providerName]);
    }

    /**
     * Get all available provider names.
     */
    public function getAvailable(): array
    {
        return array_keys($this->providers);
    }
}
