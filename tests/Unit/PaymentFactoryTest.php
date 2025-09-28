<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Unit;

use Asciisd\CashierCore\Contracts\PaymentFactoryInterface;
use Asciisd\CashierCore\Exceptions\ProcessorNotFoundException;
use Asciisd\CashierCore\Tests\TestCase;

class PaymentFactoryTest extends TestCase
{
    private PaymentFactoryInterface $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = app(PaymentFactoryInterface::class);
    }

    public function test_throws_exception_for_unknown_processor(): void
    {
        $this->expectException(ProcessorNotFoundException::class);
        $this->expectExceptionMessage("Payment processor 'unknown' not found.");

        $this->factory->create('unknown');
    }

    public function test_can_check_if_processor_exists(): void
    {
        $this->assertFalse($this->factory->hasProcessor('unknown'));
    }

    public function test_can_get_registered_processors(): void
    {
        $processors = $this->factory->getRegisteredProcessors();

        $this->assertIsArray($processors);
    }

    public function test_can_get_processor_names(): void
    {
        $names = $this->factory->getProcessorNames();

        $this->assertIsArray($names);
    }
}
