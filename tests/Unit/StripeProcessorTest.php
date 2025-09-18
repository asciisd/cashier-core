<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Unit;

use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\RefundStatus;
use Asciisd\CashierCore\Exceptions\InvalidPaymentDataException;
use Asciisd\CashierCore\Processors\StripeProcessor;
use Asciisd\CashierCore\Tests\TestCase;

class StripeProcessorTest extends TestCase
{
    private StripeProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new StripeProcessor([
            'secret_key' => 'sk_test_fake_key',
            'public_key' => 'pk_test_fake_key',
            'currency' => 'USD',
        ]);
    }

    public function test_processor_name(): void
    {
        $this->assertEquals('stripe', $this->processor->getName());
    }

    public function test_supported_features(): void
    {
        $this->assertTrue($this->processor->supports('charge'));
        $this->assertTrue($this->processor->supports('refund'));
        $this->assertTrue($this->processor->supports('capture'));
        $this->assertTrue($this->processor->supports('authorize'));
        $this->assertTrue($this->processor->supports('void'));
        $this->assertTrue($this->processor->supports('webhooks'));
        $this->assertTrue($this->processor->supports('recurring'));
        $this->assertFalse($this->processor->supports('unknown_feature'));
    }

    public function test_successful_charge(): void
    {
        $data = [
            'amount' => 1000,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Test payment',
        ];

        $result = $this->processor->charge($data);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(PaymentStatus::Succeeded, $result->status);
        $this->assertEquals(1000, $result->amount);
        $this->assertEquals('USD', $result->currency);
        $this->assertStringStartsWith('ch_', $result->transactionId);
    }

    public function test_charge_validation(): void
    {
        $this->expectException(InvalidPaymentDataException::class);

        $this->processor->charge([
            'currency' => 'USD',
            // Missing required amount
        ]);
    }

    public function test_successful_refund(): void
    {
        $result = $this->processor->refund('ch_test_transaction', 500);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(RefundStatus::Succeeded, $result->status);
        $this->assertEquals('ch_test_transaction', $result->originalTransactionId);
        $this->assertStringStartsWith('re_', $result->refundId);
    }

    public function test_successful_capture(): void
    {
        $result = $this->processor->capture('ch_test_transaction', 1000);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(PaymentStatus::Succeeded, $result->status);
    }

    public function test_successful_authorization(): void
    {
        $data = [
            'amount' => 1000,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Test authorization',
        ];

        $result = $this->processor->authorize($data);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(PaymentStatus::Succeeded, $result->status);
    }

    public function test_get_payment_status(): void
    {
        $status = $this->processor->getPaymentStatus('ch_test_transaction');

        $this->assertEquals(PaymentStatus::Succeeded->value, $status);
    }

    public function test_validate_payment_data(): void
    {
        $data = [
            'amount' => 1000,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Test payment',
        ];

        $validated = $this->processor->validatePaymentData($data);

        $this->assertEquals($data, $validated);
    }
}
