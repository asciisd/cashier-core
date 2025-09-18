<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Factory;

use Asciisd\CashierCore\Contracts\PaymentFactoryInterface;
use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Illuminate\Container\Container;

class PaymentFactory implements PaymentFactoryInterface
{
    protected array $processors = [];

    public function __construct(
        protected Container $container
    ) {}

    public function create(string $processor): PaymentProcessorInterface
    {
        if (!$this->hasProcessor($processor)) {
            throw new ProcessorNotFoundException("Payment processor '{$processor}' not found.");
        }

        $processorClass = $this->processors[$processor];

        return $this->container->make($processorClass);
    }

    public function register(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class '{$class}' does not exist.");
        }

        if (!is_subclass_of($class, PaymentProcessorInterface::class)) {
            throw new \InvalidArgumentException(
                "Class '{$class}' must implement PaymentProcessorInterface."
            );
        }

        $this->processors[$name] = $class;
    }

    public function getRegisteredProcessors(): array
    {
        return $this->processors;
    }

    public function hasProcessor(string $name): bool
    {
        return array_key_exists($name, $this->processors);
    }

    public function getProcessorNames(): array
    {
        return array_keys($this->processors);
    }

    public function registerMultiple(array $processors): void
    {
        foreach ($processors as $name => $class) {
            $this->register($name, $class);
        }
    }
}
