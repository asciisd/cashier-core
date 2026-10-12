# PayCrossBorder Payouts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `paycrossborder` driver that sends approved withdrawals through PayCrossBorder's crypto, bank and mobile-wallet payout rails and closes them by polling PCB's transaction enquiry.

**Architecture:** `PayCrossBorderProvider` implements `PaymentProcessorInterface` (deposit side unsupported) and `SendsPayouts`, delegating to `PayCrossBorderPayoutService`. The service owns the shared flow (guard → balance → quote → payout → lookup) and asks a per-rail `ReceiverBuilder` (`CryptoReceiver`, `BankReceiver`, `WalletReceiver`) for the only part that differs: the `receiver` block. Beneficiary identity reaches the driver through a new rail-neutral `PayoutBeneficiary` on `PayoutRequest`, which `WithdrawalWorkflow` reads from `withdrawal_details['beneficiary']`.

**Tech Stack:** PHP 8.3, Laravel 11–13 (`Http`, `Cache`), bcmath, Pest on Orchestra Testbench.

**Spec:** `docs/superpowers/specs/2026-10-12-paycrossborder-payouts-design.md`

## Global Constraints

- Every PHP file starts with `<?php` + blank line + `declare(strict_types=1);`.
- Namespace root `Asciisd\CashierCore\`; driver code lives in `src/Drivers/PayCrossBorder/`, namespace `Asciisd\CashierCore\Drivers\PayCrossBorder`.
- HTTP goes through `Asciisd\CashierCore\Support\PspHttp`: `PspHttp::client()` for writes (never retried), `PspHttp::idempotent()` for reads.
- Exceptions: `PaymentProcessingException` = config/input error, nothing sent. `PayoutRejectedException` = PCB refused the payout, nothing created. `PayoutOutcomeUnknownException` = the payout may exist. Never use any other exception for these meanings.
- `payout()` is never retried automatically. PCB has no idempotency key.
- `lookup()` never returns `null`. PCB can only be searched by its own `transId`, so every failure or mismatch throws `PayoutOutcomeUnknownException`.
- Status map: `In Process`, `Payout Processing`, short code `IP` → `Sent`; `Payout Pass` → `Paid`; `Payout Fail`, `Reverse` → `Failed`; `Technical Failure` and anything else → `Unknown`.
- Definite rejection on the payout call = HTTP 400, 401, 403 or 422, or 2xx with `resultCode !== "0"` and no `transReference`.
- Names, account numbers, wallet addresses, phone numbers and ID numbers are never altered; a bad value throws naming the field. Only address lines, city, state and postal code are sanitised.
- Decimal strings are validated with `/^\d+(\.\d+)?$/` before any bcmath call; bcmath scale is 8.
- Withdrawals ship disabled: `withdrawals_enabled` defaults to `false`, and `withdrawal_max_amount` must be a positive decimal or every send throws.
- Pest datasets hold plain data only: no `Http::` calls and no closures. Datasets load before the app boots, and Pest resolves closures while binding them. Build stubs inside the test body.
- Run tests with `vendor/bin/pest <path>`. Run the whole suite with `vendor/bin/pest`.
- End every commit message with:
  ```
  Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
  ```

## File Structure

| File | Responsibility |
|---|---|
| `src/DataObjects/Concerns/ReadsFields.php` (new) | Strict typed readers for host-supplied arrays. |
| `src/DataObjects/PayoutBeneficiary.php` (new) | Rail-neutral beneficiary identity. |
| `src/DataObjects/BeneficiaryAddress.php` (new) | Beneficiary postal address. |
| `src/DataObjects/BeneficiaryBank.php` (new) | Beneficiary bank account. |
| `src/DataObjects/BeneficiaryWallet.php` (new) | Beneficiary mobile-wallet provider. |
| `src/DataObjects/PayoutRequest.php` (modify) | Adds `?PayoutBeneficiary $beneficiary = null`. |
| `src/Withdrawals/WithdrawalWorkflow.php` (modify) | Reads `details['beneficiary']` into the request. |
| `src/Drivers/PayCrossBorder/PayCrossBorderClient.php` (new) | HTTP, token, endpoints. No business rules. |
| `src/Drivers/PayCrossBorder/Rail.php` (new) | Rail enum: selection, `paymentMode`, builder. |
| `src/Drivers/PayCrossBorder/Receivers/ReceiverBuilder.php` (new) | Builder interface. |
| `src/Drivers/PayCrossBorder/Receivers/CustomerReceiver.php` (new) | Shared identity fields, sanitising, validation. |
| `src/Drivers/PayCrossBorder/Receivers/CryptoReceiver.php` (new) | Wallet address + network. |
| `src/Drivers/PayCrossBorder/Receivers/BankReceiver.php` (new) | Bank account fields + country rules. |
| `src/Drivers/PayCrossBorder/Receivers/WalletReceiver.php` (new) | Mobile-wallet provider fields. |
| `src/Drivers/PayCrossBorder/PayCrossBorderAdapter.php` (new) | Status map and enquiry-row → `PayoutReceipt`. |
| `src/Drivers/PayCrossBorder/PayCrossBorderPayoutService.php` (new) | `SendsPayouts` flow. |
| `src/Drivers/PayCrossBorder/PayCrossBorderProvider.php` (new) | Connection entry point. |
| `src/CashierCoreServiceProvider.php` (modify) | `BUNDLED_DRIVERS['paycrossborder']`. |
| `src/Cashier.php` (modify) | `fakeConnection()` defaults. |
| `config/cashier-core.php` (modify) | Connection example in the docblock. |
| `tests/Fixtures/PayCrossBorderApi.php` (new) | Scripted PCB API + request/beneficiary builders. |
| `README.md`, `CHANGELOG.md` (modify) | Driver list, release notes. |
| `.claude/skills/paycrossborder/SKILL.md` + `references/api.md` (new) | Driver knowledge for future sessions. |

---

### Task 1: Beneficiary data objects

**Files:**
- Create: `src/DataObjects/Concerns/ReadsFields.php`
- Create: `src/DataObjects/BeneficiaryAddress.php`
- Create: `src/DataObjects/BeneficiaryBank.php`
- Create: `src/DataObjects/BeneficiaryWallet.php`
- Create: `src/DataObjects/PayoutBeneficiary.php`
- Modify: `src/DataObjects/PayoutRequest.php`
- Test: `tests/Unit/DataObjects/PayoutBeneficiaryTest.php`

**Interfaces:**
- Produces:
  - `PayoutBeneficiary::fromArray(array $data): PayoutBeneficiary` (throws `InvalidArgumentException`), `->toArray(): array`.
  - Public readonly properties: `string $firstName, $lastName, $gender, $nationality`, `BeneficiaryAddress $address`, and `?string` `$middleName, $dateOfBirth, $phone, $dialCode, $email, $occupation, $taxId, $idType, $idNumber, $idCountry, $idIssueDate, $idExpiry`, plus `?BeneficiaryBank $bank`, `?BeneficiaryWallet $wallet`.
  - `BeneficiaryAddress`: `string $line1, $city, $state, $postalCode, $country`, `?string $line2`.
  - `BeneficiaryBank`: `string $accountNumber, $accountHolderName, $bankName`, `?string $bankCode, $swiftCode, $accountType, $bankCountry, $branchName`.
  - `BeneficiaryWallet`: `string $providerName, $providerCode`, `?string $providerMobile`.
  - `PayoutRequest` gains `public ?PayoutBeneficiary $beneficiary = null` as its last constructor parameter.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DataObjects/PayoutBeneficiaryTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\BeneficiaryBank;
use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;

function beneficiaryArray(array $overrides = []): array
{
    return array_replace_recursive([
        'firstName' => 'Kuldeep',
        'lastName' => 'Singh',
        'gender' => 'male',
        'nationality' => 'are',
        'dateOfBirth' => '1990-04-01',
        'phone' => 501234567,
        'address' => [
            'line1' => 'Villa 12',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'postalCode' => '00000',
            'country' => 'ARE',
        ],
        'bank' => [
            'accountNumber' => 'AE110260000959024298101',
            'accountHolderName' => 'Kuldeep Singh',
            'bankName' => 'Emirates NBD',
            'swiftCode' => 'EBILAEAD',
        ],
    ], $overrides);
}

it('reads a beneficiary, normalising countries and int phone numbers', function () {
    $beneficiary = PayoutBeneficiary::fromArray(beneficiaryArray());

    expect($beneficiary->firstName)->toBe('Kuldeep')
        ->and($beneficiary->nationality)->toBe('ARE')
        ->and($beneficiary->phone)->toBe('501234567')
        ->and($beneficiary->address->city)->toBe('Dubai')
        ->and($beneficiary->bank)->toBeInstanceOf(BeneficiaryBank::class)
        ->and($beneficiary->bank->swiftCode)->toBe('EBILAEAD')
        ->and($beneficiary->wallet)->toBeNull()
        ->and($beneficiary->middleName)->toBeNull();
});

it('round-trips through toArray without null keys', function () {
    $array = PayoutBeneficiary::fromArray(beneficiaryArray())->toArray();

    expect(PayoutBeneficiary::fromArray($array)->toArray())->toBe($array)
        ->and($array)->not->toHaveKey('middleName')
        ->and($array['bank'])->not->toHaveKey('bankCode')
        ->and($array)->not->toHaveKey('wallet');
});

it('treats blank strings as absent', function () {
    $beneficiary = PayoutBeneficiary::fromArray(beneficiaryArray(['middleName' => '  ', 'taxId' => '']));

    expect($beneficiary->middleName)->toBeNull()->and($beneficiary->taxId)->toBeNull();
});

it('refuses malformed input, naming the field', function (array $overrides, string $message) {
    expect(fn () => PayoutBeneficiary::fromArray(beneficiaryArray($overrides)))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing last name' => [['lastName' => null], 'lastName is required'],
    'non-string name' => [['firstName' => ['Kuldeep']], 'firstName must be a string'],
    'two-letter country' => [['nationality' => 'AE'], 'nationality must be a three-letter ISO country code'],
    'bad date' => [['dateOfBirth' => '01-04-1990'], 'dateOfBirth must be a Y-m-d date'],
    'impossible date' => [['dateOfBirth' => '1990-02-30'], 'dateOfBirth must be a Y-m-d date'],
    'address not an object' => [['address' => 'Dubai'], 'address must be an object'],
    'address city missing' => [['address' => ['city' => null]], 'address.city is required'],
    'bank account missing' => [['bank' => ['accountNumber' => null]], 'bank.accountNumber is required'],
    'wallet code missing' => [['wallet' => ['providerName' => 'GCASH']], 'wallet.providerCode is required'],
]);

it('refuses a beneficiary without an address', function () {
    $data = beneficiaryArray();
    unset($data['address']);

    expect(fn () => PayoutBeneficiary::fromArray($data))
        ->toThrow(InvalidArgumentException::class, 'address is required');
});

it('leaves existing PayoutRequest call sites without a beneficiary', function () {
    $request = new PayoutRequest('WD-1', '70001', '100.00', 'USD', 'usdttrc20', 'TXyz');

    expect($request->beneficiary)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/DataObjects/PayoutBeneficiaryTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\DataObjects\PayoutBeneficiary" not found`.

- [ ] **Step 3: Write the field reader trait**

Create `src/DataObjects/Concerns/ReadsFields.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects\Concerns;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Strict readers for host-supplied arrays. A wrong type is an error, never
 * coerced: these values are sent to a PSP, and a silently mangled field pays
 * the wrong person or fails compliance. `$path` prefixes the key in messages
 * (`address.` → "address.city is required").
 */
trait ReadsFields
{
    /** @param  array<string, mixed>  $data */
    private static function requiredString(array $data, string $key, string $path = ''): string
    {
        return self::optionalString($data, $key, $path)
            ?? throw new InvalidArgumentException("{$path}{$key} is required.");
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalString(array $data, string $key, string $path = ''): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        // Phone numbers and postal codes often arrive as ints from a form.
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("{$path}{$key} must be a string.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param  array<string, mixed>  $data */
    private static function requiredCountry(array $data, string $key, string $path = ''): string
    {
        return self::optionalCountry($data, $key, $path)
            ?? throw new InvalidArgumentException("{$path}{$key} is required.");
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalCountry(array $data, string $key, string $path = ''): ?string
    {
        $value = self::optionalString($data, $key, $path);

        if ($value === null) {
            return null;
        }

        $value = strtoupper($value);

        if (! preg_match('/^[A-Z]{3}$/', $value)) {
            throw new InvalidArgumentException("{$path}{$key} must be a three-letter ISO country code.");
        }

        return $value;
    }

    /** @param  array<string, mixed>  $data */
    private static function optionalDate(array $data, string $key, string $path = ''): ?string
    {
        $value = self::optionalString($data, $key, $path);

        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$path}{$key} must be a Y-m-d date.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function optionalArray(array $data, string $key, string $path = ''): ?array
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$path}{$key} must be an object.");
        }

        return $value;
    }
}
```

- [ ] **Step 4: Write the three nested objects**

Create `src/DataObjects/BeneficiaryAddress.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * A payout beneficiary's postal address. `country` is ISO 3166-1 alpha-3.
 */
readonly class BeneficiaryAddress
{
    use ReadsFields;

    public function __construct(
        public string $line1,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
        public ?string $line2 = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: self::requiredString($data, 'line1', 'address.'),
            city: self::requiredString($data, 'city', 'address.'),
            state: self::requiredString($data, 'state', 'address.'),
            postalCode: self::requiredString($data, 'postalCode', 'address.'),
            country: self::requiredCountry($data, 'country', 'address.'),
            line2: self::optionalString($data, 'line2', 'address.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
        ], fn ($value) => $value !== null);
    }
}
```

Create `src/DataObjects/BeneficiaryBank.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * The bank account a fiat payout is credited to. `bankCountry` is whatever
 * the target PSP calls the bank's location, which is not always a country
 * code, so it is not normalised.
 */
readonly class BeneficiaryBank
{
    use ReadsFields;

    public function __construct(
        public string $accountNumber,
        public string $accountHolderName,
        public string $bankName,
        public ?string $bankCode = null,
        public ?string $swiftCode = null,
        public ?string $accountType = null,
        public ?string $bankCountry = null,
        public ?string $branchName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accountNumber: self::requiredString($data, 'accountNumber', 'bank.'),
            accountHolderName: self::requiredString($data, 'accountHolderName', 'bank.'),
            bankName: self::requiredString($data, 'bankName', 'bank.'),
            bankCode: self::optionalString($data, 'bankCode', 'bank.'),
            swiftCode: self::optionalString($data, 'swiftCode', 'bank.'),
            accountType: self::optionalString($data, 'accountType', 'bank.'),
            bankCountry: self::optionalString($data, 'bankCountry', 'bank.'),
            branchName: self::optionalString($data, 'branchName', 'bank.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'accountNumber' => $this->accountNumber,
            'accountHolderName' => $this->accountHolderName,
            'bankName' => $this->bankName,
            'bankCode' => $this->bankCode,
            'swiftCode' => $this->swiftCode,
            'accountType' => $this->accountType,
            'bankCountry' => $this->bankCountry,
            'branchName' => $this->branchName,
        ], fn ($value) => $value !== null);
    }
}
```

Create `src/DataObjects/BeneficiaryWallet.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;

/**
 * The mobile-wallet provider a wallet payout is credited through. The
 * beneficiary's own wallet number is PayoutBeneficiary::$phone.
 */
readonly class BeneficiaryWallet
{
    use ReadsFields;

    public function __construct(
        public string $providerName,
        public string $providerCode,
        public ?string $providerMobile = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerName: self::requiredString($data, 'providerName', 'wallet.'),
            providerCode: self::requiredString($data, 'providerCode', 'wallet.'),
            providerMobile: self::optionalString($data, 'providerMobile', 'wallet.'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'providerName' => $this->providerName,
            'providerCode' => $this->providerCode,
            'providerMobile' => $this->providerMobile,
        ], fn ($value) => $value !== null);
    }
}
```

- [ ] **Step 5: Write `PayoutBeneficiary`**

