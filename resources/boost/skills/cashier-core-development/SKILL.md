---
name: cashier-core-development
description: "Build payment processors, adapters, and billing integrations with the asciisd/cashier-core package. Activates when creating or extending payment processors, implementing PaymentProcessorInterface or PaymentAdapterInterface, working with PaymentResult or TransactionWebhookUpdate DTOs, configuring cashier-core.php, handling payment webhooks, using the PaymentFactory, adding the Payable trait, or working with Transaction/Refund/PaymentMethod models."
---

# Cashier Core Development

## Package Overview

`asciisd/cashier-core` is a flexible payment processing framework for Laravel using a factory pattern. It provides contracts, DTOs, enums, models, and an abstract base class for building provider-specific payment processors (e.g., Paytiko, Stripe, Square).

**Namespace:** `Asciisd\CashierCore`

## Architecture

```
Contracts/
├── PaymentProcessorInterface   — Core processor contract (charge, refund, webhooks)
├── PaymentAdapterInterface     — Maps provider responses to DTOs
├── PaymentFactoryInterface     — Factory for creating processors
└── BillingTransformerInterface — Auto-populates billing details from user

Abstracts/
└── AbstractPaymentProcessor    — Base class with defaults and helpers

DataObjects/
├── PaymentResult               — Charge/retrieve result
├── RefundResult                — Refund result
├── PaymentMethodSnapshot       — Card/wallet/crypto snapshot
└── TransactionWebhookUpdate    — Standardized webhook update DTO

Enums/
├── PaymentStatus               — Pending, Processing, Succeeded, Failed, etc.
├── RefundStatus                — Pending, Processing, Succeeded, Failed, Canceled
├── PaymentMethodType           — CreditCard, DebitCard, BankTransfer, etc.
└── PaymentMethodBrand          — Visa, Mastercard, ApplePay, Bitcoin, etc.

Models/
├── Transaction                 — Polymorphic, with scopes and accessors
├── Refund                      — Belongs to Transaction
└── PaymentMethod               — Polymorphic, with default/expiry logic

Services/
└── TransactionService          — updateFromWebhook(), findByProcessorTransactionId()

Traits/
└── Payable                     — Add to User model for transactions/payment methods
```

## Creating a Payment Processor

### Step 1: Extend AbstractPaymentProcessor

Implement `charge()` and `refund()` at minimum. Override other methods as needed.

```php
namespace Asciisd\CashierMyProvider;

use Asciisd\CashierCore\Abstracts\AbstractPaymentProcessor;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentStatus;

class MyProviderProcessor extends AbstractPaymentProcessor
{
    protected array $supportedFeatures = ['charge', 'refund', 'webhooks'];

    public function getName(): string
    {
        return 'my-provider';
    }

    public function charge(array $data): PaymentResult
    {
        $validated = $this->validatePaymentData($data);
        $apiKey = $this->getConfig('api_key');

        // Call provider API...
        $response = MyProviderApi::charge($validated, $apiKey);

        return $this->adapter->fromProviderResponse($response);
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        // Implement refund logic...
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        return $this->adapter->fromWebhook($payload);
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        // Verify using provider's signature algorithm
    }

    protected function getValidationRules(): array
    {
        return array_merge(parent::getValidationRules(), [
            'return_url' => 'required|url',
        ]);
    }
}
```

### Step 2: Implement PaymentAdapterInterface

Map provider-specific responses to cashier-core DTOs.

```php
namespace Asciisd\CashierMyProvider\Adapters;

use Asciisd\CashierCore\Contracts\PaymentAdapterInterface;
use Asciisd\CashierCore\DataObjects\PaymentMethodSnapshot;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Enums\PaymentMethodBrand;
use Asciisd\CashierCore\Enums\PaymentStatus;

class MyProviderAdapter implements PaymentAdapterInterface
{
    public function fromProviderResponse(mixed $response): PaymentResult
    {
        return new PaymentResult(
            success: $response->isSuccessful(),
            transactionId: $response->id,
            status: $this->mapStatus($response->status),
            amount: $response->amount,
            currency: $response->currency,
        );
    }

    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult
    {
        return new PaymentResult(
            success: $payload['status'] === 'approved',
            transactionId: $transactionId,
            status: $this->mapStatus($payload['status']),
            amount: (int) $payload['amount'],
            currency: $payload['currency'],
            processorResponse: $payload,
        );
    }

    public function fromWebhook(array $payload): TransactionWebhookUpdate
    {
        return new TransactionWebhookUpdate(
            status: $this->mapStatus($payload['status']),
            processorResponse: $payload,
            paymentMethodSnapshot: PaymentMethodSnapshot::fromCardData(
                brand: PaymentMethodBrand::from($payload['card_brand']),
                lastFour: $payload['card_last_four'],
            ),
        );
    }

    public function mapStatus(mixed $providerStatus): PaymentStatus
    {
        return match ($providerStatus) {
            'approved', 'success' => PaymentStatus::Succeeded,
            'pending' => PaymentStatus::Pending,
            'declined', 'error' => PaymentStatus::Failed,
            default => PaymentStatus::Processing,
        };
    }

    public function getProviderName(): string
    {
        return 'my-provider';
    }
}
```

### Step 3: Register in Config

Add the processor to `config/cashier-core.php`:

```php
'processors' => [
    'my-provider' => [
        'class' => \Asciisd\CashierMyProvider\MyProviderProcessor::class,
        'config' => [
            'api_key' => env('MY_PROVIDER_API_KEY'),
            'secret' => env('MY_PROVIDER_SECRET'),
        ],
    ],
],
```

### Step 4: Use the Factory

```php
use Asciisd\CashierCore\Facades\PaymentFactory;

$processor = PaymentFactory::create('my-provider');
$result = $processor->charge([
    'amount' => 5000,
    'currency' => 'USD',
    'return_url' => 'https://example.com/callback',
]);

if ($result->isSuccessful()) {
    // Handle success
} elseif ($result->requiresAction()) {
    return redirect($result->getRedirectUrl());
}
```

## BillingTransformerInterface

Auto-populate billing details from the authenticated user before sending to the processor.

```php
use Asciisd\CashierCore\Contracts\BillingTransformerInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class MyProviderBillingTransformer implements BillingTransformerInterface
{
    public function transform(Authenticatable $user, array $paymentData): array
    {
        return array_merge($paymentData, [
            'billing_first_name' => $user->first_name,
            'billing_email' => $user->email,
            'billing_country' => $user->country,
        ]);
    }

    public function canHandle(string $processor): bool
    {
        return $processor === 'my-provider';
    }

    public function getProcessorName(): string
    {
        return 'my-provider';
    }
}
```

## Payable Trait

Add to any model (typically `User`) that can make payments:

```php
use Asciisd\CashierCore\Traits\Payable;

class User extends Authenticatable
{
    use Payable;
}
```

Provides: `transactions()`, `paymentMethods()`, `getDefaultPaymentMethod()`, `getTotalSpent()`, `getSuccessfulTransactions()`, `getFailedTransactions()`, `getPendingTransactions()`.

## Key DTOs

### PaymentResult (readonly)

Properties: `success`, `transactionId`, `status` (PaymentStatus), `amount`, `currency`, `message`, `metadata`, `processorResponse`, `errorCode`, `paymentMethodSnapshot`.

Methods: `isSuccessful()`, `isFailed()`, `requiresAction()`, `getRedirectUrl()`, `toArray()`.

### TransactionWebhookUpdate (readonly)

Properties: `status` (PaymentStatus), `processorResponse`, `paymentMethodSnapshot`, `metadata`, `errorCode`, `errorMessage`, `amount`, `currency`, `description`, `additionalAttributes`.

Methods: `toUpdateArray()`, `getMetadataWithTimestamp()`.

### PaymentMethodSnapshot (readonly)

Properties: `type` (PaymentMethodType), `brand` (PaymentMethodBrand), `lastFour`, `displayName`.

Static factories: `fromArray()`, `fromCardData()`, `fromDigitalWallet()`, `fromBankTransfer()`, `fromCryptocurrency()`, `fromCash()`.

### RefundResult (readonly)

Properties: `success`, `refundId`, `originalTransactionId`, `status` (RefundStatus), `amount`, `currency`, `message`, `metadata`, `processorResponse`, `errorCode`.

## PaymentStatus Enum

| Case | Helpers |
|------|---------|
| `Pending` | `isPending()` |
| `Processing` | `isProcessing()` |
| `Succeeded` | `isSuccessful()`, `isFinal()` |
| `Failed` | `isFailed()`, `isFinal()` |
| `Canceled` | `isCanceled()`, `isFinal()` |
| `RequiresAction` | `requiresAction()` |
| `RequiresCapture` | `requiresAction()` |
| `RequiresConfirmation` | `requiresAction()` |
| `RequiresPaymentMethod` | `requiresAction()` |

## TransactionService

Use for webhook processing:

```php
use Asciisd\CashierCore\Services\TransactionService;

$service = app(TransactionService::class);
$transaction = $service->findByProcessorTransactionId($orderId, 'my-provider');

if ($transaction) {
    $service->updateFromWebhook($transaction, $webhookUpdate);
}
```

## Transaction Model Scopes

`successful()`, `failed()`, `pending()`, `byProcessor($name)`, `byAmount($min, $max)`, `byCurrency($currency)`, `byPaymentMethodType($type)`, `byPaymentMethodBrand($brand)`, `cardPayments()`, `digitalWalletPayments()`, `cryptocurrencyPayments()`.

## Configuration

Config file: `config/cashier-core.php`

Key sections: `default` (processor), `processors` (class + config), `currency` (default + supported), `database` (connection + table names), `webhooks` (enabled, tolerance, signature verification), `logging`, `security`, `retry`, `features` (refunds, partial_refunds, recurring, payment_methods_storage, webhooks).

## Migrations

Three tables published via service provider:
- `cashier_transactions` — polymorphic `payable`, processor fields, payment method fields, status, amounts
- `cashier_payment_methods` — type, brand, last four, expiry, default flag
- `cashier_refunds` — belongs to transaction, status, amounts

## Exceptions

| Exception | When |
|-----------|------|
| `InvalidPaymentDataException` | Validation fails in `validatePaymentData()` |
| `PaymentProcessingException` | Processor encounters an error (accepts `transactionId`, `processorResponse`) |
| `ProcessorNotFoundException` | Factory cannot find registered processor |
