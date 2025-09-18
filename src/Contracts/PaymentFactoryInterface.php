<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

interface PaymentFactoryInterface
{
    /**
     * Create a payment processor instance
     */
    public function create(string $processor): PaymentProcessorInterface;

    /**
     * Register a new payment processor
     */
    public function register(string $name, string $class): void;

    /**
     * Get all registered processors
     */
    public function getRegisteredProcessors(): array;

    /**
     * Check if a processor is registered
     */
    public function hasProcessor(string $name): bool;
}