Create `src/DataObjects/PayoutBeneficiary.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\DataObjects\Concerns\ReadsFields;
use InvalidArgumentException;

/**
 * Who receives a payout, for PSPs that need the beneficiary's identity
 * (PayCrossBorder). Rail-neutral: it carries no PSP rules, and code-valued
 * fields (`idType`, `occupation`) hold whatever code the target PSP expects.
 * Which fields are required is the driver's call, not this object's.
 *
 * Hosts store it as `withdrawal_details['beneficiary']`; the workflow reads it
 * with fromArray(). Countries are ISO 3166-1 alpha-3; dates are Y-m-d.
 */
readonly class PayoutBeneficiary
{
    use ReadsFields;

    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $nationality,
        public BeneficiaryAddress $address,
        public ?string $middleName = null,
        public ?string $dateOfBirth = null,
        public ?string $phone = null,
        public ?string $dialCode = null,
        public ?string $email = null,
        public ?string $occupation = null,
        public ?string $taxId = null,
        public ?string $idType = null,
        public ?string $idNumber = null,
        public ?string $idCountry = null,
        public ?string $idIssueDate = null,
        public ?string $idExpiry = null,
        public ?BeneficiaryBank $bank = null,
        public ?BeneficiaryWallet $wallet = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException naming the first bad field
     */
    public static function fromArray(array $data): self
    {
        $address = self::optionalArray($data, 'address')
            ?? throw new InvalidArgumentException('address is required.');
        $bank = self::optionalArray($data, 'bank');
        $wallet = self::optionalArray($data, 'wallet');

        return new self(
            firstName: self::requiredString($data, 'firstName'),
            lastName: self::requiredString($data, 'lastName'),
            gender: self::requiredString($data, 'gender'),
            nationality: self::requiredCountry($data, 'nationality'),
            address: BeneficiaryAddress::fromArray($address),
            middleName: self::optionalString($data, 'middleName'),
            dateOfBirth: self::optionalDate($data, 'dateOfBirth'),
            phone: self::optionalString($data, 'phone'),
            dialCode: self::optionalString($data, 'dialCode'),
            email: self::optionalString($data, 'email'),
            occupation: self::optionalString($data, 'occupation'),
            taxId: self::optionalString($data, 'taxId'),
            idType: self::optionalString($data, 'idType'),
            idNumber: self::optionalString($data, 'idNumber'),
            idCountry: self::optionalCountry($data, 'idCountry'),
            idIssueDate: self::optionalDate($data, 'idIssueDate'),
            idExpiry: self::optionalDate($data, 'idExpiry'),
            bank: $bank === null ? null : BeneficiaryBank::fromArray($bank),
            wallet: $wallet === null ? null : BeneficiaryWallet::fromArray($wallet),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'dateOfBirth' => $this->dateOfBirth,
            'phone' => $this->phone,
            'dialCode' => $this->dialCode,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'taxId' => $this->taxId,
            'idType' => $this->idType,
            'idNumber' => $this->idNumber,
            'idCountry' => $this->idCountry,
            'idIssueDate' => $this->idIssueDate,
            'idExpiry' => $this->idExpiry,
            'address' => $this->address->toArray(),
            'bank' => $this->bank?->toArray(),
            'wallet' => $this->wallet?->toArray(),
        ], fn ($value) => $value !== null);
    }
}
```

- [ ] **Step 6: Add the field to `PayoutRequest`**

In `src/DataObjects/PayoutRequest.php`, replace:

```php
        public ?string $customerEmail = null,
    ) {}
```

with:

```php
        public ?string $customerEmail = null,
        // Only drivers that need the beneficiary's identity read this.
        public ?PayoutBeneficiary $beneficiary = null,
    ) {}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/DataObjects/PayoutBeneficiaryTest.php`
Expected: PASS (6 tests, the dataset expands to 9 cases).

- [ ] **Step 8: Commit**

```bash
git add src/DataObjects tests/Unit/DataObjects/PayoutBeneficiaryTest.php
git commit -m "feat(payouts): rail-neutral PayoutBeneficiary on PayoutRequest

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Workflow reads the beneficiary

**Files:**
- Modify: `src/Withdrawals/WithdrawalWorkflow.php` (method `payoutRequest()`, around line 1072)
- Test: `tests/Feature/HeropaymentPayoutWorkflowTest.php` (append)

**Interfaces:**
- Consumes: `PayoutBeneficiary::fromArray()` (Task 1).
- Produces: every `PayoutRequest` the workflow builds carries `beneficiary` from `withdrawal_details['beneficiary']`, or `null` when the key is absent. A malformed beneficiary throws `PaymentProcessingException` whose message starts `This withdrawal's beneficiary is invalid:`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/HeropaymentPayoutWorkflowTest.php`:

```php
it('sends unchanged when the withdrawal also carries a beneficiary', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['withdrawal_details' => [
        'payout_address' => 'TXyzCustomer',
        'payout_currency' => 'usdttrc20',
        'beneficiary' => [
            'firstName' => 'Kuldeep',
            'lastName' => 'Singh',
            'gender' => 'male',
            'nationality' => 'ARE',
            'address' => ['line1' => 'Villa 12', 'city' => 'Dubai', 'state' => 'Dubai', 'postalCode' => '00000', 'country' => 'ARE'],
        ],
    ]]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && ! isset($request['beneficiary']));
});

it('fails cleanly and releases the claim when the beneficiary is malformed', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['withdrawal_details' => [
        'payout_address' => 'TXyzCustomer',
        'payout_currency' => 'usdttrc20',
        'beneficiary' => ['firstName' => 'Kuldeep'],
    ]]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain("beneficiary is invalid: address is required")
        ->and($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});
```

- [ ] **Step 2: Run tests to verify the second fails**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php --filter=beneficiary`
Expected: "sends unchanged…" PASSES already (the key is ignored today); "fails cleanly…" FAILS because `$result->ok` is `true`.

- [ ] **Step 3: Read the beneficiary in the workflow**

In `src/Withdrawals/WithdrawalWorkflow.php`, add the imports next to the existing `use Asciisd\CashierCore\DataObjects\PayoutRequest;` and among the global `use` lines:

```php
use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use InvalidArgumentException;
```

(Skip any `use` that is already present.)

In `payoutRequest()`, replace:

```php
            payoutExtraId: $extraId === '' ? null : $extraId,
            customerEmail: $email === '' ? null : $email,
        );
    }
