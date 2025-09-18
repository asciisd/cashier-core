<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Facades;

use Asciisd\CashierCore\Contracts\PaymentFactoryInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Asciisd\CashierCore\Contracts\PaymentProcessorInterface create(string $processor)
 * @method static void register(string $name, string $class)
 * @method static array getRegisteredProcessors()
 * @method static bool hasProcessor(string $name)
 * @method static array getProcessorNames()
 * @method static void registerMultiple(array $processors)
 */
class PaymentFactory extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentFactoryInterface::class;
    }
}
