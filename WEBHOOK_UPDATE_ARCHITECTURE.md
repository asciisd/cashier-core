# Webhook Update Architecture

## Overview

This document describes the standardized webhook transaction update architecture implemented in the cashier-core package. This architecture provides a consistent way for all payment processor drivers to update transactions from webhook responses.

## Architecture Components

### 1. TransactionWebhookUpdate DTO

**Location:** `src/DataObjects/TransactionWebhookUpdate.php`

A Data Transfer Object that standardizes how payment processors communicate transaction updates to the core package.

**Properties:**

- `PaymentStatus $status` - The updated payment status
- `array $processorResponse` - Raw webhook payload from the payment processor
- `?PaymentMethodSnapshot $paymentMethodSnapshot` - Payment method details (optional)
- `?array $metadata` - Additional metadata to store (optional)
- `?string $errorCode` - Error code if payment failed (optional)
- `?string $errorMessage` - Error message if payment failed (optional)
- `?int $amount` - Transaction amount (optional, for updates)
- `?string $currency` - Transaction currency (optional, for updates)
- `?string $description` - Transaction description (optional, for updates)
- `array $additionalAttributes` - Any driver-specific attributes (optional)

**Key Methods:**

- `toUpdateArray()` - Converts the DTO to an array for database update
- `getMetadataWithTimestamp()` - Returns metadata with webhook update timestamp

### 2. TransactionService

**Location:** `src/Services/TransactionService.php`

A service class that handles transaction updates from webhooks using the standardized DTO.

**Methods:**

#### `updateFromWebhook(Transaction $transaction, TransactionWebhookUpdate $webhookUpdate): Transaction`

Updates a transaction from webhook data using the standardized DTO.

**Features:**

- Automatically handles payment method updates (only updates if missing)
- Merges metadata intelligently
- Sets timestamps based on status (processed_at, failed_at)
- Logs all updates
- Returns fresh transaction instance

#### `findByProcessorTransactionId(string $processorTransactionId, string $processorName): ?Transaction`

Helper method to find transactions by processor transaction ID.

## Usage Examples

### Example 1: Basic Webhook Update

```php
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Services\TransactionService;

class MyWebhookHandler
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    public function handle(array $webhookPayload): void
    {
        // Find transaction
        $transaction = $this->transactionService->findByProcessorTransactionId(
            $webhookPayload['order_id'],
            'my_processor'
        );

        // Create webhook update DTO
        $webhookUpdate = new TransactionWebhookUpdate(
            status: PaymentStatus::Succeeded,
            processorResponse: $webhookPayload,
            metadata: [
                'processor_transaction_id' => $webhookPayload['transaction_id'],
                'external_id' => $webhookPayload['external_id'],
            ]
        );

        // Update transaction
        $this->transactionService->updateFromWebhook($transaction, $webhookUpdate);
    }
}
```

### Example 2: Webhook Update with Payment Method

```php
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentMethodType;
use Asciisd\CashierCore\Enums\PaymentStatus;

$paymentMethodSnapshot = new PaymentMethodSnapshot(
    type: PaymentMethodType::CreditCard,
    brand: PaymentMethodBrand::Visa,
    lastFour: '4242',
    displayName: 'Visa •••• 4242'
);

$webhookUpdate = new TransactionWebhookUpdate(
    status: PaymentStatus::Succeeded,
    processorResponse: $webhookPayload,
    paymentMethodSnapshot: $paymentMethodSnapshot,
    metadata: [
        'card_brand' => 'visa',
        'card_country' => 'US',
    ]
);

$this->transactionService->updateFromWebhook($transaction, $webhookUpdate);
```

### Example 3: Failed Payment with Error Details

```php
$webhookUpdate = new TransactionWebhookUpdate(
    status: PaymentStatus::Failed,
    processorResponse: $webhookPayload,
    errorCode: 'insufficient_funds',
    errorMessage: 'The card has insufficient funds',
    metadata: [
        'decline_reason' => $webhookPayload['decline_reason'],
        'decline_code' => $webhookPayload['decline_code'],
    ]
);

$this->transactionService->updateFromWebhook($transaction, $webhookUpdate);
```

### Example 4: Update with Additional Attributes

```php
$webhookUpdate = new TransactionWebhookUpdate(
    status: PaymentStatus::Processing,
    processorResponse: $webhookPayload,
    metadata: [
        'processing_started_at' => $webhookPayload['processing_time'],
    ],
    additionalAttributes: [
        'description' => 'Payment is being processed by the bank',
        // Any driver-specific fields that don't have dedicated DTO properties
    ]
);

$this->transactionService->updateFromWebhook($transaction, $webhookUpdate);
```

## Benefits

### 1. Consistency

All drivers update transactions the same way, ensuring predictable behavior across different payment processors.