```

with:

```php
            payoutExtraId: $extraId === '' ? null : $extraId,
            customerEmail: $email === '' ? null : $email,
            beneficiary: $this->payoutBeneficiary($details),
        );
    }

    /**
     * The host's `beneficiary` details, for drivers that need the payee's
     * identity. Absent is null — most drivers never read it. Malformed
     * refuses the send for every driver: the host meant to send one.
     *
     * @param  array<string, mixed>  $details
     *
     * @throws PaymentProcessingException
     */
    private function payoutBeneficiary(array $details): ?PayoutBeneficiary
    {
        $data = $details['beneficiary'] ?? null;

        if ($data === null) {
            return null;
        }

        if (! is_array($data)) {
            throw new PaymentProcessingException("This withdrawal's beneficiary is invalid: it must be an object.");
        }

        try {
            return PayoutBeneficiary::fromArray($data);
        } catch (InvalidArgumentException $e) {
            throw new PaymentProcessingException("This withdrawal's beneficiary is invalid: {$e->getMessage()}");
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php`
Expected: PASS (all existing tests plus the two new ones).

- [ ] **Step 5: Commit**

```bash
git add src/Withdrawals/WithdrawalWorkflow.php tests/Feature/HeropaymentPayoutWorkflowTest.php
git commit -m "feat(withdrawals): pass withdrawal_details.beneficiary to payout drivers

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Test fixture and HTTP client

**Files:**
- Create: `tests/Fixtures/PayCrossBorderApi.php`
- Create: `src/Drivers/PayCrossBorder/PayCrossBorderClient.php`
- Test: `tests/Unit/Drivers/PayCrossBorderClientTest.php`

**Interfaces:**
- Consumes: `PayoutBeneficiary`, `PayoutRequest` (Task 1).
- Produces:
  - `PayCrossBorderClient::fromConfig(array $config): self` (throws `PaymentProcessingException` on a bad `password_hash`).
  - `->token(): string`, `->forgetToken(): void`, `->tokenCacheKey(): string`.
  - `->quote(array $body): array`, `->transaction(string $transId): array`, `->balance(string $walletOwnerCode): array` — decoded body; throw `PaymentProcessingException` unless HTTP 2xx and `resultCode === "0"` (timeouts included).
  - `->payout(array $body): Illuminate\Http\Client\Response` — posts `{"payout": $body}`, never throws on status; a timeout throws `ConnectionException`.
  - `PayCrossBorderClient::describe(Response $response): string`.
  - Fixture `Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi`: `CONFIG`, `REFERENCE` (= derived reference of `WD-01TEST`), `configure(array $overrides = []): array`, `fake(array $options = []): void` with options `token|quote|payout|transaction|balance` (each an `Http::response()`, `Http::sequence()` or `Closure(Request)`), and builders `token()`, `quote(array $o = [])`, `payout(array $o = [])`, `transaction(array $o = [])`, `balance(mixed $value = 5000.0, float $min = 10.0)`, `beneficiary(array $o = [])`, `bankBeneficiary(array $o = [])`, `walletBeneficiary(array $o = [])`, `request(array $o = [], ?array $beneficiary = null): PayoutRequest`.

- [ ] **Step 1: Write the fixture**

Create `tests/Fixtures/PayCrossBorderApi.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A scripted PayCrossBorder API for payout tests, built from the examples in
 * docs.paycrossborder.com. Every endpoint answers from the options, so a test
 * states only what differs.
 *
 * Defaults: login succeeds with `pcb-token-1`; quotes echo the requested
 * payinAmount at rate 0.98; payouts are accepted as transReference 197930;
 * the enquiry reports 197930 as Payout Processing; the USD-USA Main Wallet
 * holds 5000 with a 10 minimum.
 */
final class PayCrossBorderApi
{
    /** PayCrossBorderPayoutService::reference('WD-01TEST'): too short once de-hyphenated, so hashed. */
    public const REFERENCE = 'PCB8FA8C449782C08525B16D9D630E';

    public const CONFIG = [
        'driver' => 'paycrossborder',
        'base_url' => 'https://pcb.test',
        'username' => '1000008340',
        'password' => 'HASHED-SECRET',
        'sender_code' => '1000008444',
        'customer_code' => '1000002225',
        'source_country' => 'ARE',
        'send_currency' => 'USD-USA',
        'currency_codes' => ['usd' => 'USD-USA', 'usdt' => 'USDT', 'aed' => 'AED', 'php' => 'PHP'],
        'charge_type' => 'OUR',
        'remittance_purpose' => 'PAYP002',
        'source_of_fund' => 'PAYS005',
        'relationship' => 'PAYR002',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'balance_buffer_percent' => '2',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function configure(array $overrides = []): array
    {
        $config = array_merge(self::CONFIG, $overrides);

        config()->set('cashier-core.connections.paycrossborder', $config);

        return $config;
    }

    /**
     * @param  array{token?: mixed, quote?: mixed, payout?: mixed, transaction?: mixed, balance?: mixed}  $options
     */
    public static function fake(array $options = []): void
    {
        Http::fake(function (Request $request) use ($options) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            $answer = match (true) {
                $path === '/ewallet/oauth/token' => $options['token'] ?? Http::response(self::token()),
                $path === '/ewallet/api/v1/fxratequotation/api' => $options['quote']
                    ?? Http::response(self::quote(['payinAmount' => (string) ($request['payinAmount'] ?? '')])),
                $path === '/ewallet/api/v1/payoutProcess/api' => $options['payout'] ?? Http::response(self::payout()),
                $path === '/ewallet/api/v1/transactionInfo/api' => $options['transaction'] ?? Http::response(self::transaction()),
                str_starts_with($path, '/ewallet/api/v1/wallet/walletOwner/') => $options['balance'] ?? Http::response(self::balance()),
                default => Http::response([], 404),
            };

            return is_callable($answer) ? $answer($request) : $answer;
        });
    }

    /** @return array<string, mixed> */
    public static function token(string $token = 'pcb-token-1', int $expiresIn = 21150): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $expiresIn,
            'scope' => 'read write trust',
            'clientCode' => '1000008483',
            'locale' => 'en',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into fxRateQuotation
     * @return array<string, mixed>
     */
    public static function quote(array $overrides = []): array
    {
        return [
            'transactionId' => '7558887',
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'fxRateQuotation' => array_merge([
                'code' => '114672',
                'fxRateValue' => '0.98',
                'senderMargin' => '0.98',
                'senderCode' => '1000008444',
                'receiverCode' => '1000008397',
                'payinAmount' => '100.00',
                'payoutAmount' => '98.00',
                'fee' => '2.0',
                'duration' => 5,
                'timeLeft' => 4,
                'paymentMode' => 'Cash',
                'chargeTypeCode' => 'OUR',
            ], $overrides),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into payoutResponseBean
     * @return array<string, mixed>
     */
    public static function payout(array $overrides = []): array
    {
        return [
            'requestTime' => 'Thu Aug 03 12:22:10 IST 2023',
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'payoutResponseBean' => array_merge([
                'transReference' => '197930',
                'clientReferenceNumber' => self::REFERENCE,
                'status' => 'In Process',
                'payinCurrency' => 'USD-USA',
                'payinAmount' => 100.0,
                'payoutCurrency' => 'USDT',
                'payoutAmount' => 98.0,
                'serviceType' => 'B2C',
            ], $overrides),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  merged into the single enquiry row
     * @return array<string, mixed>
     */
    public static function transaction(array $overrides = []): array
    {
        return [
            'resultCode' => '0',
            'resultDescription' => 'Transaction Successful',
            'pageable' => ['totalRecords' => 1],
            'transactionInfoResponse' => [array_merge([
                'id' => 3314,
                'transId' => 197930,
                'senderUniqueId' => self::REFERENCE,
                'sendingAmount' => 100.0,
                'payoutAmount' => 98.0,
                'types' => 'B2C',
                'resultCode' => '0',
                'resultDescription' => 'Transaction Successful',
                'vendorResultCode' => '200',
                'vendorResultDescription' => 'pending_approval',
                'transactionStatus' => 'IP',
                'statusName' => 'Payout Processing',
            ], $overrides)],
        ];
    }

    /** @return array<string, mixed> */
    public static function balance(mixed $value = 5000.0, float $min = 10.0): array
    {
        return [
            'transactionId' => '8474053',
            'resultCode' => '0',
            'resultDescription' => 'Transaction successful',
            'walletList' => [
                [
                    'walletOwnerCode' => '1000008444',
                    'currencyName' => 'USD-USA',
                    'walletTypeName' => 'Comission Wallet',
                    'value' => 0.0,
                    'minTransValue' => 0.0,
                ],
                [
                    'walletOwnerCode' => '1000008444',
                    'currencyName' => 'USD-USA',
                    'walletTypeName' => 'Main Wallet',
                    'value' => $value,
                    'minTransValue' => $min,
                    'maxTransValue' => 100000.0,
                ],
            ],
        ];
    }

    /**
     * A crypto-rail beneficiary (no bank, no wallet). Pass null to drop a key.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function beneficiary(array $overrides = []): array
    {
        return array_replace_recursive([
            'firstName' => 'Kuldeep',
            'lastName' => 'Singh',
            'gender' => 'male',
            'nationality' => 'ARE',
            'dateOfBirth' => '1990-04-01',
            'address' => [
                'line1' => 'Villa 12, Al Wasl Rd.',
                'city' => 'Dubai',
                'state' => 'Dubai',
                'postalCode' => '00000',
                'country' => 'ARE',
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function bankBeneficiary(array $overrides = []): array
    {
        return self::beneficiary(array_replace_recursive([
            'bank' => [
                'accountNumber' => 'AE110260000959024298101',
                'accountHolderName' => 'Kuldeep Singh',
                'bankName' => 'Emirates NBD',
                'swiftCode' => 'EBILAEAD',
            ],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function walletBeneficiary(array $overrides = []): array
    {
        return self::beneficiary(array_replace_recursive([
            'nationality' => 'PHL',
            'phone' => '9171234567',
            'dialCode' => '+63',
            'address' => ['city' => 'Manila', 'state' => 'Metro Manila', 'postalCode' => '1000', 'country' => 'PHL'],
            'wallet' => ['providerName' => 'GCASH-63001', 'providerCode' => '63001'],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides  PayoutRequest constructor arguments
     * @param  array<string, mixed>|null  $beneficiary  defaults to beneficiary()
     */
    public static function request(array $overrides = [], ?array $beneficiary = null): PayoutRequest
    {
        return new PayoutRequest(...array_merge([
            'externalOrderId' => 'WD-01TEST',
            'customerId' => '70001',
            'amount' => '100.00',
            'currency' => 'USD',
            'payoutCurrency' => 'usdt',
            'payoutAddress' => 'TXyzCustomerWalletAddress000001',
            'payoutExtraId' => 'TRC20',
            'beneficiary' => PayoutBeneficiary::fromArray($beneficiary ?? self::beneficiary()),
        ], $overrides));
    }
}
```

- [ ] **Step 2: Write the failing client tests**

Create `tests/Unit/Drivers/PayCrossBorderClientTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderClient;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Cache::flush());

function pcbClient(array $overrides = []): PayCrossBorderClient
{
    return PayCrossBorderClient::fromConfig(array_merge(PayCrossBorderApi::CONFIG, $overrides));
}

it('logs in with the password grant and PCB headers, then caches the token', function () {
    PayCrossBorderApi::fake();
    $client = pcbClient();

    expect($client->token())->toBe('pcb-token-1')
        ->and($client->token())->toBe('pcb-token-1');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pcb.test/ewallet/oauth/token'
        && $request->method() === 'POST'
        && $request->isForm()
        && $request['grant_type'] === 'password'
        && $request['scope'] === 'read write'
        && $request['username'] === '1000008340'
        && $request['password'] === 'HASHED-SECRET'
        && $request->header('channel') === ['WEB']
        && $request->header('source') === ['AGENT']
        && $request->header('Accept-Language') === ['en']
        && ! $request->hasHeader('Authorization'));
});

it('sends the uppercase md5 of the password when password_hash is md5', function () {
    PayCrossBorderApi::fake();

    pcbClient(['password' => 'secret', 'password_hash' => 'md5'])->token();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token')
        && $request['password'] === strtoupper(md5('secret')));
});

it('refuses an unknown password_hash', function () {
    expect(fn () => pcbClient(['password_hash' => 'sha1']))
        ->toThrow(PaymentProcessingException::class, 'password_hash');
});

it('adds basic client auth to the login when oauth_client_id is set', function () {
    PayCrossBorderApi::fake();

    pcbClient(['oauth_client_id' => 'cashier', 'oauth_client_secret' => 's3cret'])->token();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/oauth/token')
        && $request->header('Authorization') === ['Basic '.base64_encode('cashier:s3cret')]);
});

it('throws with the reason when login fails', function () {
    PayCrossBorderApi::fake(['token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad credentials'], 400)]);

    expect(fn () => pcbClient()->token())->toThrow(PaymentProcessingException::class, 'Bad credentials');
});

it('turns a login timeout into a PaymentProcessingException', function () {
    PayCrossBorderApi::fake(['token' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->token())->toThrow(PaymentProcessingException::class, 'cURL error 28');
});

it('expires the cached token a minute before PCB does', function () {
    PayCrossBorderApi::fake(['token' => Http::sequence()
        ->push(PayCrossBorderApi::token('first', 120))
        ->push(PayCrossBorderApi::token('second', 120))]);
    $client = pcbClient();

    expect($client->token())->toBe('first');

    $this->travel(61)->seconds();

    expect($client->token())->toBe('second');
});

it('sends the bearer token and PCB headers on API calls', function () {
    PayCrossBorderApi::fake();

    $quote = pcbClient()->quote(['payinAmount' => '100.00']);

    expect($quote['fxRateQuotation']['code'])->toBe('114672');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://pcb.test/ewallet/api/v1/fxratequotation/api'
        && $request->header('Authorization') === ['Bearer pcb-token-1']
        && $request->header('channel') === ['WEB']
        && $request['payinAmount'] === '100.00');
});

it('throws with PCB\'s description when resultCode is not 0', function () {
    PayCrossBorderApi::fake(['quote' => Http::response(['resultCode' => '1015', 'resultDescription' => 'Invalid currency'])]);

    expect(fn () => pcbClient()->quote([]))
        ->toThrow(PaymentProcessingException::class, 'resultCode 1015: Invalid currency');
});

it('forgets the token when a call answers 401', function () {
    PayCrossBorderApi::fake([
        'token' => Http::sequence()->push(PayCrossBorderApi::token('old'))->push(PayCrossBorderApi::token('new')),
        'quote' => Http::response(['error' => 'invalid_token'], 401),
    ]);
    $client = pcbClient();

    expect(fn () => $client->quote([]))->toThrow(PaymentProcessingException::class);
    expect($client->token())->toBe('new');
});

it('retries a read once with a fresh token after a 401', function () {
    PayCrossBorderApi::fake([
        'token' => Http::sequence()->push(PayCrossBorderApi::token('old'))->push(PayCrossBorderApi::token('new')),
        'transaction' => fn (Request $request) => $request->header('Authorization') === ['Bearer new']
            ? Http::response(PayCrossBorderApi::transaction())
            : Http::response(['error' => 'invalid_token'], 401),
    ]);

    $response = pcbClient()->transaction('197930');

    expect($response['transactionInfoResponse'][0]['transId'])->toBe(197930);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/ewallet/api/v1/transactionInfo/api?')
        && str_contains($request->url(), 'types=all')
        && str_contains($request->url(), 'status=all')
        && str_contains($request->url(), 'transId=197930'));
});

it('posts the payout once, wrapped, and returns the raw response', function () {
    PayCrossBorderApi::fake(['payout' => Http::response(['resultCode' => '1', 'resultDescription' => 'boom'], 500)]);

    $response = pcbClient()->payout(['transactionInfo' => ['type' => 'B2C']]);

    expect($response->status())->toBe(500);
    Http::assertSentCount(2); // login + exactly one payout
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/ewallet/api/v1/payoutProcess/api')
        && $request->data() === ['payout' => ['transactionInfo' => ['type' => 'B2C']]]);
});

it('lets a payout timeout escape as ConnectionException', function () {
    PayCrossBorderApi::fake(['payout' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->payout([]))->toThrow(ConnectionException::class);
});

it('reads the wallet list for the wallet owner', function () {
    PayCrossBorderApi::fake();

    $balance = pcbClient()->balance('1000008444');

    expect($balance['walletList'])->toHaveCount(2);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://pcb.test/ewallet/api/v1/wallet/walletOwner/1000008444');
});

it('turns a read timeout into a PaymentProcessingException', function () {
    PayCrossBorderApi::fake(['balance' => fn () => throw new ConnectionException('cURL error 28')]);

    expect(fn () => pcbClient()->balance('1000008444'))
        ->toThrow(PaymentProcessingException::class, 'cURL error 28');
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderClientTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderClient" not found`.

- [ ] **Step 4: Write the client**

Create `src/Drivers/PayCrossBorder/PayCrossBorderClient.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Support\PspHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * PayCrossBorder's HTTP surface: endpoints and auth, no business rules.
 *
 * Login is an OAuth2 password grant whose token is cached until a minute
 * before `expires_in`. Every call also carries the channel / source /
 * Accept-Language headers the docs mark mandatory. Success is HTTP 2xx AND
 * `resultCode === "0"` — PCB reports many failures inside a 200.
 *
 * payout() returns the raw response and is never retried: PCB has no
 * idempotency key, so a replayed POST could pay twice. The caller tells a
 * rejection from an unknown outcome.
 */
final class PayCrossBorderClient
{
    private const TOKEN_PATH = '/ewallet/oauth/token';

    private const API = '/ewallet/api/v1';

    private const HEADERS = [
        'channel' => 'WEB',
        'source' => 'AGENT',
        'Accept-Language' => 'en',
    ];

    /** Seconds shaved off `expires_in`, so a cached token never dies mid-call. */
    private const TOKEN_MARGIN = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $passwordHash = null,
        private readonly ?string $oauthClientId = null,
        private readonly ?string $oauthClientSecret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.*` array
     *
     * @throws PaymentProcessingException on an unsupported password_hash
     */
    public static function fromConfig(array $config): self
    {
        $hash = strtolower(trim((string) ($config['password_hash'] ?? '')));

        if (! in_array($hash, ['', 'md5'], true)) {
            throw new PaymentProcessingException("PayCrossBorder password_hash must be null or 'md5', not '{$hash}'.");
        }

        $clientId = trim((string) ($config['oauth_client_id'] ?? ''));

        return new self(
            baseUrl: rtrim((string) ($config['base_url'] ?? ''), '/'),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
            passwordHash: $hash === '' ? null : $hash,
            oauthClientId: $clientId === '' ? null : $clientId,
            oauthClientSecret: (string) ($config['oauth_client_secret'] ?? ''),
        );
    }

    /**
     * @throws PaymentProcessingException when login fails or times out
     */
    public function token(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $request = PspHttp::client()->asForm()->acceptJson()->withHeaders(self::HEADERS);

        if ($this->oauthClientId !== null) {
            $request = $request->withBasicAuth($this->oauthClientId, (string) $this->oauthClientSecret);
        }

        try {
            $response = $request->post($this->baseUrl.self::TOKEN_PATH, [
                'grant_type' => 'password',
                'scope' => 'read write',
                'username' => $this->username,
                'password' => $this->loginPassword(),
            ]);
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("Could not reach PayCrossBorder to log in: {$e->getMessage()}");
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            throw new PaymentProcessingException(sprintf(
                'Could not authenticate with PayCrossBorder: HTTP %d %s',
                $response->status(),
                self::describe($response),
            ));
        }

        $ttl = max(self::TOKEN_MARGIN, (int) $response->json('expires_in', 0) - self::TOKEN_MARGIN);

        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    public function tokenCacheKey(): string
    {
        return 'cashier:paycrossborder:token:'.md5($this->baseUrl.'|'.$this->username);
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function quote(array $body): array
    {
        try {
            $response = $this->writer()->post($this->baseUrl.self::API.'/fxratequotation/api', $body);
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("PayCrossBorder quote failed: {$e->getMessage()}");
        }

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        return $this->accepted('quote', $response);
    }

    /**
     * @param  array<string, mixed>  $body  the payout object, without the `payout` wrapper
     *
     * @throws ConnectionException when PCB did not answer — the payout may exist
     * @throws PaymentProcessingException when login fails (nothing was sent)
     */
    public function payout(array $body): Response
    {
        $response = $this->writer()->post($this->baseUrl.self::API.'/payoutProcess/api', ['payout' => $body]);

        if ($response->status() === 401) {
            $this->forgetToken();
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function transaction(string $transId): array
    {
        return $this->read('transaction enquiry', $this->baseUrl.self::API.'/transactionInfo/api?'.http_build_query([
            'types' => 'all',
            'status' => 'all',
            'transId' => $transId,
        ]));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentProcessingException
     */
    public function balance(string $walletOwnerCode): array
    {
        return $this->read('balance', $this->baseUrl.self::API.'/wallet/walletOwner/'.rawurlencode($walletOwnerCode));
    }

    /**
     * A short, log-safe description of a PCB answer: its resultCode and
     * description when it has them, else the start of the body.
     */
    public static function describe(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $code = $json['resultCode'] ?? null;
            $text = $json['resultDescription'] ?? $json['error_description'] ?? $json['message'] ?? $json['error'] ?? null;

            $parts = array_values(array_filter([
                is_scalar($code) && (string) $code !== '' ? 'resultCode '.$code : null,
                is_string($text) && $text !== '' ? $text : null,
            ]));

            if ($parts !== []) {
                return implode(': ', $parts);
            }
        }

        $body = trim($response->body());

        return $body === '' ? '(empty body)' : mb_substr($body, 0, 200);
    }

    /**
     * Read-only GET: retried once with a fresh token after a 401.
     *
     * @return array<string, mixed>
     */
    private function read(string $what, string $url): array
    {
        try {
            $response = $this->reader()->get($url);

            if ($response->status() === 401) {
                $this->forgetToken();
                $response = $this->reader()->get($url);
            }
        } catch (ConnectionException $e) {
            throw new PaymentProcessingException("PayCrossBorder {$what} failed: {$e->getMessage()}");
        }

        return $this->accepted($what, $response);
    }

    /**
     * @return array<string, mixed>
     */
    private function accepted(string $what, Response $response): array
    {
        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || (string) ($json['resultCode'] ?? '') !== '0') {
            throw new PaymentProcessingException(sprintf(
                'PayCrossBorder %s failed: HTTP %d %s',
                $what,
                $response->status(),
                self::describe($response),
            ));
        }

        return $json;
    }

    private function writer(): PendingRequest
    {
        return PspHttp::client()->timeout(30)->withToken($this->token())->acceptJson()->withHeaders(self::HEADERS);
    }

    private function reader(): PendingRequest
    {
        return PspHttp::idempotent()->timeout(30)->withToken($this->token())->acceptJson()->withHeaders(self::HEADERS);
    }

    /**
     * The docs show the login password as an uppercase hex digest. Until PCB
     * confirms whether it is issued that way or must be derived, both work.
     */
    private function loginPassword(): string
    {
        return $this->passwordHash === 'md5' ? strtoupper(md5($this->password)) : $this->password;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderClientTest.php`
Expected: PASS (16 tests). If "retries a read once…" is slow (≈0.5 s), that is `PspHttp::idempotent()` retrying the 401 twice before the fresh-token retry. It is expected.

- [ ] **Step 6: Commit**

```bash
git add tests/Fixtures/PayCrossBorderApi.php src/Drivers/PayCrossBorder/PayCrossBorderClient.php tests/Unit/Drivers/PayCrossBorderClientTest.php
git commit -m "feat(paycrossborder): HTTP client with cached password-grant token

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Rails and receiver builders

**Files:**
- Create: `src/Drivers/PayCrossBorder/Rail.php`
- Create: `src/Drivers/PayCrossBorder/Receivers/ReceiverBuilder.php`
- Create: `src/Drivers/PayCrossBorder/Receivers/CustomerReceiver.php`
- Create: `src/Drivers/PayCrossBorder/Receivers/CryptoReceiver.php`
- Create: `src/Drivers/PayCrossBorder/Receivers/BankReceiver.php`
- Create: `src/Drivers/PayCrossBorder/Receivers/WalletReceiver.php`
- Test: `tests/Unit/Drivers/PayCrossBorderReceiversTest.php`

**Interfaces:**
- Consumes: `PayoutRequest`, `PayoutBeneficiary` (Task 1); fixture builders (Task 3).
- Produces:
  - `enum Rail: string { Crypto = 'crypto'; Bank = 'bank'; Wallet = 'wallet' }` with `static for(PayoutBeneficiary): self` (throws `PaymentProcessingException` when both `bank` and `wallet` are set), `paymentMode(): string` (`'Bank'` | `'Cash'`), `builder(): ReceiverBuilder`.
  - `interface ReceiverBuilder { rail(): Rail; assertComplete(PayoutRequest): void; build(PayoutRequest): array }`. `build()` returns `['customer' => array<string, string>]` and calls `assertComplete()` first.
  - Messages start `PayCrossBorder beneficiary <field> …` (for example `PayCrossBorder beneficiary taxId is required for payouts to BRA.`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Drivers/PayCrossBorderReceiversTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\BankReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\CryptoReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\WalletReceiver;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;

it('picks the rail from the beneficiary', function () {
    expect(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::beneficiary())))->toBe(Rail::Crypto)
        ->and(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::bankBeneficiary())))->toBe(Rail::Bank)
        ->and(Rail::for(PayoutBeneficiary::fromArray(PayCrossBorderApi::walletBeneficiary())))->toBe(Rail::Wallet)
        ->and(Rail::Bank->paymentMode())->toBe('Bank')
        ->and(Rail::Crypto->paymentMode())->toBe('Cash')
        ->and(Rail::Wallet->paymentMode())->toBe('Cash')
        ->and(Rail::Bank->builder())->toBeInstanceOf(BankReceiver::class);
});

it('refuses a beneficiary with both bank and wallet details', function () {
    $both = PayCrossBorderApi::bankBeneficiary(['wallet' => ['providerName' => 'GCASH', 'providerCode' => '63001']]);

    expect(fn () => Rail::for(PayoutBeneficiary::fromArray($both)))
        ->toThrow(PaymentProcessingException::class, 'not both');
});

it('builds the crypto receiver', function () {
    expect((new CryptoReceiver)->build(PayCrossBorderApi::request()))->toEqual(['customer' => [
        'receiverFirstName' => 'Kuldeep',
        'receiverLastName' => 'Singh',
        'receiverGender' => 'male',
        'receiverNationality' => 'ARE',
        'receiverDOB' => '1990-04-01',
        'receiverAddressLineOne' => 'Villa 12 Al Wasl Rd',
        'receiverCountry' => 'ARE',
        'receiverAddressState' => 'Dubai',
        'receiverAddresssCity' => 'Dubai',
        'receiverPinCode' => '00000',
        'walletAddress' => 'TXyzCustomerWalletAddress000001',
        'cryptoNetwork' => 'TRC20',
    ]]);
});

it('builds the bank receiver', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::bankBeneficiary(['taxId' => 'TX123']));

    $customer = (new BankReceiver)->build($request)['customer'];

    expect($customer)->toMatchArray([
        'receiverFirstName' => 'Kuldeep',
        'receiverAccountNumber' => 'AE110260000959024298101',
        'receiverAccountHolderName' => 'Kuldeep Singh',
        'receiverBankName' => 'Emirates NBD',
        'receiverSwiftCode' => 'EBILAEAD',
        'taxIdNumber' => 'TX123',
    ])->and($customer)->not->toHaveKeys(['walletAddress', 'cryptoNetwork', 'receiverBankCode']);
});

it('builds the wallet receiver', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::walletBeneficiary());

    expect((new WalletReceiver)->build($request)['customer'])->toMatchArray([
        'receiverMsisdn' => '9171234567',
        'dialCode' => '+63',
        'receiverCountry' => 'PHL',
        'receiverServiceProviderName' => 'GCASH-63001',
        'receiverServiceProviderCode' => '63001',
    ]);
});

