<?php

declare(strict_types=1);

/**
 * Basic Usage Examples for Cashier Core
 * 
 * This file demonstrates how to use the Cashier Core package
 * for payment processing with different payment processors.
 */

use Asciisd\CashierCore\Facades\PaymentFactory;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Models\PaymentMethod;
use Asciisd\CashierCore\Enums\PaymentStatus;

// Example 1: Basic Payment Processing
function processBasicPayment()
{
    // Create a payment processor (replace 'custom' with your processor)
    $processor = PaymentFactory::create('custom');

    // Process a payment
    $result = $processor->charge([
        'amount' => 20, // $20.00
        'currency' => 'USD',
        'description' => 'Order #12345',
        'metadata' => [
            'order_id' => '12345',
            'customer_id' => 'cust_123',
        ],
    ]);

    if ($result->isSuccessful()) {
        echo "Payment successful! Transaction ID: {$result->transactionId}\n";
        
        // Save to database
        Transaction::create([
            'processor_name' => 'custom',
            'processor_transaction_id' => $result->transactionId,
            'payable_type' => 'App\\Models\\User',
            'payable_id' => 'user-uuid-here',
            'amount' => $result->amount,
            'currency' => $result->currency,
            'status' => $result->status,
            'description' => 'Order #12345',
            'metadata' => $result->metadata,
            'processed_at' => now(),
        ]);
    } else {
        echo "Payment failed: {$result->message}\n";
    }
}

// Example 2: Refund Processing
function processRefund(string $transactionId)
{
    $processor = PaymentFactory::create('custom');

    // Full refund
    $refundResult = $processor->refund($transactionId);

    if ($refundResult->isSuccessful()) {
        echo "Refund successful! Refund ID: {$refundResult->refundId}\n";
    }

    // Partial refund
    $partialRefundResult = $processor->refund($transactionId, 500); // $5.00

    if ($partialRefundResult->isSuccessful()) {
        echo "Partial refund successful! Refund ID: {$partialRefundResult->refundId}\n";
    }
}

// Example 3: Authorization and Capture
function authorizeAndCapture()
{
    $processor = PaymentFactory::create('custom');

    // Authorize payment
    $authResult = $processor->authorize([
        'amount' => 3000,
        'currency' => 'USD',
        'source' => 'tok_visa',
        'description' => 'Pre-authorization for order',
    ]);

    if ($authResult->isSuccessful()) {
        echo "Authorization successful! Transaction ID: {$authResult->transactionId}\n";

        // Later, capture the payment
        $captureResult = $processor->capture($authResult->transactionId, 2500); // Capture $25.00

        if ($captureResult->isSuccessful()) {
            echo "Capture successful!\n";
        }
    }
}

// Example 4: Working with Payment Methods
function managePaymentMethods()
{
    // Create a payment method record
    $paymentMethod = PaymentMethod::create([
        'user_type' => 'App\\Models\\User',
        'user_id' => 'user-uuid-here',
        'processor_name' => 'custom',
        'processor_payment_method_id' => 'pm_1234567890',
        'type' => 'credit_card',
        'brand' => 'visa',
        'last_four' => '4242',
        'exp_month' => 12,
        'exp_year' => 2025,
        'is_default' => true,
    ]);

    echo "Payment method created: {$paymentMethod->display_name}\n";

    // Check if expired
    if ($paymentMethod->is_expired) {
        echo "Payment method is expired!\n";
    } elseif ($paymentMethod->is_expiring_soon) {
        echo "Payment method expires soon!\n";
    }
}

// Example 5: Error Handling
function handlePaymentErrors()
{
    try {
        $processor = PaymentFactory::create('custom');

        $result = $processor->charge([
            'amount' => 100,
            'currency' => 'USD',
            // Missing required source - will trigger validation error
        ]);

    } catch (\Asciisd\CashierCore\Exceptions\InvalidPaymentDataException $e) {
        echo "Invalid payment data: {$e->getMessage()}\n";
    } catch (\Asciisd\CashierCore\Exceptions\PaymentProcessingException $e) {
        echo "Payment processing failed: {$e->getMessage()}\n";
        if ($e->getTransactionId()) {
            echo "Transaction ID: {$e->getTransactionId()}\n";
        }
    } catch (\Asciisd\CashierCore\Exceptions\ProcessorNotFoundException $e) {
        echo "Processor not found: {$e->getMessage()}\n";
    }
}

// Example 6: Custom Processor Registration
function registerCustomProcessor()
{
    // Register a custom processor at runtime
    PaymentFactory::register('custom', \App\PaymentProcessors\CustomProcessor::class);

    if (PaymentFactory::hasProcessor('custom')) {
        $processor = PaymentFactory::create('custom');
        echo "Custom processor registered and created successfully!\n";
    }
}

// Example 7: Working with Transactions
function queryTransactions()
{
    // Get all successful transactions
    $successfulTransactions = Transaction::successful()->get();
    echo "Found {$successfulTransactions->count()} successful transactions\n";

    // Get transactions by processor
    $customTransactions = Transaction::byProcessor('custom')->get();
    echo "Found {$customTransactions->count()} custom processor transactions\n";

    // Get transactions by amount
    $highValueTransactions = Transaction::byAmount(10000)->get(); // $100.00+
    echo "Found {$highValueTransactions->count()} high-value transactions\n";

    // Get recent transactions
    $recentTransactions = Transaction::where('created_at', '>=', now()->subDays(7))->get();
    echo "Found {$recentTransactions->count()} transactions in the last 7 days\n";
}