### 2. Type Safety

The DTO provides compile-time type checking, preventing runtime errors from incorrect data types.

### 3. Self-Documenting

The DTO structure clearly documents what data is expected and supported.

### 4. Maintainability

Changes to update logic only need to happen in one place (TransactionService).

### 5. Flexibility

The `additionalAttributes` parameter allows driver-specific customization when needed.

### 6. Testing

Easy to mock the DTO and test the service in isolation.

### 7. Clean Separation

Core package doesn't need to know about driver-specific implementation details.

## Implementation Pattern for Drivers

When implementing a new payment processor driver, follow this pattern:

1. **In your webhook handler or listener:**
   - Find the transaction
   - Extract relevant data from the webhook payload
   - Create a `TransactionWebhookUpdate` DTO
   - Call `TransactionService::updateFromWebhook()`

2. **Map processor-specific status to PaymentStatus enum:**

   ```php
   private function mapWebhookStatus(string $processorStatus): PaymentStatus
   {
       return match (strtolower($processorStatus)) {
           'success', 'completed' => PaymentStatus::Succeeded,
           'failed', 'declined' => PaymentStatus::Failed,
           'pending' => PaymentStatus::Pending,
           // ... other mappings
           default => PaymentStatus::Pending,
       };
   }
   ```

3. **Extract payment method details if available:**

   ```php
   private function extractPaymentMethod(array $payload): ?PaymentMethodSnapshot
   {
       if (!isset($payload['payment_method'])) {
           return null;
       }
       
       return new PaymentMethodSnapshot(
           type: $this->mapPaymentMethodType($payload['payment_method']['type']),
           brand: $this->mapPaymentMethodBrand($payload['payment_method']['brand']),
           lastFour: $payload['payment_method']['last_four'] ?? null,
           displayName: $payload['payment_method']['display_name'] ?? null
       );
   }
   ```

## Migration Notes

### Existing Code

If you have existing webhook handlers that directly update transactions:

```php
// OLD WAY - Direct update
$transaction->update([
    'status' => $status,
    'processor_response' => $webhookPayload,
    'metadata' => array_merge($transaction->metadata ?? [], [
        'webhook_data' => $someData,
    ]),
]);
```

### New Approach

Replace with the standardized approach:

```php
// NEW WAY - Using DTO and service
$webhookUpdate = new TransactionWebhookUpdate(
    status: $status,
    processorResponse: $webhookPayload,
    metadata: [
        'webhook_data' => $someData,
    ]
);

$this->transactionService->updateFromWebhook($transaction, $webhookUpdate);
```

## Configuration

The service uses the following configuration from `config/cashier-core.php`:

```php
'logging' => [
    'enabled' => env('CASHIER_LOGGING_ENABLED', true),
    // ... other logging config
],
```

When logging is enabled, all webhook updates are logged with relevant transaction details.

## Related Classes

- `Asciisd\CashierCore\Models\Transaction` - Transaction model
- `Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot` - Payment method data structure
- `Asciisd\CashierCore\Enums\PaymentStatus` - Payment status enum
- `Asciisd\CashierCore\Enums\PaymentMethodType` - Payment method type enum
- `Asciisd\CashierCore\Enums\PaymentMethodBrand` - Payment method brand enum

## Testing

Example test for a webhook handler using the architecture:

```php
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Services\TransactionService;
use Asciisd\CashierCore\Models\Transaction;

it('updates transaction from webhook', function () {
    $transaction = Transaction::factory()->create([
        'processor_name' => 'test_processor',
        'processor_transaction_id' => 'txn_123',
    ]);

    $service = app(TransactionService::class);
    
    $webhookUpdate = new TransactionWebhookUpdate(
        status: PaymentStatus::Succeeded,
        processorResponse: ['foo' => 'bar'],
        metadata: ['test' => 'data']
    );

    $updated = $service->updateFromWebhook($transaction, $webhookUpdate);

    expect($updated->status)->toBe(PaymentStatus::Succeeded);
    expect($updated->processor_response)->toHaveKey('foo');
    expect($updated->metadata)->toHaveKey('test');
    expect($updated->processed_at)->not->toBeNull();
});
```

## Future Enhancements

Potential future improvements to this architecture:

1. **Event Dispatching**: Dispatch events before/after transaction updates
2. **Audit Trail**: Track all changes to transactions with timestamps
3. **Validation**: Add validation rules for DTO properties
4. **Versioning**: Support for webhook payload version handling
5. **Retry Logic**: Built-in retry mechanism for failed updates
6. **Batch Updates**: Support for updating multiple transactions at once

## Support

For questions or issues with this architecture, please refer to:

- Package documentation
- Example implementations in `cashier-paytiko` driver
- Test files in `tests/` directory