it('transliterates, strips and truncates address fields only', function () {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::beneficiary(['address' => [
        'line1' => 'Straße 5, Düsseldorf-Nord #12 and a very long tail here',
        'city' => 'Düsseldorf',
        'postalCode' => 'SW1A 1AA',
    ]]));

    $customer = (new CryptoReceiver)->build($request)['customer'];

    expect($customer['receiverAddressLineOne'])->toBe('Strasse 5 Dusseldorf Nord 12 and a')
        ->and($customer['receiverAddresssCity'])->toBe('Dusseldorf')
        ->and($customer['receiverPinCode'])->toBe('SW1A1AA');
});

it('refuses rather than alters protected or incomplete fields', function (array $beneficiary, array $request, string $message) {
    $payout = PayCrossBorderApi::request($request, PayCrossBorderApi::beneficiary($beneficiary));

    expect(fn () => (new CryptoReceiver)->assertComplete($payout))
        ->toThrow(PaymentProcessingException::class, $message);
})->with([
    'digit in a name' => [['firstName' => 'Kuldeep2'], [], 'firstName'],
    'unknown gender' => [['gender' => 'm'], [], 'gender must be male, female or others'],
    'phone with symbols' => [['phone' => '+971-50'], [], 'phone must be 7-20 digits'],
    'bad dial code' => [['dialCode' => '971'], [], 'dialCode'],
    'id number with symbols' => [['idNumber' => 'A-123'], [], 'idNumber'],
    'city with nothing usable' => [['address' => ['city' => '###']], [], 'address.city'],
    'crypto without birth date' => [['dateOfBirth' => null], [], 'dateOfBirth is required for crypto payouts'],
    'crypto without network' => [[], ['payoutExtraId' => null], 'payout_extra_id'],
    'crypto short address' => [[], ['payoutAddress' => 'TXyz'], 'payout_address'],
]);

it('refuses a payout with no beneficiary', function () {
    expect(fn () => (new CryptoReceiver)->assertComplete(PayCrossBorderApi::request(['beneficiary' => null])))
        ->toThrow(PaymentProcessingException::class, 'need a beneficiary');
});

it('applies the documented per-country bank rules', function (array $overrides, string $message) {
    $request = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::bankBeneficiary($overrides));

    expect(fn () => (new BankReceiver)->assertComplete($request))
        ->toThrow(PaymentProcessingException::class, $message);
})->with([
    'tax id for BRA' => [['address' => ['country' => 'BRA'], 'bank' => ['accountType' => 'saving'], 'phone' => '11987654321'], 'taxId is required for payouts to BRA'],
    'account type for DOM' => [['address' => ['country' => 'DOM']], 'bank.accountType is required for payouts to DOM'],
    'phone for SWIFT to MEX' => [['address' => ['country' => 'MEX'], 'taxId' => 'TX1'], 'phone is required for SWIFT payouts to MEX'],
    'account number with symbols' => [['bank' => ['accountNumber' => 'AE11-0260']], 'bank.accountNumber'],
    'bad swift' => [['bank' => ['swiftCode' => 'EBIL']], 'bank.swiftCode'],
]);

