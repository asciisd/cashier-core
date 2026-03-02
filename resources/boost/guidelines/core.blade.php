## Cashier Core (asciisd/cashier-core)

Flexible payment processing framework for Laravel using a factory pattern. Provides contracts, DTOs, enums, models, and an abstract base class for building provider-specific payment processors.

### Key Contracts

- `PaymentProcessorInterface` — Core processor contract: `charge()`, `refund()`, `capture()`, `authorize()`, `void()`, `retrieve()`, `parseWebhook()`, `verifyWebhookSignature()`, `supports()`.
- `PaymentAdapterInterface` — Maps provider responses to DTOs: `fromProviderResponse()`, `fromWebhook()`, `mapStatus()`.
- `BillingTransformerInterface` — Auto-populates billing details from user: `transform()`, `canHandle()`.
- `PaymentFactoryInterface` — Factory to create processors: `create()`, `register()`, `hasProcessor()`.

### Creating a Processor

1. Extend `AbstractPaymentProcessor`, implement `charge()` and `refund()`.
2. Set `$supportedFeatures` array (e.g., `['charge', 'refund', 'webhooks']`).
3. Override `getValidationRules()` to add processor-specific validation.
4. Use `$this->getConfig('key')` for processor config, `$this->createSuccessResult()` / `$this->createFailureResult()` for results.
5. Register in `config/cashier-core.php` under `processors`.

@verbatim
<code-snippet name="Processor Registration" lang="php">
// config/cashier-core.php
'processors' => [
    'my-provider' => [
        'class' => \App\Processors\MyProviderProcessor::class,
        'config' => [
            'api_key' => env('MY_PROVIDER_API_KEY'),
        ],
    ],
],
</code-snippet>
@endverbatim

### Usage

@verbatim
<code-snippet name="Using PaymentFactory" lang="php">
use Asciisd\CashierCore\Facades\PaymentFactory;

$result = PaymentFactory::create('my-provider')->charge([
    'amount' => 5000,
    'currency' => 'USD',
]);
</code-snippet>
@endverbatim

### Payable Trait

Add `Asciisd\CashierCore\Traits\Payable` to models that can make payments. Provides `transactions()`, `paymentMethods()`, `getTotalSpent()`, `getSuccessfulTransactions()`.

### Enums

- `PaymentStatus` — Pending, Processing, Succeeded, Failed, Canceled, RequiresAction, RequiresCapture, RequiresConfirmation, RequiresPaymentMethod.
- `PaymentMethodType` — CreditCard, DebitCard, BankTransfer, DigitalWallet, Cryptocurrency, Cash, Check, Other.
- `PaymentMethodBrand` — Visa, Mastercard, Amex, ApplePay, GooglePay, Bitcoin, etc.
- `RefundStatus` — Pending, Processing, Succeeded, Failed, Canceled.

### DTOs

- `PaymentResult` — charge/retrieve result with `isSuccessful()`, `requiresAction()`, `getRedirectUrl()`.
- `TransactionWebhookUpdate` — standardized webhook DTO with `toUpdateArray()`, `getMetadataWithTimestamp()`.
- `PaymentMethodSnapshot` — static factories: `fromCardData()`, `fromDigitalWallet()`, `fromBankTransfer()`, `fromCryptocurrency()`.
- `RefundResult` — refund outcome with `isSuccessful()`, `isFailed()`.

### Config

Config file: `config/cashier-core.php`. Key sections: `default`, `processors`, `currency`, `database`, `webhooks`, `logging`, `security`, `retry`, `features`.
