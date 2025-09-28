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
        // This test would need a real processor to work
        $this->markTestSkipped('No processors configured for testing');
    }

    public function test_can_create_transaction_record(): void
    {
        // Create a transaction record manually for testing
        $transaction = Transaction::create([
            'processor_name' => 'test',
            'processor_transaction_id' => 'test_123',
            'payable_type' => 'App\\Models\\User',
            'payable_id' => '123e4567-e89b-12d3-a456-426614174000',
            'amount' => 1500,
            'currency' => 'USD',
            'status' => PaymentStatus::Succeeded,
            'description' => 'Test transaction record',
            'processed_at' => now(),
        ]);

        $this->assertDatabaseHas('cashier_transactions', [
            'id' => $transaction->id,
            'processor_name' => 'test',
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
        // This test would need a real processor to work
        $this->markTestSkipped('No processors configured for testing');
    }

    public function test_can_switch_between_processors(): void
    {
        // This test would need real processors to work
        $this->markTestSkipped('No processors configured for testing');
    }
}