it('requires bank details on the bank rail and a phone on the wallet rail', function () {
    expect(fn () => (new BankReceiver)->assertComplete(PayCrossBorderApi::request()))
        ->toThrow(PaymentProcessingException::class, 'bank is required');

    $noPhone = PayCrossBorderApi::request(beneficiary: PayCrossBorderApi::walletBeneficiary(['phone' => null]));

    expect(fn () => (new WalletReceiver)->assertComplete($noPhone))
        ->toThrow(PaymentProcessingException::class, 'phone is required for wallet payouts');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderReceiversTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\Drivers\PayCrossBorder\Rail" not found`.

- [ ] **Step 3: Write the interface and the rail enum**

Create `src/Drivers/PayCrossBorder/Receivers/ReceiverBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * Builds the `receiver` block of a PayCrossBorder payout — the only part of
 * the payout body that differs between the crypto, bank and wallet rails.
 */
interface ReceiverBuilder
{
    public function rail(): Rail;

    /**
     * @throws PaymentProcessingException naming the first missing or invalid field
     */
    public function assertComplete(PayoutRequest $request): void;

    /**
     * @return array{customer: array<string, string>}
     *
     * @throws PaymentProcessingException
     */
    public function build(PayoutRequest $request): array;
}
```

Create `src/Drivers/PayCrossBorder/Rail.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\BankReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\CryptoReceiver;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\ReceiverBuilder;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers\WalletReceiver;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * The PayCrossBorder payout rail, chosen from what the beneficiary carries:
 * bank details → Bank, a wallet provider → Wallet (PCB's "WPT"), neither →
 * Crypto (wallet address and network from the request).
 */
enum Rail: string
{
    case Crypto = 'crypto';
    case Bank = 'bank';
    case Wallet = 'wallet';

    /**
     * @throws PaymentProcessingException when the beneficiary has both bank and wallet details
     */
    public static function for(PayoutBeneficiary $beneficiary): self
    {
        if ($beneficiary->bank !== null && $beneficiary->wallet !== null) {
            throw new PaymentProcessingException(
                'A PayCrossBorder beneficiary has either bank or wallet details, not both.',
            );
        }

        return match (true) {
            $beneficiary->bank !== null => self::Bank,
            $beneficiary->wallet !== null => self::Wallet,
            default => self::Crypto,
        };
    }

    /**
     * PCB's `paymentMode`. Its crypto and WPT examples both use Cash.
     */
    public function paymentMode(): string
    {
        return $this === self::Bank ? 'Bank' : 'Cash';
    }

    public function builder(): ReceiverBuilder
    {
        return match ($this) {
            self::Crypto => new CryptoReceiver,
            self::Bank => new BankReceiver,
            self::Wallet => new WalletReceiver,
        };
    }
}
```

- [ ] **Step 4: Write the shared base**

Create `src/Drivers/PayCrossBorder/Receivers/CustomerReceiver.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Illuminate\Support\Str;

/**
 * The `receiver.customer` block every rail shares (B2C: the beneficiary is a
 * person). Field names are PCB's, including `receiverAddresssCity` with three
 * s's.
 *
 * Only free-text address fields are cleaned — transliterated to ASCII,
 * stripped to letters, digits and spaces ("special characters not allowed")
 * and cut to PCB's documented length. Names, phone numbers and ID numbers
 * are never altered: a value PCB would refuse is refused here, naming the
 * field, so the host fixes it rather than paying a mangled name.
 */
abstract class CustomerReceiver implements ReceiverBuilder
{
    private const GENDERS = ['male', 'female', 'others'];

    /** Letters in any script, plus the punctuation real names carry. */
    private const NAME_PATTERN = "/^\p{L}[\p{L} .'-]*$/u";

    public function assertComplete(PayoutRequest $request): void
    {
        $beneficiary = $this->beneficiary($request);

        $this->assertName($beneficiary->firstName, 'firstName');
        $this->assertName($beneficiary->lastName, 'lastName');

        if ($beneficiary->middleName !== null) {
            $this->assertName($beneficiary->middleName, 'middleName');
        }

        if (! in_array(strtolower($beneficiary->gender), self::GENDERS, true)) {
            throw self::invalid('gender', 'must be male, female or others');
        }

        if ($beneficiary->phone !== null && ! preg_match('/^\d{7,20}$/', $beneficiary->phone)) {
            throw self::invalid('phone', 'must be 7-20 digits, without the dial code');
        }

        if ($beneficiary->dialCode !== null && ! preg_match('/^\+\d{1,3}$/', $beneficiary->dialCode)) {
            throw self::invalid('dialCode', 'must look like +971');
        }

        if ($beneficiary->idNumber !== null && ! preg_match('/^[A-Za-z0-9]{1,50}$/', $beneficiary->idNumber)) {
            throw self::invalid('idNumber', 'must be 1-50 letters or digits');
        }

        $address = $beneficiary->address;

        foreach (['line1' => $address->line1, 'city' => $address->city, 'state' => $address->state, 'postalCode' => $address->postalCode] as $field => $value) {
            if (self::clean($value, 50) === '') {
                throw self::invalid("address.{$field}", 'has no letters or digits PayCrossBorder accepts');
            }
        }

        $this->assertRail($request, $beneficiary);
    }

    public function build(PayoutRequest $request): array
    {
        $this->assertComplete($request);

        $beneficiary = $this->beneficiary($request);
        $address = $beneficiary->address;
        $line2 = $address->line2 === null ? '' : self::clean($address->line2, 35);

        return ['customer' => self::compact(array_merge([
            'receiverFirstName' => $beneficiary->firstName,
            'receiverMiddleName' => $beneficiary->middleName,
            'receiverLastName' => $beneficiary->lastName,
            'receiverGender' => strtolower($beneficiary->gender),
            'receiverNationality' => $beneficiary->nationality,
            'receiverDOB' => $beneficiary->dateOfBirth,
            'receiverIdType' => $beneficiary->idType,
            'receiverIdNumber' => $beneficiary->idNumber,
            'receiverIssueDate' => $beneficiary->idIssueDate,
            'receiverIdExpiration' => $beneficiary->idExpiry,
            'receiverIdCountry' => $beneficiary->idCountry,
            'receiverOccupation' => $beneficiary->occupation,
            'dialCode' => $beneficiary->dialCode,
            'receiverMsisdn' => $beneficiary->phone,
            'receiverAddressLineOne' => self::clean($address->line1, 35),
            'receiverAddressLineTwo' => $line2 === '' ? null : $line2,
            'receiverCountry' => $address->country,
            'receiverAddressState' => self::clean($address->state, 50),
            'receiverAddresssCity' => self::clean($address->city, 50),
            'receiverPinCode' => mb_substr(str_replace(' ', '', self::clean($address->postalCode, 50)), 0, 10),
        ], $this->railFields($request, $beneficiary)))];
    }

    /**
     * @throws PaymentProcessingException
     */
    abstract protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void;

    /**
     * @return array<string, string|null>
     */
    abstract protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array;

    protected function beneficiary(PayoutRequest $request): PayoutBeneficiary
    {
        return $request->beneficiary ?? throw new PaymentProcessingException(
            'PayCrossBorder payouts need a beneficiary in the withdrawal details.',
        );
    }

    protected static function invalid(string $field, string $reason): PaymentProcessingException
    {
        return new PaymentProcessingException("PayCrossBorder beneficiary {$field} {$reason}.");
    }

    private function assertName(string $name, string $field): void
    {
        if (mb_strlen($name) > 75 || ! preg_match(self::NAME_PATTERN, $name)) {
            throw self::invalid($field, 'must be letters only (spaces, dots, apostrophes and hyphens allowed), at most 75');
        }
    }

    /**
     * ASCII letters, digits and single spaces, at most $max characters.
     */
    private static function clean(string $value, int $max): string
    {
        $value = preg_replace('/[^A-Za-z0-9 ]+/', ' ', Str::ascii($value)) ?? '';
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return rtrim(mb_substr($value, 0, $max));
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, string>
     */
    private static function compact(array $fields): array
    {
        return array_filter($fields, fn ($value) => $value !== null);
    }
}
```

- [ ] **Step 5: Write the three rails**

Create `src/Drivers/PayCrossBorder/Receivers/CryptoReceiver.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-CRYPTO.md: stablecoin to a wallet. The address is the request's
 * payout_address, the network (ERC20, TRC20 …) its payout_extra_id.
 * receiverDOB is mandatory on this rail only.
 */
final class CryptoReceiver extends CustomerReceiver
{
    public function rail(): Rail
    {
        return Rail::Crypto;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        if ($beneficiary->dateOfBirth === null) {
            throw self::invalid('dateOfBirth', 'is required for crypto payouts');
        }

        if (! preg_match('/^[A-Za-z0-9]{26,50}$/', trim($request->payoutAddress))) {
            throw self::invalid('payout_address', 'must be a 26-50 character wallet address');
        }

        if (! preg_match('/^[A-Za-z0-9]{3,10}$/', trim((string) $request->payoutExtraId))) {
            throw self::invalid('payout_extra_id', 'must name the crypto network, e.g. TRC20');
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        return [
            'walletAddress' => trim($request->payoutAddress),
            'cryptoNetwork' => strtoupper(trim((string) $request->payoutExtraId)),
        ];
    }
}
```

Create `src/Drivers/PayCrossBorder/Receivers/BankReceiver.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-FIAT.md: credit a bank account. The destination country is the
 * beneficiary's address country.
 *
 * Only the rules the payout page states inline are encoded below. The
 * per-country "Local RAIL" tables (COUNTRYVALIDATIONS.md) are not: PCB
 * refuses such a payout itself, which arrives as a clean rejection.
 */
final class BankReceiver extends CustomerReceiver
{
    /** PAYOUT-FIAT.md, receiver `taxIdNumber`. */
    public const TAX_ID_COUNTRIES = ['ARG', 'THA', 'BRA', 'ECU', 'GTM', 'HND', 'KAZ', 'MEX', 'PER', 'CHL', 'PHL', 'JPN', 'URY', 'COL', 'CRI', 'RUS', 'BLR', 'PAK'];

    /** PAYOUT-FIAT.md, receiver `receiverAccountType`. */
    public const ACCOUNT_TYPE_COUNTRIES = ['BRA', 'PHL', 'DOM', 'URY'];

    /** PAYOUT-FIAT.md, receiver `receiverMsisdn` on the SWIFT rail. */
    public const SWIFT_PHONE_COUNTRIES = ['BRA', 'PHL', 'CHN', 'UGA', 'URY', 'COL', 'MEX', 'HKG', 'MYS'];

    public function rail(): Rail
    {
        return Rail::Bank;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        $bank = $beneficiary->bank ?? throw self::invalid('bank', 'is required for bank payouts');
        $country = $beneficiary->address->country;

        if (! preg_match('/^[A-Za-z0-9]{1,50}$/', $bank->accountNumber)) {
            throw self::invalid('bank.accountNumber', 'must be 1-50 letters or digits, without spaces');
        }

        if (mb_strlen($bank->accountHolderName) > 150) {
            throw self::invalid('bank.accountHolderName', 'must be at most 150 characters');
        }

        if (mb_strlen($bank->bankName) > 100) {
            throw self::invalid('bank.bankName', 'must be at most 100 characters');
        }

        if ($bank->swiftCode !== null && ! preg_match('/^[A-Za-z0-9]{8}([A-Za-z0-9]{3})?$/', $bank->swiftCode)) {
            throw self::invalid('bank.swiftCode', 'must be 8 or 11 letters or digits');
        }

        if (in_array($country, self::TAX_ID_COUNTRIES, true) && $beneficiary->taxId === null) {
            throw self::invalid('taxId', "is required for payouts to {$country}");
        }

        if (in_array($country, self::ACCOUNT_TYPE_COUNTRIES, true) && $bank->accountType === null) {
            throw self::invalid('bank.accountType', "is required for payouts to {$country}");
        }

        if ($bank->swiftCode !== null && in_array($country, self::SWIFT_PHONE_COUNTRIES, true) && $beneficiary->phone === null) {
            throw self::invalid('phone', "is required for SWIFT payouts to {$country}");
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        $bank = $beneficiary->bank;

        return [
            'receiverAccountNumber' => $bank?->accountNumber,
            'receiverAccountHolderName' => $bank?->accountHolderName,
            'receiverBankName' => $bank?->bankName,
            'receiverBankCode' => $bank?->bankCode,
            'receiverSwiftCode' => $bank?->swiftCode === null ? null : strtoupper($bank->swiftCode),
            'receiverAccountType' => $bank?->accountType,
            'receiverBankCountry' => $bank?->bankCountry,
            'receiverBankBranchName' => $bank?->branchName,
            'taxIdNumber' => $beneficiary->taxId,
        ];
    }
}
```

Create `src/Drivers/PayCrossBorder/Receivers/WalletReceiver.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder\Receivers;

use Asciisd\CashierCore\DataObjects\PayoutBeneficiary;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\PayCrossBorder\Rail;

/**
 * PAYOUT-WPT.md: credit a mobile wallet. The beneficiary's phone is the
 * wallet number; the provider comes from the beneficiary's wallet details.
 */
final class WalletReceiver extends CustomerReceiver
{
    public function rail(): Rail
    {
        return Rail::Wallet;
    }

    protected function assertRail(PayoutRequest $request, PayoutBeneficiary $beneficiary): void
    {
        if ($beneficiary->wallet === null) {
            throw self::invalid('wallet', 'is required for wallet payouts');
        }

        if ($beneficiary->phone === null) {
            throw self::invalid('phone', 'is required for wallet payouts');
        }
    }

    protected function railFields(PayoutRequest $request, PayoutBeneficiary $beneficiary): array
    {
        return [
            'receiverServiceProviderName' => $beneficiary->wallet?->providerName,
            'receiverServiceProviderCode' => $beneficiary->wallet?->providerCode,
            'receiverServiceProviderMobile' => $beneficiary->wallet?->providerMobile,
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderReceiversTest.php`
Expected: PASS. If the truncation assertion fails, check the arithmetic: `Strasse 5 Dusseldorf Nord 12 and a very…` cut at 35 characters is `Strasse 5 Dusseldorf Nord 12 and a ` and `rtrim` leaves `Strasse 5 Dusseldorf Nord 12 and a`.

- [ ] **Step 7: Commit**

```bash
git add src/Drivers/PayCrossBorder/Rail.php src/Drivers/PayCrossBorder/Receivers tests/Unit/Drivers/PayCrossBorderReceiversTest.php
git commit -m "feat(paycrossborder): per-rail receiver builders

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Status adapter

**Files:**
- Create: `src/Drivers/PayCrossBorder/PayCrossBorderAdapter.php`
- Test: `tests/Unit/Drivers/PayCrossBorderAdapterTest.php`

**Interfaces:**
- Produces: `PayCrossBorderAdapter::mapPayoutStatus(?string $statusName, ?string $shortCode = null): PayoutState` and `->payoutReceipt(array $row): PayoutReceipt` (reference = `(string) $row['transId']`; `error` set only when Failed).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/PayCrossBorderAdapterTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderAdapter;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;

it('maps PCB statuses to payout states', function (?string $name, ?string $code, PayoutState $state) {
    expect((new PayCrossBorderAdapter)->mapPayoutStatus($name, $code))->toBe($state);
})->with([
    'in process' => ['In Process', null, PayoutState::Sent],
    'payout processing' => ['Payout Processing', 'IP', PayoutState::Sent],
    'case and spacing' => ['  payout PROCESSING ', null, PayoutState::Sent],
    'pass' => ['Payout Pass', null, PayoutState::Paid],
    'fail' => ['Payout Fail', null, PayoutState::Failed],
    'reverse' => ['Reverse', null, PayoutState::Failed],
    // The docs do not say whether money moved; Failed would allow a refund or resend.
    'technical failure' => ['Technical Failure', null, PayoutState::Unknown],
    'unrecognised' => ['Screening Hold', null, PayoutState::Unknown],
    'short code only' => [null, 'IP', PayoutState::Sent],
    'unknown short code only' => ['', 'PP', PayoutState::Unknown],
    'nothing' => [null, null, PayoutState::Unknown],
]);

it('builds a receipt from an enquiry row', function () {
    $row = PayCrossBorderApi::transaction()['transactionInfoResponse'][0];

    $receipt = (new PayCrossBorderAdapter)->payoutReceipt($row);

    expect($receipt->reference)->toBe('197930')
        ->and($receipt->rawStatus)->toBe('Payout Processing')
        ->and($receipt->state)->toBe(PayoutState::Sent)
        ->and($receipt->payload)->toBe($row)
        ->and($receipt->error)->toBeNull();
});

it('carries PCB\'s reason on a failed row', function () {
    $row = PayCrossBorderApi::transaction([
        'statusName' => 'Payout Fail',
        'transactionStatus' => 'PF',
        'vendorResultDescription' => 'Beneficiary account closed',
    ])['transactionInfoResponse'][0];

    $receipt = (new PayCrossBorderAdapter)->payoutReceipt($row);

    expect($receipt->state)->toBe(PayoutState::Failed)
        ->and($receipt->error)->toBe('PayCrossBorder Payout Fail: Beneficiary account closed');
});

it('falls back to the short code for the raw status', function () {
    $row = PayCrossBorderApi::transaction(['statusName' => null])['transactionInfoResponse'][0];

    expect((new PayCrossBorderAdapter)->payoutReceipt($row)->rawStatus)->toBe('IP');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderAdapterTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderAdapter" not found`.

- [ ] **Step 3: Write the adapter**

Create `src/Drivers/PayCrossBorder/PayCrossBorderAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\Enums\PayoutState;

/**
 * PayCrossBorder payout statuses (responseCodes.md) → PayoutState.
 *
 * `statusName` is the signal; the short `transactionStatus` code is read only
 * when the name is missing, and only `IP` is documented. "Technical Failure"
 * is deliberately Unknown: the docs do not say whether funds left, and Failed
 * releases the withdrawal for a refund or a resend.
 */
final class PayCrossBorderAdapter
{
    private const SENT = ['in process', 'payout processing'];

    private const SENT_CODES = ['IP'];

    private const PAID = ['payout pass'];

    private const FAILED = ['payout fail', 'reverse'];

    public function mapPayoutStatus(?string $statusName, ?string $shortCode = null): PayoutState
    {
        $name = strtolower(trim((string) $statusName));

        if ($name === '') {
            return in_array(strtoupper(trim((string) $shortCode)), self::SENT_CODES, true)
                ? PayoutState::Sent
                : PayoutState::Unknown;
        }

        return match (true) {
            in_array($name, self::SENT, true) => PayoutState::Sent,
            in_array($name, self::PAID, true) => PayoutState::Paid,
            in_array($name, self::FAILED, true) => PayoutState::Failed,
            default => PayoutState::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $row  one `transactionInfoResponse` row
     */
    public function payoutReceipt(array $row): PayoutReceipt
    {
        $name = trim((string) ($row['statusName'] ?? ''));
        $code = trim((string) ($row['transactionStatus'] ?? ''));
        $state = $this->mapPayoutStatus($name, $code);

        $error = null;

        if ($state === PayoutState::Failed) {
            $detail = trim((string) ($row['vendorResultDescription'] ?? $row['resultDescription'] ?? ''));
            $error = "PayCrossBorder {$name}".($detail === '' ? '' : ": {$detail}");
        }

        return new PayoutReceipt(
            reference: (string) ($row['transId'] ?? ''),
            rawStatus: $name !== '' ? $name : $code,
            state: $state,
            payload: $row,
            error: $error,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderAdapterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/PayCrossBorder/PayCrossBorderAdapter.php tests/Unit/Drivers/PayCrossBorderAdapterTest.php
git commit -m "feat(paycrossborder): payout status adapter

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Payout service

**Files:**
- Create: `src/Drivers/PayCrossBorder/PayCrossBorderPayoutService.php`
- Test: `tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php`

**Interfaces:**
- Consumes: `PayCrossBorderClient` (Task 3), `Rail` + builders (Task 4), `PayCrossBorderAdapter` (Task 5).
- Produces: `final class PayCrossBorderPayoutService implements SendsPayouts` with `__construct(array $config, ?PayCrossBorderClient $client = null)`, the four `SendsPayouts` methods, and `public static function reference(string $externalOrderId): string`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderPayoutService;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-10-12 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function pcbService(array $overrides = []): PayCrossBorderPayoutService
{
    return new PayCrossBorderPayoutService(array_merge(PayCrossBorderApi::CONFIG, $overrides));
}

/** The most recent payout POST, or null when none was sent. */
function pcbPayoutSent(): ?Request
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api'))
        ->last();
}

/**
 * Datasets load before the app boots, so they hold plain data and the test
 * turns it into a stub: 'timeout', or ['json' => …, 'body' => …, 'status' => …].
 */
function pcbStub(array|string $spec): mixed
{
    if ($spec === 'timeout') {
        return fn () => throw new ConnectionException('cURL error 28');
    }

    return Http::response($spec['body'] ?? $spec['json'] ?? [], $spec['status'] ?? 200);
}

// --- references ---------------------------------------------------------

it('derives readable references from workflow order ids and hashes the rest', function () {
    expect(PayCrossBorderPayoutService::reference('WD-01M4PQ4K3S3PAPTFM7J6FHJ60E'))->toBe('WD01M4PQ4K3S3PAPTFM7J6FHJ60E')
        ->and(PayCrossBorderPayoutService::reference('WD-01M4PQ4K3S3PAPTFM7J6FHJ60E-2'))->toBe('WD01M4PQ4K3S3PAPTFM7J6FHJ60E2')
        ->and(PayCrossBorderPayoutService::reference('WD-01TEST'))->toBe(PayCrossBorderApi::REFERENCE)
        ->and(PayCrossBorderPayoutService::reference('wd 1/2'))->toMatch('/^PCB[0-9A-F]{27}$/')
        ->and(PayCrossBorderPayoutService::reference('WD-A'))->not->toBe(PayCrossBorderPayoutService::reference('WD-B'));
});

// --- guard (shared by preflight and send) --------------------------------

it('refuses on configuration or input before calling PCB', function (array $config, array $request, ?array $beneficiary, string $message) {
    Http::fake();

    expect(fn () => pcbService($config)->preflight(PayCrossBorderApi::request($request, $beneficiary)))
        ->toThrow(PaymentProcessingException::class, $message);
    expect(fn () => pcbService($config)->send(PayCrossBorderApi::request($request, $beneficiary)))
        ->toThrow(PaymentProcessingException::class, $message);

    Http::assertNothingSent();
})->with([
    'disabled' => [['withdrawals_enabled' => false], [], null, 'withdrawals are disabled'],
    'cap unset' => [['withdrawal_max_amount' => null], [], null, 'no valid withdrawal_max_amount'],
    'cap zero' => [['withdrawal_max_amount' => '0'], [], null, 'no valid withdrawal_max_amount'],
    'over cap' => [[], ['amount' => '1000.01'], null, 'exceeds the configured cap of 1000'],
    'bad amount' => [[], ['amount' => '1e2'], null, 'positive number'],
    'other currency' => [[], ['currency' => 'AED'], null, 'An AED withdrawal cannot be paid from the PayCrossBorder USD-USA wallet'],
    'unmapped currency' => [[], ['currency' => 'EUR'], null, 'no currency code configured for eur'],
    'unmapped payout currency' => [[], ['payoutCurrency' => 'btc'], null, 'no currency code configured for btc'],
    'incomplete beneficiary' => [[], [], PayCrossBorderApi::beneficiary(['gender' => 'x']), 'beneficiary gender'],
    'bank and wallet' => [[], [], PayCrossBorderApi::bankBeneficiary(['wallet' => ['providerName' => 'G', 'providerCode' => '1']]), 'not both'],
]);

it('refuses a request without a beneficiary', function () {
    Http::fake();

    expect(fn () => pcbService()->send(PayCrossBorderApi::request(['beneficiary' => null])))
        ->toThrow(PaymentProcessingException::class, 'need a beneficiary');
});

// --- preflight -------------------------------------------------------------

it('passes preflight against the Main Wallet in the send currency', function () {
    PayCrossBorderApi::fake();

    $preflight = pcbService()->preflight(PayCrossBorderApi::request());

    expect($preflight->ok)->toBeTrue()
        ->and($preflight->balance)->toBe('5000')
        ->and($preflight->required)->toBe('102.00000000')
        ->and($preflight->walletCurrency)->toBe('USD-USA');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/wallet/walletOwner/1000008444'));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api'));
});

it('reads the balance of wallet_owner_code when it is configured', function () {
    PayCrossBorderApi::fake();

    pcbService(['wallet_owner_code' => '1000009999'])->preflight(PayCrossBorderApi::request());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/wallet/walletOwner/1000009999'));
});

it('refuses preflight on balance problems', function (array|string $balance, string $reason) {
    PayCrossBorderApi::fake(['balance' => pcbStub($balance)]);

    $preflight = pcbService()->preflight(PayCrossBorderApi::request());

    expect($preflight->ok)->toBeFalse()->and($preflight->reason)->toBe($reason);
})->with([
    'too little' => [['json' => PayCrossBorderApi::balance(101.99)], PayoutPreflight::INSUFFICIENT_FUNDS],
    'below minimum' => [['json' => PayCrossBorderApi::balance(5000.0, 150.0)], PayoutPreflight::BELOW_MINIMUM],
    'unreadable value' => [['json' => PayCrossBorderApi::balance('lots')], PayoutPreflight::BALANCE_UNAVAILABLE],
    'no main wallet' => [['json' => ['resultCode' => '0', 'walletList' => []]], PayoutPreflight::BALANCE_UNAVAILABLE],
    'result code' => [['json' => ['resultCode' => '9', 'resultDescription' => 'nope']], PayoutPreflight::BALANCE_UNAVAILABLE],
    'timeout' => ['timeout', PayoutPreflight::BALANCE_UNAVAILABLE],
]);

// --- send --------------------------------------------------------------------

it('quotes by payinAmount and sends a crypto payout', function () {
    PayCrossBorderApi::fake();

    $receipt = pcbService()->send(PayCrossBorderApi::request());

    expect($receipt->reference)->toBe('197930')
        ->and($receipt->state)->toBe(PayoutState::Sent)
        ->and($receipt->rawStatus)->toBe('In Process')
        ->and($receipt->payload['payoutResponseBean']['transReference'])->toBe('197930');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/fxratequotation/api')
        && $request->data() == [
            'payinAmount' => '100.00',
            'payoutAmount' => '',
            'sendCurrencyCode' => 'USD-USA',
            'receiveCurrencyCode' => 'USDT',
            'settlementCurrencyCode' => 'USD-USA',
            'destinationCountryCode' => 'ARE',
            'sourceCountry' => 'ARE',
            'serviceTypeCode' => 'B2C',
            'paymentMode' => 'Cash',
            'senderCode' => '1000008444',
            'customerCode' => '1000002225',
            'chargeTypeCode' => 'OUR',
        ]);

    $payout = pcbPayoutSent()->data()['payout'];

    expect($payout['transactionInfo'])->toEqual([
        'payinAmount' => '100.00',
        'payinCurrency' => 'USD-USA',
        'type' => 'B2C',
        'requestDate' => '12-10-2026',
        'sendClientTrxReference' => PayCrossBorderApi::REFERENCE,
        'descriptionText' => substr(PayCrossBorderApi::REFERENCE, 0, 25),
        'paymentMode' => 'Cash',
        'sendClientCode' => '1000008444',
        'payoutCurrency' => 'USDT',
        'payoutAmount' => '98.00',
        'destinationCountryCode' => 'ARE',
        'settlementCurrency' => 'USD-USA',
        'sourceCountry' => 'ARE',
        'fxRateValue' => '0.98',
        'senderMargin' => '0.98',
    ])->and($payout['sender'])->toEqual(['business' => [
        'isAutoRegistered' => false,
        'declaration' => false,
        'docReferenceNumber' => PayCrossBorderApi::REFERENCE,
        'customerCode' => '1000002225',
    ]])->and($payout['receiver']['customer']['walletAddress'])->toBe('TXyzCustomerWalletAddress000001')
        ->and($payout['compliance'])->toEqual([
            'forexQuoteId' => '114672',
            'remittancePurpose' => 'PAYP002',
            'sourceOfFund' => 'PAYS005',
            'relationship' => 'PAYR002',
        ]);
});

it('sends bank payouts as paymentMode Bank and wallet payouts as Cash', function () {
    PayCrossBorderApi::fake();

    pcbService()->send(PayCrossBorderApi::request(['payoutCurrency' => 'aed'], PayCrossBorderApi::bankBeneficiary()));
    $bank = pcbPayoutSent()->data()['payout'];

    expect($bank['transactionInfo']['paymentMode'])->toBe('Bank')
        ->and($bank['transactionInfo']['payoutCurrency'])->toBe('AED')
        ->and($bank['receiver']['customer']['receiverAccountNumber'])->toBe('AE110260000959024298101');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/fxratequotation/api')
        && $request['paymentMode'] === 'Bank');

    pcbService()->send(PayCrossBorderApi::request(['payoutCurrency' => 'php'], PayCrossBorderApi::walletBeneficiary()));
    $wallet = pcbPayoutSent()->data()['payout'];

    expect($wallet['transactionInfo']['paymentMode'])->toBe('Cash')
        ->and($wallet['transactionInfo']['destinationCountryCode'])->toBe('PHL')
        ->and($wallet['receiver']['customer']['receiverServiceProviderCode'])->toBe('63001');
});

it('falls back to fxRateValue when the quote has no senderMargin', function () {
    PayCrossBorderApi::fake(['quote' => Http::response(PayCrossBorderApi::quote(['senderMargin' => null]))]);

    pcbService()->send(PayCrossBorderApi::request());

    expect(pcbPayoutSent()->data()['payout']['transactionInfo']['senderMargin'])->toBe('0.98');
});

it('refuses to send when the quote is unusable', function (array|string $quote, string $message) {
    PayCrossBorderApi::fake(['quote' => pcbStub($quote)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PaymentProcessingException::class, $message);

    expect(pcbPayoutSent())->toBeNull();
})->with([
    'result code' => [['json' => ['resultCode' => '1015', 'resultDescription' => 'No rate']], 'No rate'],
    'different amount' => [['json' => PayCrossBorderApi::quote(['payinAmount' => '99.50'])], 'quoted 99.50 for a 100.00 payout'],
    'no code' => [['json' => PayCrossBorderApi::quote(['code' => null])], 'without code'],
    'timeout' => ['timeout', 'cURL error 28'],
]);

it('treats PCB refusals as rejections', function (array $payout, string $message) {
    PayCrossBorderApi::fake(['payout' => pcbStub($payout)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PayoutRejectedException::class, $message);
})->with([
    '400' => [['json' => ['resultCode' => '1001', 'resultDescription' => 'Invalid wallet'], 'status' => 400], 'Invalid wallet'],
    '422' => [['json' => ['resultCode' => '1002', 'resultDescription' => 'Bad receiver'], 'status' => 422], 'Bad receiver'],
    'result code in a 200' => [['json' => ['resultCode' => '1003', 'resultDescription' => 'Screening failed']], 'Screening failed'],
]);

it('treats ambiguous payout answers as unknown', function (array|string $payout, string $message) {
    PayCrossBorderApi::fake(['payout' => pcbStub($payout)]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))
        ->toThrow(PayoutOutcomeUnknownException::class, $message);
})->with([
    'timeout' => ['timeout', 'did not answer'],
    '500' => [['json' => ['resultCode' => '500'], 'status' => 500], 'returned 500'],
    '409' => [['status' => 409], 'returned 409'],
    'success without reference' => [['json' => ['resultCode' => '0', 'payoutResponseBean' => []]], 'without a transaction reference'],
    'unreadable body' => [['body' => '<html>gateway</html>'], 'without a transaction reference'],
]);

it('never retries the payout POST', function () {
    PayCrossBorderApi::fake(['payout' => pcbStub(['status' => 500])]);

    expect(fn () => pcbService()->send(PayCrossBorderApi::request()))->toThrow(PayoutOutcomeUnknownException::class);

    expect(collect(Http::recorded())->filter(fn (array $pair) => str_ends_with($pair[0]->url(), '/payoutProcess/api')))->toHaveCount(1);
});

// --- lookup ------------------------------------------------------------------

it('looks a payout up by PCB\'s transId and ties it to our reference', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Payout Pass']))]);

    $receipt = pcbService()->lookup('WD-01TEST', '197930');

    expect($receipt->reference)->toBe('197930')->and($receipt->state)->toBe(PayoutState::Paid);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'transId=197930'));
});

it('never returns null from lookup', function (?string $reference, array|string|null $transaction, string $message) {
    PayCrossBorderApi::fake($transaction === null ? [] : ['transaction' => pcbStub($transaction)]);

    expect(fn () => pcbService()->lookup('WD-01TEST', $reference))
        ->toThrow(PayoutOutcomeUnknownException::class, $message);
})->with([
    'no reference' => [null, null, 'only look a payout up by its own transaction id'],
    'blank reference' => ['  ', null, 'only look a payout up by its own transaction id'],
    'no rows' => ['197930', ['json' => ['resultCode' => '0', 'transactionInfoResponse' => []]], 'returned 0 transactions'],
    'other transId' => ['197930', ['json' => PayCrossBorderApi::transaction(['transId' => 197931])], 'answered for transaction 197931'],
    'other order' => ['197930', ['json' => PayCrossBorderApi::transaction(['senderUniqueId' => 'PAYXPY29FF'])], 'belongs to PAYXPY29FF'],
    'enquiry failed' => ['197930', ['json' => ['resultCode' => '9'], 'status' => 500], 'lookup failed'],
    'timeout' => ['197930', 'timeout', 'lookup failed'],
]);

it('has no payout callbacks', function () {
    expect(fn () => pcbService()->parsePayoutWebhook([]))
        ->toThrow(PaymentProcessingException::class, 'sends no payout callbacks');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderPayoutService" not found`.

- [ ] **Step 3: Write the service**

Create `src/Drivers/PayCrossBorder/PayCrossBorderPayoutService.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * PayCrossBorder payouts: B2C from our pre-registered business to the
 * withdrawing customer, on the crypto, bank or wallet rail.
 *
 * Four facts shape this class. A payout needs a fresh quote — quotes live
 * minutes — so send() always quotes first and pins the quote to our exact
 * amount. There is no idempotency key, so the payout POST is never retried
 * and anything ambiguous is Unknown. There is no callback, so the host polls
 * lookup(). And PCB can only be searched by its own transId, so lookup()
 * without one throws, and every answer is tied back to our reference.
 */
final class PayCrossBorderPayoutService implements SendsPayouts
{
    /**
     * Strict decimal: bcmath 8.3+ throws a ValueError on "2.5e1", padded or
     * signed strings that is_numeric() accepts. Applied after trim().
     */
    private const NUMERIC_PATTERN = '/^\d+(\.\d+)?$/';

    private const SCALE = 8;

    private const DEFAULT_BUFFER_PERCENT = '2';

    /** Definite refusals of the payout POST: nothing created. Every other 4xx is ambiguous. */
    private const REJECTED_STATUSES = [400, 401, 403, 422];

    private const CHARGE_TYPES = ['BEN', 'SHA', 'OUR'];

    private const MAIN_WALLET = 'main wallet';

    private PayCrossBorderClient $client;

    private PayCrossBorderAdapter $adapter;

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.*` array
     */
    public function __construct(private readonly array $config, ?PayCrossBorderClient $client = null)
    {
        $this->client = $client ?? PayCrossBorderClient::fromConfig($config);
        $this->adapter = new PayCrossBorderAdapter;
    }

    /**
     * PCB's `sendClientTrxReference` (10–30 letters and digits) for our order
     * id: the id without hyphens and underscores when that fits — readable in
     * PCB's back office — else a hash. Deterministic, so lookup() recomputes
     * it. Ids differing only in hyphens or underscores would collide; the
     * workflow's WD-<ULID>[-n] ids never do.
     */
    public static function reference(string $externalOrderId): string
    {
        $readable = strtoupper(str_replace(['-', '_'], '', trim($externalOrderId)));

        if (preg_match('/^[A-Z0-9]{10,30}$/', $readable)) {
            return $readable;
        }

        return 'PCB'.strtoupper(substr(hash('sha256', $externalOrderId), 0, 27));
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        $amount = $this->guard($request);
        $currency = $this->sendCurrency();

        // Live, never cached: a stale balance defeats the check. Any failure
        // refuses — it never passes.
        try {
            $wallet = $this->mainWallet($this->client->balance($this->walletOwnerCode()), $currency);
        } catch (PaymentProcessingException) {
            $wallet = null;
        }

        $balance = $wallet === null ? null : self::decimal($wallet['value'] ?? null);

        if ($balance === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::BALANCE_UNAVAILABLE,
                "The PayCrossBorder {$currency} Main Wallet balance could not be read. Nothing was sent.",
            );
        }

        $minimum = self::decimal($wallet['minTransValue'] ?? null);

        if ($minimum !== null && bccomp($amount, $minimum, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::BELOW_MINIMUM,
                sprintf('%s %s is below the PayCrossBorder minimum of %s %s.', $amount, $currency, $minimum, $currency),
            );
        }

        $required = bcmul($amount, self::onePlusPercent($this->bufferPercent()), self::SCALE);

        if (bccomp($balance, $required, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::INSUFFICIENT_FUNDS,
                sprintf('PayCrossBorder balance %s %s; this payout needs %s %s.', $balance, $currency, $required, $currency),
                $balance,
                $required,
                $currency,
            );
        }

        return PayoutPreflight::passed($balance, $required, $currency);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        $amount = $this->guard($request);

        /** @var \Asciisd\CashierCore\DataObjects\PayoutBeneficiary $beneficiary guaranteed by guard() */
        $beneficiary = $request->beneficiary;
        $rail = Rail::for($beneficiary);
        $country = $beneficiary->address->country;
        $receiveCurrency = $this->currencyCode($request->payoutCurrency);
        $reference = self::reference($request->externalOrderId);

        $quote = $this->quote($amount, $receiveCurrency, $country, $rail);

        $body = [
            'transactionInfo' => [
                'payinAmount' => (string) $quote['payinAmount'],
                'payinCurrency' => $this->sendCurrency(),
                'type' => 'B2C',
                'requestDate' => now()->format('d-m-Y'),
                'sendClientTrxReference' => $reference,
                'descriptionText' => substr($reference, 0, 25),
                'paymentMode' => $rail->paymentMode(),
                'sendClientCode' => $this->setting('sender_code'),
                'payoutCurrency' => $receiveCurrency,
                'payoutAmount' => (string) $quote['payoutAmount'],
                'destinationCountryCode' => $country,
                'settlementCurrency' => $this->sendCurrency(),
                'sourceCountry' => $this->setting('source_country'),
                'fxRateValue' => (string) $quote['fxRateValue'],
                // The docs: "in case of 0 it will reflect the fxRateValue".
                'senderMargin' => (string) ($quote['senderMargin'] ?? $quote['fxRateValue']),
            ],
            'sender' => ['business' => [
                'isAutoRegistered' => false,
                'declaration' => false,
                'docReferenceNumber' => $reference,
                'customerCode' => $this->setting('customer_code'),
            ]],
            'receiver' => $rail->builder()->build($request),
            'compliance' => [
                'forexQuoteId' => (string) $quote['code'],
                'remittancePurpose' => $this->setting('remittance_purpose'),
                'sourceOfFund' => $this->setting('source_of_fund'),
                'relationship' => $this->setting('relationship'),
            ],
        ];

        try {
            $response = $this->client->payout($body);
        } catch (ConnectionException $e) {
            throw new PayoutOutcomeUnknownException(
                "PayCrossBorder did not answer the payout request ({$e->getMessage()}). "
                .'It may have been created — do not resend before checking its status.',
            );
        }

        return $this->sendReceipt($response);
    }

    /**
     * Never null: PCB cannot say "no such payout" for our order id, because
     * it can only be searched by its own transId.
     */
    public function lookup(string $externalOrderId, ?string $reference = null): ?PayoutReceipt
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            throw new PayoutOutcomeUnknownException(
                "PayCrossBorder can only look a payout up by its own transaction id, not by order {$externalOrderId}. "
                .'Link the PayCrossBorder reference, or confirm the payout was never sent.',
            );
        }

        try {
            $response = $this->client->transaction($reference);
        } catch (PaymentProcessingException $e) {
            throw new PayoutOutcomeUnknownException("PayCrossBorder lookup failed for {$reference}: {$e->getMessage()}", previous: $e);
        }

        $rows = $response['transactionInfoResponse'] ?? null;

        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[array_key_first($rows)])) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder returned %d transactions for %s; the payout outcome is unknown.',
                is_array($rows) ? count($rows) : 0,
                $reference,
            ));
        }

        $row = $rows[array_key_first($rows)];
        $returned = (string) ($row['transId'] ?? '');

        if ($returned !== $reference) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder answered for transaction %s when %s was requested; the payout outcome is unknown.',
                $returned === '' ? '(no id)' : $returned,
                $reference,
            ));
        }

        $owner = (string) ($row['senderUniqueId'] ?? '');

        if ($owner !== self::reference($externalOrderId)) {
            throw new PayoutOutcomeUnknownException(sprintf(
                'PayCrossBorder transaction %s belongs to %s, not order %s.',
                $reference,
                $owner === '' ? '(no reference)' : $owner,
                $externalOrderId,
            ));
        }

        return $this->adapter->payoutReceipt($row);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        throw new PaymentProcessingException('PayCrossBorder sends no payout callbacks; payouts are polled.');
    }

    /**
     * Fail closed on configuration and input; returns the trimmed amount.
     * Shared by preflight() and send(), and makes no HTTP call.
     *
     * @throws PaymentProcessingException
     */
    private function guard(PayoutRequest $request): string
    {
        if (! filter_var($this->config['withdrawals_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new PaymentProcessingException(
                'PayCrossBorder withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // Missing, empty, zero or malformed is a configuration error — never "no cap".
        $cap = trim((string) ($this->config['withdrawal_max_amount'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $cap) || bccomp($cap, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException(
                'PayCrossBorder withdrawals are enabled but no valid withdrawal_max_amount is configured. Refusing to send.',
            );
        }

        $amount = trim($request->amount);

        if (! preg_match(self::NUMERIC_PATTERN, $amount) || bccomp($amount, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException('PayCrossBorder withdrawal amount must be a positive number.');
        }

        if (bccomp($amount, $cap, self::SCALE) > 0) {
            throw new PaymentProcessingException(sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap));
        }

        if ($request->beneficiary === null) {
            throw new PaymentProcessingException('PayCrossBorder payouts need a beneficiary in the withdrawal details.');
        }

        $send = $this->sendCurrency();

        if ($this->currencyCode($request->currency) !== $send) {
            throw new PaymentProcessingException(sprintf(
                'An %s withdrawal cannot be paid from the PayCrossBorder %s wallet.',
                strtoupper($request->currency),
                $send,
            ));
        }

        $this->currencyCode($request->payoutCurrency);

        Rail::for($request->beneficiary)->builder()->assertComplete($request);

        return $amount;
    }

    /**
     * A fresh quote pinned to our amount.
     *
     * @return array<string, mixed> the `fxRateQuotation` object
     *
     * @throws PaymentProcessingException nothing was sent
     */
    private function quote(string $amount, string $receiveCurrency, string $country, Rail $rail): array
    {
        $response = $this->client->quote([
            'payinAmount' => $amount,
            'payoutAmount' => '',
            'sendCurrencyCode' => $this->sendCurrency(),
            'receiveCurrencyCode' => $receiveCurrency,
            'settlementCurrencyCode' => $this->sendCurrency(),
            'destinationCountryCode' => $country,
            'sourceCountry' => $this->setting('source_country'),
            'serviceTypeCode' => 'B2C',
            'paymentMode' => $rail->paymentMode(),
            'senderCode' => $this->setting('sender_code'),
            'customerCode' => $this->setting('customer_code'),
            'chargeTypeCode' => $this->chargeType(),
        ]);

        $quote = $response['fxRateQuotation'] ?? null;

        foreach (['code', 'fxRateValue', 'payinAmount', 'payoutAmount'] as $key) {
            if (! is_array($quote) || ! is_scalar($quote[$key] ?? null) || (string) $quote[$key] === '') {
                throw new PaymentProcessingException("PayCrossBorder returned a quote without {$key}. Nothing was sent.");
            }
        }

        $quoted = self::decimal($quote['payinAmount']);

        if ($quoted === null || bccomp($quoted, $amount, 2) !== 0) {
            throw new PaymentProcessingException(sprintf(
                'PayCrossBorder quoted %s for a %s payout. Nothing was sent.',
                (string) $quote['payinAmount'],
                $amount,
            ));
        }

        return $quote;
    }

    /**
     * @throws PayoutRejectedException
     * @throws PayoutOutcomeUnknownException
     */
    private function sendReceipt(Response $response): PayoutReceipt
    {
        $json = $response->json();
        $bean = is_array($json) && is_array($json['payoutResponseBean'] ?? null) ? $json['payoutResponseBean'] : [];
        $reference = trim((string) ($bean['transReference'] ?? ''));
        $resultCode = is_array($json) && is_scalar($json['resultCode'] ?? null) ? (string) $json['resultCode'] : null;

        if ($response->successful()) {
            if ($reference !== '' && $resultCode === '0') {
                return new PayoutReceipt(
                    reference: $reference,
                    rawStatus: (string) ($bean['status'] ?? ''),
                    state: PayoutState::Sent,
                    payload: $json,
                );
            }

            if ($reference === '' && $resultCode !== null && $resultCode !== '0') {
                throw new PayoutRejectedException('PayCrossBorder refused the payout: '.PayCrossBorderClient::describe($response));
            }

            // The reference is the only handle on the payout. A 2xx without
            // one is as ambiguous as a 5xx, not a success.
            throw new PayoutOutcomeUnknownException(
                'PayCrossBorder answered the payout without a transaction reference ('.PayCrossBorderClient::describe($response).'). '
                .'It may have been created — do not resend before checking its status.',
            );
        }

        if (in_array($response->status(), self::REJECTED_STATUSES, true)) {
            throw new PayoutRejectedException('PayCrossBorder refused the payout: '.PayCrossBorderClient::describe($response));
        }

        throw new PayoutOutcomeUnknownException(sprintf(
            'PayCrossBorder returned %d for the payout request: %s. It may have been created — do not resend before checking its status.',
            $response->status(),
            PayCrossBorderClient::describe($response),
        ));
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function mainWallet(array $response, string $currency): ?array
    {
        foreach ((array) ($response['walletList'] ?? []) as $wallet) {
            if (is_array($wallet)
                && strtolower(trim((string) ($wallet['walletTypeName'] ?? ''))) === self::MAIN_WALLET
                && strtoupper(trim((string) ($wallet['currencyName'] ?? ''))) === strtoupper($currency)) {
                return $wallet;
            }
        }

        return null;
    }

    /**
     * Our currency code → PCB's, via `currency_codes` (case-insensitive keys).
     *
     * @throws PaymentProcessingException when unmapped
     */
    private function currencyCode(string $ours): string
    {
        $key = strtolower(trim($ours));

        foreach ((array) ($this->config['currency_codes'] ?? []) as $code => $pcb) {
            if (strtolower(trim((string) $code)) === $key && is_string($pcb) && trim($pcb) !== '') {
                return trim($pcb);
            }
        }

        throw new PaymentProcessingException("PayCrossBorder has no currency code configured for {$key}; add it to currency_codes.");
    }

    private function sendCurrency(): string
    {
        return $this->setting('send_currency');
    }

    private function walletOwnerCode(): string
    {
        $code = trim((string) ($this->config['wallet_owner_code'] ?? ''));

        return $code !== '' ? $code : $this->setting('sender_code');
    }

    private function chargeType(): string
    {
        $type = strtoupper(trim((string) ($this->config['charge_type'] ?? 'OUR')));

        if (! in_array($type, self::CHARGE_TYPES, true)) {
            throw new PaymentProcessingException("PayCrossBorder charge_type must be BEN, SHA or OUR, not '{$type}'.");
        }

        return $type;
    }

    /**
     * @throws PaymentProcessingException when the connection lacks the key
     */
    private function setting(string $key): string
    {
        $value = trim((string) ($this->config[$key] ?? ''));

        if ($value === '') {
            throw new PaymentProcessingException("PayCrossBorder connection is missing {$key}.");
        }

        return $value;
    }

    private function bufferPercent(): string
    {
        $percent = trim((string) ($this->config['balance_buffer_percent'] ?? self::DEFAULT_BUFFER_PERCENT));

        return preg_match(self::NUMERIC_PATTERN, $percent) ? $percent : self::DEFAULT_BUFFER_PERCENT;
    }

    private static function onePlusPercent(string $percent): string
    {
        return bcadd('1', bcdiv($percent, '100', self::SCALE), self::SCALE);
    }

    /**
     * A PCB number (JSON float or string) as a strict decimal string, or null
     * when it is not one — never coerced to zero.
     */
    private static function decimal(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match(self::NUMERIC_PATTERN, $value) ? $value : null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php`
Expected: PASS. Three checks to make if something fails:
- **"passes preflight…":** `balance` is `'5000'`, because `decimal(5000.0)` is `sprintf('%.8F')` with the trailing zeros and dot removed, and `required` is `bcmul('100.00', '1.02000000', 8)`, which is `'102.00000000'`.
- **"different amount":** `payinAmount` `'99.50'` against `'100.00'` at scale 2 gives a non-zero `bccomp`, so the send is refused with `quoted 99.50 for a 100.00 payout`.
- **"unreadable body":** a 200 with an HTML body decodes to `null`, so the result code is `null` and there is no reference. That produces the "without a transaction reference" Unknown.

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/PayCrossBorder/PayCrossBorderPayoutService.php tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php
git commit -m "feat(paycrossborder): payout service — guard, balance preflight, quote, send, lookup

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Provider and registration

**Files:**
- Create: `src/Drivers/PayCrossBorder/PayCrossBorderProvider.php`
- Modify: `src/CashierCoreServiceProvider.php` (`BUNDLED_DRIVERS`)
- Modify: `src/Cashier.php` (`fakeConnection()` match)
- Modify: `config/cashier-core.php` (connections docblock)
- Test: `tests/Unit/Drivers/PayCrossBorderProviderTest.php`

**Interfaces:**
- Consumes: `PayCrossBorderPayoutService` (Task 6).
- Produces: `class PayCrossBorderProvider implements PaymentProcessorInterface, SendsPayouts`, registered as driver `paycrossborder`; `getName()` is `'paycrossborder'`; `supports('payout')` is true, everything else false.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Drivers/PayCrossBorderProviderTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderProvider;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('resolves the bundled paycrossborder driver from a connection', function () {
    PayCrossBorderApi::configure();

    $provider = app(ConnectionRegistry::class)->get('paycrossborder');

    expect($provider)->toBeInstanceOf(PayCrossBorderProvider::class)
        ->and($provider)->toBeInstanceOf(SendsPayouts::class)
        ->and($provider->getName())->toBe('paycrossborder')
        ->and($provider->supports('payout'))->toBeTrue()
        ->and($provider->supports('charge'))->toBeFalse()
        ->and($provider->supports('webhook'))->toBeFalse();
});

it('refuses to construct without credentials or codes', function (string $missing) {
    $config = PayCrossBorderApi::CONFIG;
    unset($config[$missing]);

    expect(fn () => new PayCrossBorderProvider($config))
        ->toThrow(PaymentProcessingException::class, "{$missing} is missing");
})->with(['base_url', 'username', 'password', 'sender_code', 'customer_code']);

it('supports nothing on the deposit side', function () {
    $provider = new PayCrossBorderProvider(PayCrossBorderApi::CONFIG);

    expect(fn () => $provider->charge(['amount' => 10]))->toThrow(PaymentProcessingException::class, 'payouts only')
        ->and(fn () => $provider->refund('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->capture('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->authorize([]))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->void('x'))->toThrow(BadMethodCallException::class)
        ->and(fn () => $provider->parseWebhook([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->validatePaymentData([]))->toThrow(PaymentProcessingException::class)
        ->and(fn () => $provider->getPaymentStatus('x'))->toThrow(PaymentProcessingException::class)
        ->and($provider->retrieve('x'))->toBeNull()
        ->and($provider->verifyWebhookSignature([], 'sig'))->toBeFalse();
});

it('delegates payouts to the payout service', function () {
    PayCrossBorderApi::fake();

    $receipt = (new PayCrossBorderProvider(PayCrossBorderApi::CONFIG))->send(PayCrossBorderApi::request());

    expect($receipt->reference)->toBe('197930');
});

it('gives fakeConnection working defaults', function () {
    Cashier::fakeConnection('paycrossborder');

    expect(app(ConnectionRegistry::class)->get('paycrossborder'))->toBeInstanceOf(PayCrossBorderProvider::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderProviderTest.php`
Expected: FAIL with `Class "Asciisd\CashierCore\Drivers\PayCrossBorder\PayCrossBorderProvider" not found`.

- [ ] **Step 3: Write the provider**

Create `src/Drivers/PayCrossBorder/PayCrossBorderProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\PayCrossBorder;

use Asciisd\CashierCore\Contracts\PaymentProcessorInterface;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PaymentResult;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\DataObjects\RefundResult;
use Asciisd\CashierCore\DataObjects\TransactionWebhookUpdate;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;

/**
 * PayCrossBorder (docs.paycrossborder.com) — payouts only.
 *
 * PCB's API is for Financial Institution customers: it sends money out on
 * crypto, bank and mobile-wallet rails and offers no collection, refund or
 * callback endpoints. Everything on the deposit side is unsupported; payouts
 * go through PayCrossBorderPayoutService and are polled with lookup().
 */
class PayCrossBorderProvider implements PaymentProcessorInterface, SendsPayouts
{
    private const REQUIRED = ['base_url', 'username', 'password', 'sender_code', 'customer_code'];

    /** @var string[] */
    private array $supportedFeatures = ['payout'];

    private ?PayCrossBorderPayoutService $payouts = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = [])
    {
        foreach (self::REQUIRED as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new PaymentProcessingException("PayCrossBorder provider is not configured: {$key} is missing.");
            }
        }
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        return $this->payouts()->preflight($request);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        return $this->payouts()->send($request);
    }

    public function lookup(string $externalOrderId, ?string $reference = null): ?PayoutReceipt
    {
        return $this->payouts()->lookup($externalOrderId, $reference);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        return $this->payouts()->parsePayoutWebhook($payload);
    }

    public function charge(array $data): PaymentResult
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it cannot take deposits.');
    }

    public function retrieve(string $transactionId): ?PaymentResult
    {
        return null;
    }

    public function getPaymentStatus(string $transactionId): string
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it has no payment status.');
    }

    public function validatePaymentData(array $data): array
    {
        throw new PaymentProcessingException('PayCrossBorder handles payouts only; it cannot take deposits.');
    }

    public function parseWebhook(array $payload): TransactionWebhookUpdate
    {
        throw new PaymentProcessingException('PayCrossBorder sends no callbacks.');
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return false;
    }

    public function refund(string $transactionId, ?float $amount = null): RefundResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support refunds.');
    }

    public function capture(string $transactionId, ?float $amount = null): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support capture.');
    }

    public function authorize(array $data): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support authorize.');
    }

    public function void(string $transactionId): PaymentResult
    {
        throw new \BadMethodCallException('PayCrossBorder does not support void.');
    }

    public function getName(): string
    {
        return 'paycrossborder';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supportedFeatures, true);
    }

    private function payouts(): PayCrossBorderPayoutService
    {
        return $this->payouts ??= new PayCrossBorderPayoutService($this->config);
    }
}
```

- [ ] **Step 4: Register the driver**

In `src/CashierCoreServiceProvider.php`, in `BUNDLED_DRIVERS`, replace:

```php
        'xoala' => Drivers\Xoala\XoalaProvider::class,
```

with:

```php
        'xoala' => Drivers\Xoala\XoalaProvider::class,
        'paycrossborder' => Drivers\PayCrossBorder\PayCrossBorderProvider::class,
```

- [ ] **Step 5: Add `fakeConnection()` defaults**

In `src/Cashier.php`, inside `fakeConnection()`'s `match ($driver)`, insert before `default => [],`:

```php
            'paycrossborder' => [
                'base_url' => 'https://pcb.test',
                'username' => '1000008340',
                'password' => "{$name}-password",
                'sender_code' => '1000008444',
                'customer_code' => '1000002225',
                'source_country' => 'ARE',
                'send_currency' => 'USD-USA',
                'currency_codes' => ['usd' => 'USD-USA', 'usdt' => 'USDT'],
                'charge_type' => 'OUR',
                'remittance_purpose' => 'PAYP002',
                'source_of_fund' => 'PAYS005',
                'relationship' => 'PAYR002',
            ],
```

- [ ] **Step 6: Document the connection**

In `config/cashier-core.php`, replace:

```php
    |     'balance_buffer_percent' => env('HEROPAYMENT_BALANCE_BUFFER_PERCENT', 2),
    | ],
    |
    */
```

with:

```php
    |     'balance_buffer_percent' => env('HEROPAYMENT_BALANCE_BUFFER_PERCENT', 2),
    | ],
    |
    | PayCrossBorder is payouts only: crypto (wallet + network), bank and
    | mobile-wallet rails, B2C from our business registered with PCB. There
    | are no callbacks — the host polls syncPayout(). Besides payout_address
    | and payout_currency (and payout_extra_id, the crypto network), the host
    | stores the payee as withdrawal_details.beneficiary (see
    | PayoutBeneficiary::fromArray()); bank details pick the bank rail, wallet
    | details the wallet rail, neither the crypto rail.
    |
    | 'paycrossborder' => [
    |     'driver' => 'paycrossborder',
    |     'base_url' => env('PCB_BASE_URL'),          // required: the docs only say "host"
    |     'username' => env('PCB_USERNAME'),
    |     'password' => env('PCB_PASSWORD'),
    |     // null sends `password` as issued; 'md5' sends strtoupper(md5(password)).
    |     'password_hash' => env('PCB_PASSWORD_HASH'),
    |     // Only if PCB issues OAuth client credentials for the token call.
    |     'oauth_client_id' => env('PCB_OAUTH_CLIENT_ID'),
    |     'oauth_client_secret' => env('PCB_OAUTH_CLIENT_SECRET'),
    |     'sender_code' => env('PCB_SENDER_CODE'),    // senderCode / sendClientCode
    |     'customer_code' => env('PCB_CUSTOMER_CODE'), // our registered business
    |     'wallet_owner_code' => env('PCB_WALLET_OWNER_CODE'), // defaults to sender_code
    |     'source_country' => env('PCB_SOURCE_COUNTRY'), // ISO-3, e.g. ARE
    |     // Our wallet and settlement currency at PCB. Every withdrawal is sent
    |     // in it, so the transaction currency must map to it.
    |     'send_currency' => 'USD-USA',
    |     // our currency code (case-insensitive) => PCB currency code
    |     'currency_codes' => ['usd' => 'USD-USA', 'usdt' => 'USDT', 'usdc' => 'USDC'],
    |     'charge_type' => 'OUR',                     // BEN | SHA | OUR
    |     'remittance_purpose' => 'PAYP002',          // master-API codes
    |     'source_of_fund' => 'PAYS005',
    |     'relationship' => 'PAYR002',
    |     // Real money and no confirmed sandbox. Off until proven; every
    |     // payout is capped at withdrawal_max_amount.
    |     'withdrawals_enabled' => env('PCB_WITHDRAWALS_ENABLED', false),
    |     'withdrawal_max_amount' => env('PCB_WITHDRAWAL_MAX_AMOUNT'),
    |     'balance_buffer_percent' => env('PCB_BALANCE_BUFFER_PERCENT', 2),
    | ],
    |
    */
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/PayCrossBorderProviderTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Drivers/PayCrossBorder/PayCrossBorderProvider.php src/CashierCoreServiceProvider.php src/Cashier.php config/cashier-core.php tests/Unit/Drivers/PayCrossBorderProviderTest.php
git commit -m "feat(paycrossborder): register the payouts-only driver

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: End-to-end through the withdrawal workflow

**Files:**
- Test: `tests/Feature/PayCrossBorderPayoutWorkflowTest.php`

**Interfaces:**
- Consumes: everything above, plus the existing `WithdrawalWorkflow::sendPayout(Transaction, Actor): Result` and `::syncPayout(Transaction, Actor): Result`.

- [ ] **Step 1: Write the feature test**

Create `tests/Feature/PayCrossBorderPayoutWorkflowTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\PayCrossBorderApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    PayCrossBorderApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function pcbWithdrawal(array $overrides = [], ?array $beneficiary = null): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'paycrossborder',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
        'withdrawal_method' => 'crypto',
        'withdrawal_details' => [
            'payout_address' => 'TXyzCustomerWalletAddress000001',
            'payout_currency' => 'usdt',
            'payout_extra_id' => 'TRC20',
            'beneficiary' => $beneficiary ?? PayCrossBorderApi::beneficiary(),
        ],
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function pcbActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

function pcbWorkflow(): WithdrawalWorkflow
{
    return app(WithdrawalWorkflow::class);
}

it('sends the payout and records PCB\'s transReference', function () {
    PayCrossBorderApi::fake();

    $result = pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());
    $fresh = $transaction->fresh();

    expect($result->ok)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->payout_reference)->toBe('197930');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payoutProcess/api')
        && $request['payout']['transactionInfo']['sendClientTrxReference'] === PayCrossBorderApi::REFERENCE
        && $request['payout']['receiver']['customer']['receiverFirstName'] === 'Kuldeep');
    $this->ledger->assertNothingMoved();
});

it('closes the withdrawal when PCB reports Payout Pass', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Payout Pass']))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    $result = pcbWorkflow()->syncPayout($transaction, pcbActor());

    expect($result->ok)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
});

it('marks the payout failed with PCB\'s reason on Payout Fail', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction([
        'statusName' => 'Payout Fail',
        'vendorResultDescription' => 'Beneficiary account closed',
    ]))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    pcbWorkflow()->syncPayout($transaction, pcbActor());
    $fresh = $transaction->fresh();

    expect($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->payout_state->isInFlight())->toBeFalse()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->metadata['payout_error'])->toBe('PayCrossBorder Payout Fail: Beneficiary account closed');
});

