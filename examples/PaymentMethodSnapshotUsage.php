<?php

/**
 * Example: Using Payment Method Snapshots
 * 
 * This example demonstrates how the new payment method snapshot system works.
 * Instead of relying on foreign key relationships that can break when users
 * delete their payment methods, we now store a snapshot of the payment method
 * information directly in the transaction record.
 */

use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Models\Transaction;

// Example 1: Creating payment method snapshots for different payment types

// Credit Card Payment
$visaSnapshot = PaymentMethodSnapshot::fromCardData(
    brand: 'visa',
    lastFour: '4242',
    displayName: 'Visa •••• 4242'
);

$mastercardSnapshot = PaymentMethodSnapshot::fromCardData(
    brand: 'mastercard',
    lastFour: '5555'
);

// Digital Wallet Payments
$paypalSnapshot = PaymentMethodSnapshot::fromDigitalWallet('paypal');
$fawrySnapshot = PaymentMethodSnapshot::fromDigitalWallet('fawry');
$binancePaySnapshot = PaymentMethodSnapshot::fromDigitalWallet('binance_pay');

// Bank Transfer
$wireTransferSnapshot = PaymentMethodSnapshot::fromBankTransfer('wire_transfer');

// Cryptocurrency
$bitcoinSnapshot = PaymentMethodSnapshot::fromCryptocurrency('bitcoin');

// Cash Payment
$cashSnapshot = PaymentMethodSnapshot::fromCash();

// Example 2: Using snapshots when creating transactions
$transactionData = [
    'processor_name' => 'stripe',
    'processor_transaction_id' => 'ch_1234567890',
    'payable_type' => 'App\Models\User',
    'payable_id' => 1,
    'amount' => 100, // $100.00
    'currency' => 'USD',
    'status' => 'succeeded',
    'description' => 'Premium subscription payment',
    // Payment method snapshot fields
    ...$visaSnapshot->toArray()
];

// Create transaction with payment method snapshot
$transaction = Transaction::create($transactionData);

// Example 3: Querying transactions by payment method
$cardPayments = Transaction::cardPayments()->get();
$digitalWalletPayments = Transaction::digitalWalletPayments()->get();
$cryptoPayments = Transaction::cryptocurrencyPayments()->get();

// Filter by specific payment method brand
$visaPayments = Transaction::byPaymentMethodBrand(PaymentMethodBrand::Visa)->get();
$fawryPayments = Transaction::byPaymentMethodBrand(PaymentMethodBrand::Fawry)->get();

// Filter by payment method type
$creditCardPayments = Transaction::byPaymentMethodType(PaymentMethodType::CreditCard)->get();

// Example 4: Displaying payment method information
foreach ($cardPayments as $transaction) {
    echo "Payment Method: " . $transaction->payment_method_display . "\n";
    echo "Brand: " . $transaction->payment_method_brand->label() . "\n";
    echo "Type: " . $transaction->payment_method_type->label() . "\n";
    
    if ($transaction->hasCardDetails()) {
        echo "Last Four: " . $transaction->payment_method_last_four . "\n";
    }
    
    echo "Icon: " . $transaction->payment_method_brand->getIcon() . "\n";
    echo "---\n";
}

// Example 5: Benefits of the new approach

/**
 * BEFORE (with payment_method_id foreign key):
 * - If user deletes their saved payment method, transaction loses payment info
 * - Need to join with payment_methods table to get payment details
 * - Risk of orphaned transactions with null payment_method_id
 * 
 * AFTER (with payment method snapshots):
 * - Payment method information is preserved forever in the transaction
 * - No need for joins to display payment method details
 * - Rich filtering and querying capabilities
 * - Support for all payment types: cards, digital wallets, crypto, cash, etc.
 * - Consistent display names and icons
 * - Better reporting and analytics
 */

// Example 6: Creating snapshots from processor responses
function createSnapshotFromProcessorResponse(array $processorResponse): PaymentMethodSnapshot
{
    $paymentMethod = $stripeResponse['payment_method_details'];
    
    return match ($paymentMethod['type']) {
        'card' => PaymentMethodSnapshot::fromCardData(
            brand: $paymentMethod['card']['brand'],
            lastFour: $paymentMethod['card']['last4']
        ),
        'paypal' => PaymentMethodSnapshot::fromDigitalWallet('paypal'),
        'apple_pay' => PaymentMethodSnapshot::fromDigitalWallet('apple_pay'),
        'google_pay' => PaymentMethodSnapshot::fromDigitalWallet('google_pay'),
        default => PaymentMethodSnapshot::fromCardData('visa', '0000'),
    };
}

function createSnapshotFromPaytikoResponse(array $paytikoResponse): PaymentMethodSnapshot
{
    $method = $paytikoResponse['payment_method'];
    
    return match ($method['type']) {
        'fawry' => PaymentMethodSnapshot::fromDigitalWallet('fawry'),
        'vodafone' => PaymentMethodSnapshot::fromDigitalWallet('vodafone'),
        'instapay' => PaymentMethodSnapshot::fromDigitalWallet('instapay'),
        'binance_pay' => PaymentMethodSnapshot::fromDigitalWallet('binance_pay'),
        'wire_transfer' => PaymentMethodSnapshot::fromBankTransfer('wire_transfer'),
        default => PaymentMethodSnapshot::fromDigitalWallet('other'),
    };
}

// Example 7: Migration strategy
/**
 * For existing transactions with payment_method_id:
 * 1. Run a migration to populate the new fields from existing payment_methods
 * 2. Set payment_method_id to null after migration
 * 3. Remove the foreign key constraint
 * 
 * Migration pseudocode:
 * 
 * foreach (Transaction::whereNotNull('payment_method_id') as $transaction) {
 *     $paymentMethod = $transaction->paymentMethod;
 *     if ($paymentMethod) {
 *         $snapshot = PaymentMethodSnapshot::fromArray([
 *             'type' => $paymentMethod->type,
 *             'brand' => $paymentMethod->brand,
 *             'last_four' => $paymentMethod->last_four,
 *             'display_name' => $paymentMethod->display_name,
 *         ]);
 *         
 *         $transaction->update($snapshot->toArray());
 *     }
 * }
 */
