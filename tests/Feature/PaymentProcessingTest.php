<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Feature;

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Facades\PaymentFactory;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_payment_through_factory(): void
    {
        $processor = PaymentFactory::create('stripe');

        $result = $processor->charge([
            'amount' => 2000,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Integration test payment',
        ]);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(2000, $result->amount);
        $this->assertEquals('USD', $result->currency);
    }

    public function test_can_create_transaction_record(): void
    {
        $processor = PaymentFactory::create('stripe');

        $result = $processor->charge([
            'amount' => 1500,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Test transaction record',
        ]);

        // Create a transaction record manually for testing
        $transaction = Transaction::create([
            'processor_name' => 'stripe',
            'processor_transaction_id' => $result->transactionId,
            'payable_type' => 'App\\Models\\User',
            'payable_id' => '123e4567-e89b-12d3-a456-426614174000',
            'amount' => $result->amount,
            'currency' => $result->currency,
            'status' => $result->status,
            'description' => 'Test transaction record',
            'processed_at' => now(),
        ]);

        $this->assertDatabaseHas('cashier_transactions', [
            'id' => $transaction->id,
            'processor_name' => 'stripe',
            'amount' => 1500,
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded->value,
        ]);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertFalse($transaction->isFailed());
        $this->assertEquals('15.00', $transaction->formatted_amount);
    }

    public function test_can_process_refund(): void
    {
        $processor = PaymentFactory::create('stripe');

        // First, create a successful payment
        $paymentResult = $processor->charge([
            'amount' => 3000,
            'currency' => 'USD',
            'source' => 'tok_visa',
            'description' => 'Payment to be refunded',
        ]);

        $this->assertTrue($paymentResult->isSuccessful());

        // Then refund it
        $refundResult = $processor->refund($paymentResult->transactionId, 1000);

        $this->assertTrue($refundResult->isSuccessful());
        $this->assertEquals(1000, $refundResult->amount);
        $this->assertEquals($paymentResult->transactionId, $refundResult->originalTransactionId);
    }

    public function test_can_switch_between_processors(): void
    {
        // Test Stripe
        $stripeProcessor = PaymentFactory::create('stripe');
        $stripeResult = $stripeProcessor->charge([
            'amount' => 1000,
            'currency' => 'USD',
            'source' => 'tok_visa',
        ]);

        $this->assertTrue($stripeResult->isSuccessful());
        $this->assertEquals('stripe', $stripeProcessor->getName());

        // Test PayPal
        $paypalProcessor = PaymentFactory::create('paypal');
        $paypalResult = $paypalProcessor->charge([
            'amount' => 2000,
            'currency' => 'USD',
        ]);

        $this->assertTrue($paypalResult->isSuccessful());
        $this->assertEquals('paypal', $paypalProcessor->getName());

        // Verify they are different instances
        $this->assertNotSame($stripeProcessor, $paypalProcessor);
    }
}