it('keeps a Technical Failure in flight as unknown', function () {
    PayCrossBorderApi::fake(['transaction' => Http::response(PayCrossBorderApi::transaction(['statusName' => 'Technical Failure']))]);

    $transaction = pcbWithdrawal(['payout_state' => PayoutState::Sent, 'payout_reference' => '197930']);

    pcbWorkflow()->syncPayout($transaction, pcbActor());

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown)
        ->and($transaction->fresh()->payout_state->isInFlight())->toBeTrue();
});

it('records a PCB refusal as a failed payout', function () {
    PayCrossBorderApi::fake(['payout' => Http::response(['resultCode' => '1001', 'resultDescription' => 'Invalid wallet'], 400)]);

    $result = pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());

    expect($result->ok)->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Failed)
        ->and($transaction->fresh()->metadata['payout_error'])->toContain('Invalid wallet');
});

it('marks a timed-out payout unknown and keeps the claim', function () {
    PayCrossBorderApi::fake(['payout' => fn () => throw new ConnectionException('cURL error 28')]);

    pcbWorkflow()->sendPayout($transaction = pcbWithdrawal(), pcbActor());

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Unknown)
        ->and($transaction->fresh()->metadata)->toHaveKey(TransferClaim::METADATA_KEY);
});

it('fails cleanly before calling PCB when the beneficiary is incomplete', function () {
    PayCrossBorderApi::fake();

    $result = pcbWorkflow()->sendPayout(
        $transaction = pcbWithdrawal(beneficiary: PayCrossBorderApi::beneficiary(['dateOfBirth' => null])),
        pcbActor(),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('dateOfBirth is required for crypto payouts')
        ->and($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run the feature test**

Run: `vendor/bin/pest tests/Feature/PayCrossBorderPayoutWorkflowTest.php`
Expected: PASS. No production code should change in this task. If a test fails, the bug is in an earlier task's code: fix it there, and re-run that task's unit test as well.

The last test expects `Http::assertNothingSent()`. That depends on the workflow calling `preflight()` and running the guard before any balance call. If the workflow builds the request and calls preflight first, the guard throws before any HTTP. If it doesn't hold, read `WithdrawalWorkflow::sendPayout()` to see the order, and change only the assertion to `Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/payoutProcess/api'))`.

If "keeps a Technical Failure in flight as unknown" fails, read `WithdrawalWorkflow::syncPayout()` and `applyPayoutUpdate()` (around lines 559–700). An `Unknown` receipt is applied like any other state, so the row should move to `Unknown`. If the workflow deliberately ignores `Unknown` receipts from sync, change the assertion to `toBe(PayoutState::Sent)` (still in flight, with no refund). Note the change in the task report. Never change the adapter mapping to make this pass.

- [ ] **Step 3: Run the whole suite**

Run: `vendor/bin/pest`
Expected: PASS, no failures anywhere.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/PayCrossBorderPayoutWorkflowTest.php
git commit -m "test(paycrossborder): payout lifecycle through WithdrawalWorkflow

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Docs, changelog and skill

**Files:**
- Modify: `README.md` (driver list, around line 13)
- Modify: `CHANGELOG.md` (new top section)
- Create: `.claude/skills/paycrossborder/SKILL.md`
- Create: `.claude/skills/paycrossborder/references/api.md`

- [ ] **Step 1: Update the README driver list**

In `README.md`, replace:

```markdown
Seven direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport,
Sticpay, MyFatoorah, Xoala** — plus internal `manual`, `bank_transfer` and
`crypto` providers. All of them are hosted-redirect: no PAN or CVV ever
touches your application (SAQ-A posture).
```

with:

```markdown
Eight direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport,
Sticpay, MyFatoorah, Xoala, Digiblox** — plus internal `manual`,
`bank_transfer` and `crypto` providers. All of them are hosted-redirect: no
PAN or CVV ever touches your application (SAQ-A posture). **PayCrossBorder**
also ships bundled as a payouts-only driver (crypto, bank and mobile-wallet
withdrawals).
```

- [ ] **Step 2: Add the changelog entry**

At the top of `CHANGELOG.md`, above `## 2.9.1`, insert:

```markdown
## 2.10.0

- Payouts: new `paycrossborder` driver (PayCrossBorder, docs.paycrossborder.com), payouts only, on three rails chosen per withdrawal: crypto (wallet address + `payout_extra_id` network), bank and mobile wallet. Every payout is B2C from the business registered with PCB (`customer_code`), quoted by the exact amount sent (`payinAmount`). PCB sends no callbacks: the host polls `syncPayout()`, and `lookup()` needs the stored `payout_reference` (PCB's `transId`). `Technical Failure` maps to `Unknown`, not `Failed`. Ships disabled: set `withdrawals_enabled` and `withdrawal_max_amount`.
- Payouts: `PayoutRequest` has an optional `?PayoutBeneficiary $beneficiary` (with `BeneficiaryAddress`, `BeneficiaryBank`, `BeneficiaryWallet`), populated from `withdrawal_details.beneficiary`. Absent is `null` and changes nothing for other drivers; a malformed beneficiary now refuses the send for every driver.
- PayCrossBorder open items before going live: whether the login password is issued pre-hashed (`password_hash`), whether the token call needs OAuth client credentials (`oauth_client_id`), and whether enquiry `senderUniqueId` equals our `sendClientTrxReference` (lookup relies on it).
```

- [ ] **Step 3: Write the skill**

Create `.claude/skills/paycrossborder/SKILL.md`:

```markdown
---
name: paycrossborder
description: Use when working with the PayCrossBorder payout gateway (docs.paycrossborder.com) — the PayCrossBorder driver, PayCrossBorderClient/PayCrossBorderProvider/PayCrossBorderPayoutService/PayCrossBorderAdapter, the Crypto/Bank/Wallet receiver builders, PayoutBeneficiary, or PCB login, quotation, payout, transaction enquiry and balance calls. Covers the FI-customer payout API (crypto, FIAT and WPT rails).
---

# PayCrossBorder

PayCrossBorder (PCB) is a cross-border payout network. Its API is for
**Financial Institution customers** and is **payouts only**: no collections,
no refunds, **no callbacks**. Docs: https://docs.paycrossborder.com/#/
(docsify; raw pages at `https://docs.paycrossborder.com/<PAGE>.md`).

## Driver map

| File | Role |
|---|---|
| `src/Drivers/PayCrossBorder/PayCrossBorderProvider.php` | Connection entry. Deposit side unsupported. |
| `PayCrossBorderClient.php` | Login (OAuth password grant, cached token), quote, payout, enquiry, balance. |
| `PayCrossBorderPayoutService.php` | Guard → balance preflight → fresh quote → payout → lookup. |
| `Rail.php` + `Receivers/*` | Rail choice and the per-rail `receiver` block. |
| `PayCrossBorderAdapter.php` | `statusName` → `PayoutState`. |
| `src/DataObjects/PayoutBeneficiary.php` | Payee identity from `withdrawal_details.beneficiary`. |

Design: `docs/superpowers/specs/2026-10-12-paycrossborder-payouts-design.md`.
API details and quirks: `references/api.md`.

## Rules that are easy to break

- `payout()` is never retried — no idempotency key.
- `lookup()` never returns null; without PCB's `transId` it throws.
- Success is HTTP 2xx **and** `resultCode === "0"`.
- `Technical Failure` → `Unknown`, never `Failed`.
- Names, account numbers, wallet addresses, phones, ID numbers: refuse, never alter. Only address text is sanitised.
- PCB field names have typos that are load-bearing: `receiverAddresssCity`, `businessAddresssCity`.
```

Create `.claude/skills/paycrossborder/references/api.md`:

```markdown
# PayCrossBorder API notes

Vendored from docs.paycrossborder.com (V 1.2.0), 2026-10-12. Host is
unspecified in the docs ("http://host"), so `base_url` is per connection.

## Common

- Headers on every call: `channel: WEB`, `source: AGENT`, `Accept-Language: en`.
- Success: HTTP 2xx and `resultCode: "0"`. The docs list no error codes.
- Dates: `requestDate` is `dd-mm-yyyy`; identity dates are `yyyy-mm-dd`.
- Countries are ISO-3. Currencies are PCB codes such as `USD-USA`, `USDT`.

## Login — `POST /ewallet/oauth/token` (form)

`grant_type=password&scope=read write&username=…&password=…`. The example
password is an uppercase hex digest (unconfirmed whether issued or derived →
`password_hash`). Response: `access_token`, `expires_in` (seconds),
`clientCode`.

## Quotation — `POST /ewallet/api/v1/fxratequotation/api`

`payinAmount` or `payoutAmount`, `sendCurrencyCode`, `receiveCurrencyCode`,
`settlementCurrencyCode`, `destinationCountryCode`, `sourceCountry`,
`serviceTypeCode` (B2C…), `paymentMode` (Cash | Bank), `senderCode`,
`customerCode`, `chargeTypeCode` (BEN | SHA | OUR). Response
`fxRateQuotation`: `code` (→ `compliance.forexQuoteId`), `fxRateValue`,
`senderMargin`, `payinAmount`, `payoutAmount`, `fee`, `duration`/`timeLeft`
(minutes; quotes expire in ~5).

## Payout — `POST /ewallet/api/v1/payoutProcess/api`

Body `{"payout": {transactionInfo, sender, receiver, compliance}}`.
`sendClientTrxReference` 10–30 alphanumerics; `descriptionText` ≤ 25.
Registered sender: `{business: {isAutoRegistered: false, declaration: false,
docReferenceNumber, customerCode}}`. Receiver per rail:

- Crypto: identity + address + `receiverDOB` (mandatory) + `walletAddress`
  (26–50) + `cryptoNetwork`.
- FIAT: identity + address + `receiverAccountNumber`,
  `receiverAccountHolderName`, `receiverBankName`, `receiverSwiftCode`,
  `receiverBankCode`, `receiverAccountType` (BRA, PHL, DOM, URY),
  `taxIdNumber` (ARG, THA, BRA, ECU, GTM, HND, KAZ, MEX, PER, CHL, PHL, JPN,
  URY, COL, CRI, RUS, BLR, PAK); phone on SWIFT to BRA, PHL, CHN, UGA, URY,
  COL, MEX, HKG, MYS. Per-country Local RAIL tables in COUNTRYVALIDATIONS.md
  are not encoded in the driver.
- WPT: identity + address + `receiverMsisdn`, `dialCode`,
  `receiverServiceProviderName`, `receiverServiceProviderCode`,
  `receiverServiceProviderMobile`.

Response `payoutResponseBean.transReference` is PCB's id (→ `payout_reference`),
`status` starts as `In Process`.

## Enquiry — `GET /ewallet/api/v1/transactionInfo/api?types=all&status=all&transId=…`

`transactionInfoResponse[]` rows: `transId`, `senderUniqueId` (assumed = our
`sendClientTrxReference`), `statusName`, `transactionStatus` (`IP` seen),
`vendorResultDescription`.

## Statuses (responseCodes.md)

In Process, Payout Processing → Sent · Payout Pass → Paid · Payout Fail,
Reverse → Failed · Technical Failure → Unknown.

## Balance — `GET /ewallet/api/v1/wallet/walletOwner/{walletOwnerCode}`

`walletList[]`: `walletTypeName` (Main Wallet | Comission Wallet),
`currencyName`, `value` (the balance), `minTransValue`, `maxTransValue`.
```

- [ ] **Step 4: Run the whole suite once more**

Run: `vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add README.md CHANGELOG.md .claude/skills/paycrossborder
git commit -m "docs(paycrossborder): README, changelog and driver skill

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
