# Heropayments Payouts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send approved withdrawals to the customer's crypto wallet through Heropayments V2. Check the Heropayments balance before every send, and close the withdrawal from Heropayments' callbacks.

**Architecture:** A generic `SendsPayouts` contract is implemented by `HeropaymentProvider`, which delegates to a new `HeropaymentPayoutService`. `WithdrawalWorkflow` gains `sendPayout()`, `applyPayoutUpdate()` and `syncPayout()`. It tracks the PSP side in a new, indexed `payout_state` column while the customer-facing `status` stays `Processing`. Withdrawal callbacks are routed to a new `ProcessPayoutWebhook` job and never reach the deposit `WebhookProcessor`.

**Tech Stack:** PHP 8.3, Laravel (package on Orchestra Testbench), Pest, `Http::fake`, bcmath.

**Spec:** `docs/superpowers/specs/2026-09-29-heropayments-payouts-design.md`

**Run tests with:** `vendor/bin/pest` (full suite, currently 669 passing) or
`vendor/bin/pest <path>` / `vendor/bin/pest --filter '<name>'`.

## Global Constraints

- Every PHP file starts with `declare(strict_types=1);`.
- Money arithmetic uses bcmath on decimal strings, at scale 8. Never compare money as floats.
- Numeric config and amounts are validated with the strict pattern `/^\d+(\.\d+)?$/` after `trim()`. `is_numeric()` is not enough, because bcmath 8.3+ crashes on `2.5e1` and on padded strings.
- `withdrawals_enabled` defaults to **false**.
- Sending refuses (fail closed) unless all of these hold: `withdrawals_enabled` is true, `withdrawal_max_amount` is a valid decimal greater than 0, the amount is at most the cap, `fee_percent` is set, and a callback URL resolves.
- `POST /v2/withdrawal` is never retried automatically. It uses `PspHttp::client()`, never `idempotent()`, and never `->throw()`.
- The customer-facing `status` stays `Processing` through every payout failure and retry. Only `finished` moves it to `Succeeded`.
- Heropayments' failure text goes in `metadata.payout_error`. **Never** put it in `error_message`, because hosts may show that to the customer.
- `cancel()` must refuse while `payout_state` is `sent` or `unknown`.
- Payout details come from `withdrawal_details` keys `payout_address`, `payout_currency`, `payout_extra_id` (optional) and `customer_email` (optional).
- Pest helper functions are global: every new helper name must be unique across `tests/`. This plan prefixes them with `hp`.

## Review Focus

These failure modes are the ones most likely to bite. Each has a pinned test in the task named.

1. **A stale instance cancels after a send settled.** An admin page loaded before the send calls `cancel()` afterwards. It must be refused by the re-check under the lock, not refund MT5 while money is in flight. *(Task 5, "cancel re-checks payout_state under the lock")*
2. **`finished` arrives for a cancelled withdrawal.** The row is left untouched and a **critical** log is written for manual follow-up. *(Task 6)*
3. **A Heropayments outage during preflight.** A rate or balance lookup fails, or a failure was cached as null. The send must be refused, never waved through. *(Task 3, "rate unavailable" and "balance unavailable")*
4. **A malformed balance string** such as `"1,234.50"`, `""` or `"2.5e1"`. It must refuse with `balance_unavailable`, not throw a `ValueError`. *(Task 3)*
5. **A withdrawal missing `payout_address`.** It fails cleanly and the claim is released, so the row isn't locked for 10 minutes. *(Task 5)*

---

## File Structure

| File | Responsibility |
|---|---|
| Create `database/migrations/2026_09_29_000001_add_payout_columns_to_transactions_table.php` | `payout_state` (indexed) and `payout_reference` columns |
| Create `src/Enums/PayoutState.php` | `Sent` / `Unknown` / `Failed` / `Paid` |
| Modify `src/Models/Transaction.php` | fillable and cast for the two columns |
| Modify `src/Drivers/Heropayment/HeropaymentClient.php` | `getBalance()`, `createWithdrawal()` |
| Create `src/Contracts/SendsPayouts.php` | the generic payout contract |
| Create `src/DataObjects/PayoutRequest.php`, `PayoutPreflight.php`, `PayoutReceipt.php` | payout value objects |
| Create `src/Exceptions/PayoutRejectedException.php`, `PayoutOutcomeUnknownException.php` | the two send-failure classes |
| Modify `src/Drivers/Heropayment/HeropaymentQuoteService.php` | withdrawal rate, withdrawal network fees, minimum withdrawal |
| Create `src/Drivers/Heropayment/HeropaymentPayoutService.php` | preflight, send, lookup |
| Modify `src/Drivers/Heropayment/HeropaymentAdapter.php` | payout status mapping, `payoutReceipt()` |
| Modify `src/Drivers/Heropayment/HeropaymentProvider.php` | implements `SendsPayouts` |
| Modify `src/Logging/PaymentLogger.php`, `src/Logging/TransactionLogger.php` | payout log lines |
| Create `src/Events/WithdrawalPayoutSent.php`, `WithdrawalPayoutFailed.php`, `PayoutFundsInsufficient.php` | payout events |
| Modify `src/Withdrawals/WithdrawalWorkflow.php` | `sendPayout()`, `applyPayoutUpdate()`, `syncPayout()`, `cancel()`/`markPaid()` changes |
| Create `src/Jobs/ProcessPayoutWebhook.php` | withdrawal-callback job |
| Modify `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php` | route withdrawal callbacks to the new job |
| Modify `config/cashier-core.php`, `.claude/skills/heropayments/references/quirks.md`, `.claude/skills/heropayments/SKILL.md` | docs |
| Create `tests/Fixtures/HeropaymentPayoutApi.php` | scripted Heropayments API for payout tests |
| Tests | `tests/Feature/PayoutColumnsTest.php`, `tests/Unit/Drivers/HeropaymentClientTest.php`, `tests/Unit/Drivers/HeropaymentPayoutServiceTest.php`, `tests/Feature/HeropaymentPayoutWorkflowTest.php`, `tests/Feature/ProcessPayoutWebhookTest.php`, additions to `HeropaymentAdapterTest.php`, `HeropaymentQuoteServiceTest.php` and `HeropaymentWebhookTest.php` |

---

### Task 1: Payout columns and `PayoutState`

**Files:**
- Create: `database/migrations/2026_09_29_000001_add_payout_columns_to_transactions_table.php`
- Create: `src/Enums/PayoutState.php`
- Modify: `src/Models/Transaction.php` (the `$fillable` array and `casts()`)
- Test: `tests/Feature/PayoutColumnsTest.php`

**Interfaces:**
- Produces: `Asciisd\CashierCore\Enums\PayoutState` (string-backed: `sent`, `unknown`, `failed`, `paid`) with `isInFlight(): bool`. `Transaction::$payout_state` is `?PayoutState` and `Transaction::$payout_reference` is `?string`, and both are fillable.

- [ ] **Step 1: Write the failing test**

`tests/Feature/PayoutColumnsTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Support\Facades\Schema;

it('adds nullable payout columns to transactions', function () {
    expect(Schema::hasColumns('transactions', ['payout_state', 'payout_reference']))->toBeTrue();
});

it('casts payout_state to PayoutState and defaults both columns to null', function () {
    $transaction = Transaction::query()->create([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-COLUMNS',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
    ]);

    expect($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->payout_reference)->toBeNull();

    $transaction->update(['payout_state' => PayoutState::Sent, 'payout_reference' => 'hero-1']);

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->payout_reference)->toBe('hero-1');
});

it('treats only sent and unknown as in flight', function () {
    expect(PayoutState::Sent->isInFlight())->toBeTrue()
        ->and(PayoutState::Unknown->isInFlight())->toBeTrue()
        ->and(PayoutState::Failed->isInFlight())->toBeFalse()
        ->and(PayoutState::Paid->isInFlight())->toBeFalse();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PayoutColumnsTest.php`
Expected: FAIL. `hasColumns` is false, and `PayoutState` is not found.

- [ ] **Step 3: Create the enum**

`src/Enums/PayoutState.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Enums;

/**
 * The PSP side of a withdrawal payout — ops-facing, kept apart from the
 * customer-facing `status`, which stays Processing until the payout is paid.
 *
 * Null on the row means the payout was never sent.
 */
enum PayoutState: string
{
    /** The PSP accepted the payout and has not finished it yet. */
    case Sent = 'sent';

    /** The send timed out or errored: the payout may or may not exist. */
    case Unknown = 'unknown';

    /** The PSP refused or failed the payout; nothing is in flight. */
    case Failed = 'failed';

    /** The PSP reported the payout finished. Final. */
    case Paid = 'paid';

    /**
     * Whether money may be on its way — the states in which the MT5 debit
     * must not be refunded.
     */
    public function isInFlight(): bool
    {
        return $this === self::Sent || $this === self::Unknown;
    }
}
```

- [ ] **Step 4: Create the migration**

`database/migrations/2026_09_29_000001_add_payout_columns_to_transactions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PSP side of a withdrawal payout. `payout_state` is indexed so ops can
 * list payouts that failed or whose outcome is unknown; `status` stays the
 * customer-facing lifecycle.
 *
 * Guarded like the create migration: hosts that own their transactions table
 * and already added these columns are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'payout_state')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->string('payout_state')->nullable()->index();          // PayoutState
            $table->string('payout_reference')->nullable();               // PSP payment id
        });
    }

    public function down(): void
    {
        $table = config('cashier-core.database.tables.transactions', 'transactions');

        if (! Schema::hasColumn($table, 'payout_state')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropIndex(['payout_state']);
            $table->dropColumn(['payout_state', 'payout_reference']);
        });
    }
};
```

- [ ] **Step 5: Register the columns on the model**

In `src/Models/Transaction.php`, add `use Asciisd\CashierCore\Enums\PayoutState;` to the imports. Then add the two columns to the last line of `$fillable`:

```php
        'error_code', 'error_message', 'processed_at', 'failed_at',
        'payout_state', 'payout_reference',
    ];
```

In `casts()`, after `'settlement_mode' => SettlementMode::class,`:

```php
            'payout_state' => PayoutState::class,
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/PayoutColumnsTest.php`, then `vendor/bin/pest`
Expected: PASS, with the full suite still green.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_29_000001_add_payout_columns_to_transactions_table.php src/Enums/PayoutState.php src/Models/Transaction.php tests/Feature/PayoutColumnsTest.php
git commit -m "feat(withdrawals): add payout_state and payout_reference columns

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: `HeropaymentClient` balance and withdrawal calls

**Files:**
- Modify: `src/Drivers/Heropayment/HeropaymentClient.php`
- Test: `tests/Unit/Drivers/HeropaymentClientTest.php` (new)

**Interfaces:**
- Produces:
  - `HeropaymentClient::getBalance(): ?array`. Returns `{walletAddress, walletCurrency, balance}`, or `null` on any non-2xx.
  - `HeropaymentClient::createWithdrawal(array $body): \Illuminate\Http\Client\Response`. It is signed and **never throws on an HTTP status**. On a timeout or connection failure it throws `Illuminate\Http\Client\ConnectionException`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/Drivers/HeropaymentClientTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function hpClient(): HeropaymentClient
{
    return new HeropaymentClient('https://hero.test', 'test-key', 'test-secret');
}

it('reads the balance, signing the empty query string', function () {
    Http::fake(['hero.test/v2/balance' => Http::response([
        'walletAddress' => 'TMerchant',
        'walletCurrency' => 'usdttrc20',
        'balance' => '333.80103',
    ])]);

    expect(hpClient()->getBalance())->toBe([
        'walletAddress' => 'TMerchant',
        'walletCurrency' => 'usdttrc20',
        'balance' => '333.80103',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hero.test/v2/balance'
        && $request->header('x-api-key')[0] === 'test-key'
        && $request->header('x-api-sign')[0] === hash_hmac('sha512', '', 'test-secret'));
});

it('returns null when the balance lookup fails', function () {
    Http::fake(['hero.test/v2/balance' => Http::response([], 500)]);

    expect(hpClient()->getBalance())->toBeNull();
});

it('signs the withdrawal body exactly as sent, without escaping slashes or unicode', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response(['id' => 'hero-wd-1'])]);

    hpClient()->createWithdrawal([
        'customerEmail' => 'zoë@example.com',
        'callbackUrl' => 'https://members.example.com/api/webhooks/heropayment',
        'priceAmount' => '10.00',
    ]);

    Http::assertSent(function (Request $request) {
        $raw = $request->body();

        return $request->url() === 'https://hero.test/v2/withdrawal'
            && str_contains($raw, 'https://members.example.com/api/webhooks/heropayment')
            && str_contains($raw, 'zoë@example.com')
            && $request->header('x-api-sign')[0] === hash_hmac('sha512', $raw, 'test-secret');
    });
});

it('returns the response instead of throwing on a 4xx or 5xx', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    $response = hpClient()->createWithdrawal(['priceAmount' => '10.00']);

    expect($response->status())->toBe(400)
        ->and($response->json('message'))->toBe('Payout address not valid');
});

it('sends a withdrawal exactly once, with no automatic retry', function () {
    Http::fake(['hero.test/v2/withdrawal' => Http::response([], 500)]);

    hpClient()->createWithdrawal(['priceAmount' => '10.00']);

    Http::assertSentCount(1);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentClientTest.php`
Expected: FAIL with "Call to undefined method … getBalance()".

- [ ] **Step 3: Implement**

In `src/Drivers/Heropayment/HeropaymentClient.php`, add `use Illuminate\Http\Client\Response;` to the imports. Then add these two methods after `getPaymentByOrderId()`:

```php
    /**
     * The merchant balance: one wallet, e.g.
     * `{walletAddress, walletCurrency: "usdttrc20", balance: "333.80103"}`.
     * Null on any non-2xx — callers must treat that as "unknown", never "enough".
     *
     * @return array<string, mixed>|null
     */
    public function getBalance(): ?array
    {
        return $this->getSigned('/v2/balance');
    }

    /**
     * Create a withdrawal (payout).
     *
     * Returns the raw response rather than throwing on an HTTP status: the
     * caller must tell a clean 4xx rejection (nothing created) from a 5xx or
     * timeout (it may have been created). Never retried here — a replayed POST
     * could pay the customer twice. A timeout surfaces as ConnectionException.
     *
     * @param  array<string, mixed>  $body
     */
    public function createWithdrawal(array $body): Response
    {
        $json = $this->encodePayload($body);

        return PspHttp::client()->withHeaders([
            'x-api-key' => $this->apiKey,
            'x-api-sign' => $this->sign($json),
        ])
            ->withBody($json, 'application/json')
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl.'/v2/withdrawal');
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentClientTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Drivers/Heropayment/HeropaymentClient.php tests/Unit/Drivers/HeropaymentClientTest.php
git commit -m "feat(heropayment): add balance and withdrawal client calls

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Payout contract, value objects, quote extensions and preflight

**Files:**
- Create: `src/Contracts/SendsPayouts.php`
- Create: `src/DataObjects/PayoutRequest.php`, `src/DataObjects/PayoutPreflight.php`, `src/DataObjects/PayoutReceipt.php`
- Create: `src/Exceptions/PayoutRejectedException.php`, `src/Exceptions/PayoutOutcomeUnknownException.php`
- Modify: `src/Drivers/Heropayment/HeropaymentQuoteService.php`
- Create: `src/Drivers/Heropayment/HeropaymentPayoutService.php` (preflight and guards only in this task; `send()`/`lookup()`/`parsePayoutWebhook()` throw `LogicException` until Task 4)
- Create: `tests/Fixtures/HeropaymentPayoutApi.php`
- Test: `tests/Unit/Drivers/HeropaymentPayoutServiceTest.php` (new), plus additions to `tests/Unit/Drivers/HeropaymentQuoteServiceTest.php`

**Interfaces:**
- Produces:
  - `SendsPayouts`:
    - `preflight(PayoutRequest): PayoutPreflight`
    - `send(PayoutRequest): PayoutReceipt`
    - `lookup(string $externalOrderId): ?PayoutReceipt`
    - `parsePayoutWebhook(array $payload): PayoutReceipt`
  - `PayoutRequest(string $externalOrderId, string $customerId, string $amount, string $currency, string $payoutCurrency, string $payoutAddress, ?string $payoutExtraId = null, ?string $customerEmail = null)`
  - `PayoutPreflight`:
    - public props `bool $ok`, `?string $balance`, `?string $required`, `?string $walletCurrency`, `?string $reason`, `?string $message`
    - constants `INSUFFICIENT_FUNDS`, `BELOW_MINIMUM`, `BALANCE_UNAVAILABLE`, `QUOTE_UNAVAILABLE`
    - static `passed(string $balance, string $required, string $walletCurrency)` and `refused(string $reason, string $message, ?string $balance = null, ?string $required = null, ?string $walletCurrency = null)`
  - `PayoutReceipt(?string $reference, string $rawStatus, PayoutState $state, array $payload = [], ?string $error = null)`
  - `PayoutRejectedException` and `PayoutOutcomeUnknownException`, both `extends PaymentProcessingException`
  - `HeropaymentQuoteService`:
    - `rate(string $from, string $to, string $transactionType = 'deposit'): ?float`
    - `withdrawalNetworkFees(): array<string, float>`
    - `minWithdrawal(string $currency): ?float`
  - `HeropaymentPayoutService::__construct(array $config, ?HeropaymentClient $client = null, ?HeropaymentQuoteService $quotes = null)`
  - `Tests\Fixtures\HeropaymentPayoutApi`:
    - `CONFIG`
    - `configure(array $overrides = []): array`
    - `fake(array $options = []): void`
    - `withdrawal(array $overrides = []): array`

- [ ] **Step 1: Create the value objects, exceptions and contract**

These have no behavior of their own; the preflight tests exercise them.

`src/Contracts/SendsPayouts.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Contracts;

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;

/**
 * A connection that can push a withdrawal payout to the customer.
 *
 * The workflow owns locking, persistence and events; implementations own the
 * PSP contract. The two send exceptions are the whole point of the interface:
 * a rejection is safe to correct and resend, an unknown outcome is not.
 */
interface SendsPayouts
{
    /**
     * Whether the payout can go right now: funds and minimums. A failed lookup
     * refuses — it never passes.
     *
     * @throws PaymentProcessingException on configuration or input errors
     */
    public function preflight(PayoutRequest $request): PayoutPreflight;

    /**
     * @throws PayoutRejectedException the PSP refused; nothing was created
     * @throws PayoutOutcomeUnknownException the payout may exist — look it up before resending
     * @throws PaymentProcessingException on configuration or input errors
     */
    public function send(PayoutRequest $request): PayoutReceipt;

    /**
     * The payout for our order id, or null when not found or the lookup failed.
     */
    public function lookup(string $externalOrderId): ?PayoutReceipt;

    /**
     * @param  array<string, mixed>  $payload  a verified payout callback
     */
    public function parsePayoutWebhook(array $payload): PayoutReceipt;
}
```

`src/DataObjects/PayoutRequest.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * One payout attempt, as the PSP needs it. Built by the workflow from a
 * Processing withdrawal row.
 */
readonly class PayoutRequest
{
    /**
     * @param  string  $externalOrderId  the attempt's order id (the row's provider_transaction_id)
     * @param  string  $customerId  MT5 login, falling back to the user id
     * @param  string  $amount  decimal string in $currency, e.g. "100.00"
     * @param  string  $currency  ISO code of the withdrawal, e.g. "USD"
     * @param  string  $payoutCurrency  PSP ticker the customer receives, e.g. "usdttrc20"
     */
    public function __construct(
        public string $externalOrderId,
        public string $customerId,
        public string $amount,
        public string $currency,
        public string $payoutCurrency,
        public string $payoutAddress,
        public ?string $payoutExtraId = null,
        public ?string $customerEmail = null,
    ) {}
}
```

`src/DataObjects/PayoutPreflight.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * The answer to "can this payout go now?". Amounts are decimal strings in the
 * PSP wallet currency.
 */
readonly class PayoutPreflight
{
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';

    public const BELOW_MINIMUM = 'below_minimum';

    public const BALANCE_UNAVAILABLE = 'balance_unavailable';

    public const QUOTE_UNAVAILABLE = 'quote_unavailable';

    public function __construct(
        public bool $ok,
        public ?string $balance = null,
        public ?string $required = null,
        public ?string $walletCurrency = null,
        public ?string $reason = null,
        public ?string $message = null,
    ) {}

    public static function passed(string $balance, string $required, string $walletCurrency): self
    {
        return new self(true, $balance, $required, $walletCurrency);
    }

    public static function refused(
        string $reason,
        string $message,
        ?string $balance = null,
        ?string $required = null,
        ?string $walletCurrency = null,
    ): self {
        return new self(false, $balance, $required, $walletCurrency, $reason, $message);
    }
}
```

`src/DataObjects/PayoutReceipt.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

use Asciisd\CashierCore\Enums\PayoutState;

/**
 * What the PSP says about a payout — from a create response, a lookup, or a
 * callback.
 */
readonly class PayoutReceipt
{
    /**
     * @param  string|null  $reference  the PSP's payment id
     * @param  array<string, mixed>  $payload  the raw PSP payload
     * @param  string|null  $error  the failure reason when $state is Failed
     */
    public function __construct(
        public ?string $reference,
        public string $rawStatus,
        public PayoutState $state,
        public array $payload = [],
        public ?string $error = null,
    ) {}
}
```

`src/Exceptions/PayoutRejectedException.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

/**
 * The PSP validated and refused the payout — nothing was created, so it is
 * safe to correct the input and send again.
 */
class PayoutRejectedException extends PaymentProcessingException {}
```

`src/Exceptions/PayoutOutcomeUnknownException.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Exceptions;

/**
 * The send timed out, errored server-side, or came back without an id: the
 * payout may exist. Look it up before any resend.
 */
class PayoutOutcomeUnknownException extends PaymentProcessingException {}
```

- [ ] **Step 2: Write the failing quote-service tests**

Append to `tests/Unit/Drivers/HeropaymentQuoteServiceTest.php`. The file's `beforeEach` and its `fakeHeroLookups()` and `heroQuoteService()` helpers already exist, and the fake's network fees include a `usdttrc20` withdrawal row of `9.99`:

```php
it('reads withdrawal network fees separately from deposit fees', function () {
    fakeHeroLookups();

    expect(heroQuoteService()->withdrawalNetworkFees())->toBe(['usdttrc20' => 9.99]);
});

it('reads the minimum withdrawal for a ticker', function () {
    fakeHeroLookups(['hero.test/v2/min-amount*' => Http::response([
        'minDeposit' => 5.0,
        'minWithdrawal' => 12.5,
        'currency' => 'usdttrc20',
    ])]);

    expect(heroQuoteService()->minWithdrawal('usdttrc20'))->toBe(12.5);
});

it('asks for a withdrawal rate and caches it apart from the deposit rate', function () {
    fakeHeroLookups();

    heroQuoteService()->rate('usd', 'usdttrc20');
    heroQuoteService()->rate('usd', 'usdttrc20', 'withdrawal');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'transactionType=withdrawal'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'transactionType=deposit'));
});
```

- [ ] **Step 3: Run them to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentQuoteServiceTest.php`
Expected: FAIL with "Call to undefined method … withdrawalNetworkFees()".

- [ ] **Step 4: Extend the quote service**

In `src/Drivers/Heropayment/HeropaymentQuoteService.php`:

Add a constant after `private const DEPOSIT = 'deposit';`:

```php
    private const WITHDRAWAL = 'withdrawal';
```

Replace `rate()`:

```php
    /**
     * Spot rate: units of $to per 1 unit of $from. Null when unavailable.
     *
     * Heropayments quotes deposits and withdrawals separately, so the type is
     * part of both the request and the cache key.
     */
    public function rate(string $from, string $to, string $transactionType = self::DEPOSIT): ?float
    {
        $payload = $this->remember(
            "rate:{$transactionType}:{$from}:{$to}",
            $this->quoteTtl(),
            fn () => $this->client->getRate($from, $to, $transactionType),
        );

        return isset($payload['rate']) ? (float) $payload['rate'] : null;
    }
```

After `minDeposit()`, add:

```php
    /**
     * Minimum withdrawal for a ticker, in that ticker's own units. Null when unavailable.
     */
    public function minWithdrawal(string $currency): ?float
    {
        $payload = $this->remember(
            "min-amount:{$currency}",
            $this->referenceTtl(),
            fn () => $this->client->getMinAmount(currency: $currency),
        );

        return isset($payload['minWithdrawal']) ? (float) $payload['minWithdrawal'] : null;
    }
```

Replace `depositNetworkFees()` with these three methods:

```php
    /**
     * Deposit network fees keyed by lowercased ticker, in native currency units.
     *
     * @return array<string, float>
     */
    public function depositNetworkFees(): array
    {
        return $this->networkFees(self::DEPOSIT);
    }

    /**
     * Withdrawal network fees keyed by lowercased ticker, in native currency
     * units (see HeropaymentClient::getNetworkFees() on why not USDT).
     *
     * @return array<string, float>
     */
    public function withdrawalNetworkFees(): array
    {
        return $this->networkFees(self::WITHDRAWAL);
    }

    /**
     * @return array<string, float>
     */
    private function networkFees(string $type): array
    {
        $rows = $this->remember('network-fees', $this->referenceTtl(), fn () => $this->client->getNetworkFees()) ?? [];

        $fees = [];

        foreach ($rows as $row) {
            if (($row['type'] ?? null) !== $type) {
                continue;
            }

            $fees[strtolower((string) ($row['ticker'] ?? ''))] = (float) ($row['networkfee'] ?? 0);
        }

        return $fees;
    }
```

- [ ] **Step 5: Run the quote tests**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentQuoteServiceTest.php`
Expected: PASS, including every existing test.

- [ ] **Step 6: Create the test fixture**

`tests/Fixtures/HeropaymentPayoutApi.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Tests\Fixtures;

use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentProvider;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A scripted Heropayments V2 API for payout tests. Every endpoint the payout
 * path touches answers from the options, so a test states only what differs.
 *
 * Defaults: balance 500.00 usdttrc20, every rate 1.0, minimum 5, withdrawal
 * network fee 1.0 — so a 100.00 USD payout needs
 * (100 × 1.006 + 1) × 1.02 = 103.632 in the wallet.
 */
final class HeropaymentPayoutApi
{
    public const CONFIG = [
        'driver' => 'heropayment',
        'class' => HeropaymentProvider::class,
        'base_url' => 'https://hero.test',
        'api_key' => 'test-key',
        'api_secret' => 'test-secret',
        'webhook_url' => 'https://members.example.com/api/webhooks/heropayment',
        'withdrawals_enabled' => true,
        'withdrawal_max_amount' => '1000',
        'fee_percent' => '0.6',
        'balance_buffer_percent' => '2',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function configure(array $overrides = []): array
    {
        $config = array_merge(self::CONFIG, $overrides);

        config()->set('cashier-core.connections.heropayment', $config);

        return $config;
    }

    /**
     * `withdrawal` and `lookup` take an Http::response() or a Closure(Request),
     * which may throw (e.g. ConnectionException for a timeout). `fee_ticker` is
     * the one coin the withdrawal network-fee row is listed for.
     *
     * @param  array{balance?: ?string, wallet?: string, rate?: ?string, min?: float, fee?: string, fee_ticker?: string, withdrawal?: mixed, lookup?: mixed}  $options
     */
    public static function fake(array $options = []): void
    {
        $o = $options + [
            'balance' => '500.00',
            'wallet' => 'usdttrc20',
            'rate' => '1.0',
            'min' => 5.0,
            'fee' => '1.0',
            'fee_ticker' => 'usdttrc20',
            'withdrawal' => null,
            'lookup' => null,
        ];

        Http::fake(function (Request $request) use ($o) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/v2/withdrawal') {
                $answer = $o['withdrawal'] ?? Http::response(self::withdrawal());

                return $answer instanceof Closure ? $answer($request) : $answer;
            }

            if (str_starts_with($path, '/v2/payments/order/')) {
                $answer = $o['lookup'] ?? Http::response(['message' => 'Not found'], 404);

                return $answer instanceof Closure ? $answer($request) : $answer;
            }

            return match ($path) {
                '/v2/balance' => $o['balance'] === null
                    ? Http::response([], 500)
                    : Http::response(['walletAddress' => 'TMerchant', 'walletCurrency' => $o['wallet'], 'balance' => $o['balance']]),
                '/v2/rate' => $o['rate'] === null
                    ? Http::response([], 500)
                    : Http::response(['rate' => $o['rate']]),
                '/v2/min-amount' => Http::response(['minDeposit' => $o['min'], 'minWithdrawal' => $o['min'], 'currency' => 'usdttrc20']),
                '/v2/network-fees' => Http::response([
                    ['networkfee' => '9.0', 'ticker' => 'usdttrc20', 'type' => 'deposit'],
                    ['networkfee' => $o['fee'], 'ticker' => $o['fee_ticker'], 'type' => 'withdrawal'],
                ]),
                default => Http::response([], 404),
            };
        });
    }

    /**
     * A V2 create-withdrawal response / callback body (v2.md, callbacks.md).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function withdrawal(array $overrides = []): array
    {
        return array_merge([
            'id' => 'hero-wd-1',
            'status' => 'waiting',
            'invoice' => [
                'id' => 'hero-inv-1',
                'customerId' => '70001',
                'orderId' => null,
                'priceCurrency' => 'usd',
                'priceAmount' => 100,
            ],
            'externalOrderId' => 'WD-01TEST',
            'sequence' => 'original',
            'transactionType' => 'withdrawal',
            'payCurrency' => 'usdttrc20',
            'payAmount' => 101.6,
            'payHash' => null,
            'paidAmount' => 0,
            'outcomeAmount' => 0,
            'outcomeAddress' => 'TXyzCustomer',
            'outcomeCurrency' => 'usdttrc20',
            'feePercent' => 0.6,
            'networkFee' => 1,
            'clientAmount' => 100,
            'merchantAmountUsdt' => 101.6,
            'fiat' => true,
        ], $overrides);
    }
}
```

- [ ] **Step 7: Write the failing preflight tests**

`tests/Unit/Drivers/HeropaymentPayoutServiceTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Drivers\Heropayment\HeropaymentPayoutService;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

function hpService(array $overrides = []): HeropaymentPayoutService
{
    return new HeropaymentPayoutService(array_merge(HeropaymentPayoutApi::CONFIG, $overrides));
}

function hpRequest(array $overrides = []): PayoutRequest
{
    return new PayoutRequest(...array_merge([
        'externalOrderId' => 'WD-01TEST',
        'customerId' => '70001',
        'amount' => '100.00',
        'currency' => 'USD',
        'payoutCurrency' => 'usdttrc20',
        'payoutAddress' => 'TXyzCustomer',
    ], $overrides));
}

// --- fail-closed configuration -------------------------------------------

it('refuses when withdrawals are disabled', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawals_enabled' => false])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'withdrawals are disabled');
});

it('fails closed on an invalid cap', function (mixed $cap) {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawal_max_amount' => $cap])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class);
})->with([
    'missing' => [null],
    'empty' => [''],
    'zero' => ['0'],
    'non-numeric' => ['lots'],
    'scientific' => ['2.5e1'],
]);

it('refuses an amount over the cap', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawal_max_amount' => '50'])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'exceeds the configured cap of 50');
});

it('refuses when fee_percent is not configured', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['fee_percent' => null])->preflight(hpRequest()))
        ->toThrow(PaymentProcessingException::class, 'fee_percent');
});

it('refuses a request with no payout address', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService()->preflight(hpRequest(['payoutAddress' => '  '])))
        ->toThrow(PaymentProcessingException::class, 'payout address');
});

// --- the balance check ----------------------------------------------------

it('passes when the balance exactly covers amount, fee, network fee and buffer', function () {
    // (100 × 1.006 + 1.0) × 1.02 = 103.632
    HeropaymentPayoutApi::fake(['balance' => '103.632']);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeTrue()
        ->and($preflight->walletCurrency)->toBe('usdttrc20')
        ->and(bccomp($preflight->required, '103.632', 8))->toBe(0);
});

it('refuses with the numbers when the balance is short', function () {
    HeropaymentPayoutApi::fake(['balance' => '103.63199999']);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeFalse()
        ->and($preflight->reason)->toBe(PayoutPreflight::INSUFFICIENT_FUNDS)
        ->and($preflight->balance)->toBe('103.63199999')
        ->and($preflight->message)->toContain('103.63199999')->toContain('103.632');
});

it('converts a network fee quoted in the payout coin into the wallet currency', function (string $balance, bool $ok) {
    // Wallet usdttrc20, payout btc, every rate 2.0, btc network fee 1.0 (native units):
    // base 100 × 2 = 200; × 1.006 = 201.2; + 1.0 btc × 2 = 203.2; × 1.02 = 207.264
    HeropaymentPayoutApi::fake(['balance' => $balance, 'rate' => '2.0', 'fee_ticker' => 'btc']);

    expect(hpService()->preflight(hpRequest(['payoutCurrency' => 'btc']))->ok)->toBe($ok);
})->with([
    'exactly enough' => ['207.264', true],
    'just short' => ['207.26', false],
]);

it('refuses a payout coin with no withdrawal network fee instead of assuming zero', function () {
    // The fee row is for usdttrc20 only; a btc payout has none.
    HeropaymentPayoutApi::fake();

    expect(hpService()->preflight(hpRequest(['payoutCurrency' => 'btc']))->reason)
        ->toBe(PayoutPreflight::QUOTE_UNAVAILABLE);
});

it('refuses when the amount is below the minimum withdrawal', function () {
    HeropaymentPayoutApi::fake(['min' => 150.0]);

    $preflight = hpService()->preflight(hpRequest());

    expect($preflight->ok)->toBeFalse()
        ->and($preflight->reason)->toBe(PayoutPreflight::BELOW_MINIMUM);
});

// --- Review Focus 3: outages refuse, never pass -----------------------------

it('refuses when the balance is unavailable', function () {
    HeropaymentPayoutApi::fake(['balance' => null]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::BALANCE_UNAVAILABLE);
});

it('refuses when the withdrawal rate is unavailable', function () {
    HeropaymentPayoutApi::fake(['rate' => null]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::QUOTE_UNAVAILABLE);
});

// --- Review Focus 4: malformed balance strings ------------------------------

it('refuses a malformed balance string instead of crashing', function (string $balance) {
    HeropaymentPayoutApi::fake(['balance' => $balance]);

    expect(hpService()->preflight(hpRequest())->reason)->toBe(PayoutPreflight::BALANCE_UNAVAILABLE);
})->with([
    'thousands separator' => ['1,234.50'],
    'empty' => [''],
    'scientific' => ['2.5e1'],
    'negative' => ['-5'],
]);
```

- [ ] **Step 8: Run to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentPayoutServiceTest.php`
Expected: FAIL with "Class … HeropaymentPayoutService not found".

- [ ] **Step 9: Implement the service (preflight and guards)**

`src/Drivers/Heropayment/HeropaymentPayoutService.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Heropayment;

use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use LogicException;

/**
 * Heropayments V2 withdrawals (payouts).
 *
 * Three facts shape this class. There is no sandbox, so the first live send
 * moves real money — hence the enable flag and the fail-closed cap. The
 * deduction from our balance is amount + processing fee + network fee, and
 * the fee percentage is only revealed after the payout exists — hence the
 * configured fee_percent and a safety buffer. And `externalOrderId` must be
 * unique, which is the idempotency key a retried send relies on.
 */
final class HeropaymentPayoutService implements SendsPayouts
{
    private const WITHDRAWAL = 'withdrawal';

    /**
     * Strict decimal: bcmath 8.3+ throws a ValueError on "2.5e1", padded or
     * signed strings that is_numeric() accepts. Applied after trim().
     */
    private const NUMERIC_PATTERN = '/^\d+(\.\d+)?$/';

    private const SCALE = 8;

    private const DEFAULT_BUFFER_PERCENT = '2';

    private HeropaymentClient $client;

    private HeropaymentQuoteService $quotes;

    /**
     * @param  array<string, mixed>  $config  a `cashier-core.connections.heropayment` array
     */
    public function __construct(
        private readonly array $config,
        ?HeropaymentClient $client = null,
        ?HeropaymentQuoteService $quotes = null,
    ) {
        $this->client = $client ?? HeropaymentClient::fromConfig($config);
        $this->quotes = $quotes ?? new HeropaymentQuoteService($this->client, $config);
    }

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        $amount = $this->guard($request);
        $currency = strtolower($request->currency);
        $payout = strtolower(trim($request->payoutCurrency));

        // The minimum is in the payout coin's own units.
        $minimum = $this->quotes->minWithdrawal($payout);
        $toPayout = $this->quotes->rate($currency, $payout, self::WITHDRAWAL);

        if ($minimum === null || $toPayout === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::QUOTE_UNAVAILABLE,
                "Heropayments has no withdrawal quote for {$payout} right now.",
            );
        }

        $inPayout = bcmul($amount, self::decimal($toPayout), self::SCALE);

        if (bccomp($inPayout, self::decimal($minimum), self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::BELOW_MINIMUM,
                sprintf('%s %s is below the Heropayments minimum withdrawal of %s %s.', $inPayout, $payout, self::decimal($minimum), $payout),
            );
        }

        // Live, never cached: a stale balance defeats the check.
        $wallet = $this->client->getBalance();
        $balance = trim((string) ($wallet['balance'] ?? ''));
        $walletCurrency = strtolower(trim((string) ($wallet['walletCurrency'] ?? '')));

        if (! preg_match(self::NUMERIC_PATTERN, $balance) || $walletCurrency === '') {
            return PayoutPreflight::refused(
                PayoutPreflight::BALANCE_UNAVAILABLE,
                'The Heropayments balance could not be read. Nothing was sent.',
            );
        }

        $required = $this->required($amount, $currency, $payout, $walletCurrency);

        if ($required === null) {
            return PayoutPreflight::refused(
                PayoutPreflight::QUOTE_UNAVAILABLE,
                "Heropayments has no withdrawal rate or network fee for {$payout} right now.",
            );
        }

        if (bccomp($balance, $required, self::SCALE) < 0) {
            return PayoutPreflight::refused(
                PayoutPreflight::INSUFFICIENT_FUNDS,
                sprintf('Heropayments balance %s %s; this payout needs ~%s %s.', $balance, $walletCurrency, $required, $walletCurrency),
                $balance,
                $required,
                $walletCurrency,
            );
        }

        return PayoutPreflight::passed($balance, $required, $walletCurrency);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        throw new LogicException('Implemented in Task 4.');
    }

    public function lookup(string $externalOrderId): ?PayoutReceipt
    {
        throw new LogicException('Implemented in Task 4.');
    }

    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        throw new LogicException('Implemented in Task 4.');
    }

    /**
     * What Heropayments will deduct, in the wallet currency:
     * (amount × rate × (1 + fee%) + networkFee × payout→wallet rate) × (1 + buffer%).
     *
     * Network fees are native payout-coin units (HeropaymentClient::getNetworkFees()),
     * so they are converted — skipped when the payout coin is the wallet coin.
     * Null when any input is unavailable: an unknown fee is never assumed zero.
     */
    private function required(string $amount, string $currency, string $payout, string $walletCurrency): ?string
    {
        $toWallet = $this->quotes->rate($currency, $walletCurrency, self::WITHDRAWAL);
        $networkFee = $this->quotes->withdrawalNetworkFees()[$payout] ?? null;
        $feeToWallet = $payout === $walletCurrency ? 1.0 : $this->quotes->rate($payout, $walletCurrency, self::WITHDRAWAL);

        if ($toWallet === null || $networkFee === null || $feeToWallet === null) {
            return null;
        }

        $base = bcmul($amount, self::decimal($toWallet), self::SCALE);
        $withFee = bcmul($base, self::onePlusPercent($this->feePercent()), self::SCALE);
        $withNetwork = bcadd($withFee, bcmul(self::decimal($networkFee), self::decimal($feeToWallet), self::SCALE), self::SCALE);

        return bcmul($withNetwork, self::onePlusPercent($this->bufferPercent()), self::SCALE);
    }

    /**
     * Fail closed on configuration and input; returns the trimmed amount.
     *
     * @throws PaymentProcessingException
     */
    private function guard(PayoutRequest $request): string
    {
        if (! filter_var($this->config['withdrawals_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals are disabled. Set withdrawals_enabled once the flow is proven.',
            );
        }

        // Missing, empty, zero or malformed is a configuration error — never "no cap".
        $cap = trim((string) ($this->config['withdrawal_max_amount'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $cap)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals are enabled but no valid withdrawal_max_amount is configured. Refusing to send.',
            );
        }

        if (bccomp($cap, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException(
                sprintf('Heropayment withdrawal cap is configured as %s, which blocks all withdrawals.', $cap),
            );
        }

        $this->feePercent();

        $amount = trim($request->amount);

        if (! preg_match(self::NUMERIC_PATTERN, $amount) || bccomp($amount, '0', self::SCALE) <= 0) {
            throw new PaymentProcessingException('Heropayment withdrawal amount must be a positive number.');
        }

        if (bccomp($amount, $cap, self::SCALE) > 0) {
            throw new PaymentProcessingException(
                sprintf('Withdrawal of %s exceeds the configured cap of %s.', $amount, $cap),
            );
        }

        if (trim($request->payoutAddress) === '') {
            throw new PaymentProcessingException('Heropayment withdrawal requires a payout address.');
        }

        if (trim($request->payoutCurrency) === '') {
            throw new PaymentProcessingException('Heropayment withdrawal requires a payout currency.');
        }

        return $amount;
    }

    private function feePercent(): string
    {
        $percent = trim((string) ($this->config['fee_percent'] ?? ''));

        if (! preg_match(self::NUMERIC_PATTERN, $percent)) {
            throw new PaymentProcessingException(
                'Heropayment withdrawals need fee_percent configured to estimate the balance a payout requires.',
            );
        }

        return $percent;
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

    private static function decimal(float $value): string
    {
        return sprintf('%.8F', $value);
    }
}
```

- [ ] **Step 10: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentPayoutServiceTest.php tests/Unit/Drivers/HeropaymentQuoteServiceTest.php`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add src/Contracts/SendsPayouts.php src/DataObjects/PayoutRequest.php src/DataObjects/PayoutPreflight.php src/DataObjects/PayoutReceipt.php src/Exceptions/PayoutRejectedException.php src/Exceptions/PayoutOutcomeUnknownException.php src/Drivers/Heropayment/HeropaymentQuoteService.php src/Drivers/Heropayment/HeropaymentPayoutService.php tests/Fixtures/HeropaymentPayoutApi.php tests/Unit/Drivers/HeropaymentPayoutServiceTest.php tests/Unit/Drivers/HeropaymentQuoteServiceTest.php
git commit -m "feat(heropayment): payout contract and balance preflight

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Send, lookup, status mapping; the provider implements `SendsPayouts`

**Files:**
- Modify: `src/Drivers/Heropayment/HeropaymentPayoutService.php` (replace the three `LogicException` stubs)
- Modify: `src/Drivers/Heropayment/HeropaymentAdapter.php`
- Modify: `src/Drivers/Heropayment/HeropaymentProvider.php`
- Modify: `src/Logging/PaymentLogger.php`
- Test: `tests/Unit/Drivers/HeropaymentPayoutServiceTest.php`, `tests/Unit/Drivers/HeropaymentAdapterTest.php`

**Interfaces:**
- Consumes (from Task 3): `PayoutRequest`, `PayoutReceipt`, `PayoutState`, both exceptions, and the `HeropaymentPayoutApi` fixture.
- Produces:
  - `HeropaymentAdapter::mapPayoutStatus(string $status): PayoutState`
  - `HeropaymentAdapter::payoutReceipt(array $payload): PayoutReceipt`
  - `HeropaymentProvider implements SendsPayouts`, with `supports('payout') === true`
  - `PaymentLogger::providerPayoutStatusUnrecognised(string $provider, string $orderId, string $status): void`

- [ ] **Step 1: Write the failing adapter tests**

Append to `tests/Unit/Drivers/HeropaymentAdapterTest.php`, adding `use Asciisd\CashierCore\Enums\PayoutState;` to its imports:

```php
it('maps V2 withdrawal statuses to payout states', function (string $status, PayoutState $state) {
    expect((new \Asciisd\CashierCore\Drivers\Heropayment\HeropaymentAdapter)->mapPayoutStatus($status))->toBe($state);
})->with([
    ['waiting', PayoutState::Sent],
    ['confirming', PayoutState::Sent],
    ['exchanging', PayoutState::Sent],
    ['hold', PayoutState::Sent],
    ['sending', PayoutState::Sent],
    ['finished', PayoutState::Paid],
    ['failed', PayoutState::Failed],
    ['refunded', PayoutState::Failed],
    ['FINISHED', PayoutState::Paid],
    ['something-new', PayoutState::Sent],
]);

it('builds a payout receipt carrying the payment id and a failure reason', function () {
    $receipt = (new \Asciisd\CashierCore\Drivers\Heropayment\HeropaymentAdapter)->payoutReceipt([
        'id' => 'hero-wd-9',
        'status' => 'refunded',
        'externalOrderId' => 'WD-01TEST',
    ]);

    expect($receipt->reference)->toBe('hero-wd-9')
        ->and($receipt->rawStatus)->toBe('refunded')
        ->and($receipt->state)->toBe(PayoutState::Failed)
        ->and($receipt->error)->toBe('Heropayment status: refunded');
});
```

- [ ] **Step 2: Write the failing send tests**

Append to `tests/Unit/Drivers/HeropaymentPayoutServiceTest.php`. Add these imports at the top:

```php
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
```

Tests:

```php
// --- send ------------------------------------------------------------------

it('sends the V2 withdrawal body and returns a receipt', function () {
    HeropaymentPayoutApi::fake();

    $receipt = hpService()->send(hpRequest(['payoutExtraId' => 'memo-1', 'customerEmail' => 'c@example.com']));

    expect($receipt->reference)->toBe('hero-wd-1')
        ->and($receipt->state)->toBe(PayoutState::Sent);

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/v2/withdrawal')) {
            return false;
        }

        return $request->data() === [
            'customerId' => '70001',
            'payoutAddress' => 'TXyzCustomer',
            'payoutCurrency' => 'usdttrc20',
            'priceCurrency' => 'usd',
            'priceAmount' => '100.00',
            'payoutExtraId' => 'memo-1',
            'customerEmail' => 'c@example.com',
            'externalOrderId' => 'WD-01TEST',
            'callbackUrl' => 'https://members.example.com/api/webhooks/heropayment',
            'fiat' => true,
        ];
    });
});

it('omits empty optional fields', function () {
    HeropaymentPayoutApi::fake();

    hpService()->send(hpRequest());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && ! array_key_exists('payoutExtraId', $request->data())
        && ! array_key_exists('customerEmail', $request->data()));
});

it('falls back to the package webhook route under its configured name prefix', function () {
    // The package routes are registered in tests as cashier.webhooks.heropayment.
    // The deposit path looks up `webhooks.heropayment`, which never matches —
    // the payout path must not repeat that.
    HeropaymentPayoutApi::fake();

    hpService(['webhook_url' => null])->send(hpRequest());

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && str_ends_with((string) $request['callbackUrl'], '/api/webhooks/heropayment'));
});

it('refuses to send when no callback URL resolves', function () {
    HeropaymentPayoutApi::fake();
    config()->set('cashier-core.routes.name_prefix', 'not-registered.');

    expect(fn () => hpService(['webhook_url' => null])->send(hpRequest()))
        ->toThrow(\Asciisd\CashierCore\Exceptions\PaymentProcessingException::class, 'callback URL');

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
});

it('treats a 4xx as a clean rejection carrying Heropayments message', function () {
    HeropaymentPayoutApi::fake(['withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    expect(fn () => hpService()->send(hpRequest()))
        ->toThrow(PayoutRejectedException::class, 'Payout address not valid');
});

it('resolves a not-unique rejection by looking the payout up', function () {
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
        'lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'sending'])),
    ]);

    $receipt = hpService()->send(hpRequest());

    expect($receipt->reference)->toBe('hero-wd-1')
        ->and($receipt->rawStatus)->toBe('sending');
});

it('reports unknown when not-unique cannot be looked up', function () {
    HeropaymentPayoutApi::fake([
        'withdrawal' => Http::response(['message' => 'Field externalOrderId for this user is not unique'], 400),
    ]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
});

it('reports unknown on a 5xx, a timeout, or a 2xx without an id', function (mixed $answer) {
    HeropaymentPayoutApi::fake(['withdrawal' => $answer]);

    expect(fn () => hpService()->send(hpRequest()))->toThrow(PayoutOutcomeUnknownException::class);
})->with([
    '500' => fn () => Http::response(['message' => 'internal server error'], 500),
    'timeout' => fn () => fn () => throw new ConnectionException('cURL error 28: timed out'),
    '200 no id' => fn () => Http::response(['status' => 'waiting']),
]);

it('checks config and input before sending', function () {
    HeropaymentPayoutApi::fake();

    expect(fn () => hpService(['withdrawals_enabled' => false])->send(hpRequest()))
        ->toThrow(\Asciisd\CashierCore\Exceptions\PaymentProcessingException::class);

    Http::assertNothingSent();
});

// --- lookup ------------------------------------------------------------------

it('looks a payout up by order id', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'finished']))]);

    $receipt = hpService()->lookup('WD-01TEST');

    expect($receipt?->state)->toBe(PayoutState::Paid);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/payments/order/WD-01TEST'));
});

it('returns null when the lookup finds nothing', function () {
    HeropaymentPayoutApi::fake();

    expect(hpService()->lookup('WD-01TEST'))->toBeNull();
});
```

- [ ] **Step 3: Run them to verify they fail**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentPayoutServiceTest.php tests/Unit/Drivers/HeropaymentAdapterTest.php`
Expected: FAIL with `LogicException: Implemented in Task 4.` and "undefined method mapPayoutStatus".

- [ ] **Step 4: Add the logger line**

In `src/Logging/PaymentLogger.php`, after `providerQuoteLookupFailed()`:

```php
    public static function providerPayoutStatusUnrecognised(string $provider, string $orderId, string $status): void
    {
        self::channel()->warning('Unrecognised payout status; treated as still in flight', [
            'provider' => $provider,
            'order_id' => $orderId,
            'status' => $status,
        ]);
    }
```

- [ ] **Step 5: Add payout mapping to the adapter**

In `src/Drivers/Heropayment/HeropaymentAdapter.php`, add imports for `Asciisd\CashierCore\DataObjects\PayoutReceipt`, `Asciisd\CashierCore\Enums\PayoutState` and `Asciisd\CashierCore\Logging\PaymentLogger`, unless it is already imported. Add a class constant and two methods:

```php
    /**
     * The V2 withdrawal vocabulary (overview.md, "Withdrawal statuses").
     */
    private const PAYOUT_STATUSES = [
        'waiting', 'confirming', 'exchanging', 'hold', 'sending',
        'finished', 'failed', 'refunded',
    ];

    /**
     * Map a V2 withdrawal status. Anything unrecognised is still in flight:
     * that never closes or refunds a withdrawal, so it is the safe default.
     */
    public function mapPayoutStatus(string $status): PayoutState
    {
        return match (strtolower($status)) {
            'finished' => PayoutState::Paid,
            'failed', 'refunded' => PayoutState::Failed,
            default => PayoutState::Sent,
        };
    }

    /**
     * A create response, lookup or callback body as a PayoutReceipt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function payoutReceipt(array $payload): PayoutReceipt
    {
        $raw = strtolower((string) ($payload['status'] ?? ''));
        $state = $this->mapPayoutStatus($raw);

        if (! in_array($raw, self::PAYOUT_STATUSES, true)) {
            PaymentLogger::providerPayoutStatusUnrecognised('heropayment', (string) ($payload['externalOrderId'] ?? ''), $raw);
        }

        return new PayoutReceipt(
            reference: isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null,
            rawStatus: $raw,
            state: $state,
            payload: $payload,
            error: $state === PayoutState::Failed ? (string) ($payload['error'] ?? "Heropayment status: {$raw}") : null,
        );
    }
```

- [ ] **Step 6: Implement send, lookup and parse in the service**

In `src/Drivers/Heropayment/HeropaymentPayoutService.php`:
- Add imports: `Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException`, `Asciisd\CashierCore\Exceptions\PayoutRejectedException`, `Illuminate\Http\Client\ConnectionException`, `Illuminate\Http\Client\Response`, `Illuminate\Support\Facades\Route`.
- Remove `use LogicException;`.
- Add a `private HeropaymentAdapter $adapter;` property, and set `$this->adapter = new HeropaymentAdapter;` at the end of the constructor.
- Replace the three stubs:

```php
    public function send(PayoutRequest $request): PayoutReceipt
    {
        $amount = $this->guard($request);
        $callbackUrl = $this->callbackUrl();

        $body = array_filter([
            'customerId' => $request->customerId,
            'payoutAddress' => trim($request->payoutAddress),
            'payoutCurrency' => strtolower(trim($request->payoutCurrency)),
            'priceCurrency' => strtolower($request->currency),
            'priceAmount' => $amount,
            'payoutExtraId' => $request->payoutExtraId,
            'customerEmail' => $request->customerEmail,
            'externalOrderId' => $request->externalOrderId,
            'callbackUrl' => $callbackUrl,
        ], fn ($value) => $value !== null && $value !== '');

        // The withdrawal is priced in the account's fiat currency.
        $body['fiat'] = true;

        try {
            $response = $this->client->createWithdrawal($body);
        } catch (ConnectionException $e) {
            throw new PayoutOutcomeUnknownException(
                "Heropayments did not answer the withdrawal request ({$e->getMessage()}). "
                .'It may have been created — do not resend before checking its status.',
            );
        }

        if ($response->successful()) {
            $payload = (array) $response->json();

            // The id is the only handle on the payout. A 2xx without one is as
            // ambiguous as a 5xx, not a success.
            if ((string) ($payload['id'] ?? '') === '') {
                throw new PayoutOutcomeUnknownException(
                    'Heropayments accepted the withdrawal but returned no payment id. '
                    .'It may have been created — do not resend before checking its status.',
                );
            }

            return $this->adapter->payoutReceipt($payload);
        }

        $message = $this->errorMessage($response);

        if ($response->clientError()) {
            // externalOrderId is unique per merchant: "not unique" means an
            // earlier attempt with this id landed. Resolve it, never resend.
            if (str_contains(strtolower($message), 'not unique')) {
                $existing = $this->lookup($request->externalOrderId);

                if ($existing !== null) {
                    return $existing;
                }

                throw new PayoutOutcomeUnknownException(
                    "Heropayments reports order {$request->externalOrderId} already exists, but it could not be looked up.",
                );
            }

            throw new PayoutRejectedException($message);
        }

        // errors.md: on "timeout of 15000ms exceeded" and "internal server
        // error", check whether the withdrawal was created. Every 5xx is
        // treated that way — the lookup resolves the ones that were rejections.
        throw new PayoutOutcomeUnknownException(
            sprintf(
                'Heropayments returned %d for the withdrawal request: %s. It may have been created — do not resend before checking its status.',
                $response->status(),
                $message,
            ),
        );
    }

    public function lookup(string $externalOrderId): ?PayoutReceipt
    {
        $payload = $this->client->getPaymentByOrderId($externalOrderId);

        if (! is_array($payload) || (string) ($payload['id'] ?? '') === '') {
            return null;
        }

        return $this->adapter->payoutReceipt($payload);
    }

    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        return $this->adapter->payoutReceipt($payload);
    }
```

Add two private helpers:

```php
    /**
     * Payouts are closed by callback, so a send without one is refused.
     * Falls back to the package's own webhook route under its configured
     * name prefix.
     */
    private function callbackUrl(): string
    {
        $route = config('cashier-core.routes.name_prefix', 'cashier.webhooks.').'heropayment';

        $url = $this->config['webhook_url'] ?? (Route::has($route) ? route($route) : null);

        if ($url === null || $url === '') {
            throw new PaymentProcessingException(
                'Heropayment withdrawals need a callback URL: set webhook_url or register the package webhook routes.',
            );
        }

        return (string) $url;
    }

    private function errorMessage(Response $response): string
    {
        $json = $response->json();
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        if (is_array($message)) {
            $message = implode('; ', array_map('strval', $message));
        }

        $message = trim((string) ($message ?? $response->body()));

        return $message === '' ? "HTTP {$response->status()}" : $message;
    }
```

- [ ] **Step 7: Make the provider implement the contract**

In `src/Drivers/Heropayment/HeropaymentProvider.php`:
- Add imports for `Asciisd\CashierCore\Contracts\SendsPayouts`, `Asciisd\CashierCore\DataObjects\PayoutPreflight`, `Asciisd\CashierCore\DataObjects\PayoutReceipt` and `Asciisd\CashierCore\DataObjects\PayoutRequest`.
- Change the declaration to `class HeropaymentProvider implements PaymentProcessorInterface, ProvidesWebhookTransactionId, SendsPayouts`.
- Change `$supportedFeatures` to `['charge', 'webhook', 'payout']`.
- Add a property and the methods:

```php
    private ?HeropaymentPayoutService $payouts = null;

    public function preflight(PayoutRequest $request): PayoutPreflight
    {
        return $this->payouts()->preflight($request);
    }

    public function send(PayoutRequest $request): PayoutReceipt
    {
        return $this->payouts()->send($request);
    }

    public function lookup(string $externalOrderId): ?PayoutReceipt
    {
        return $this->payouts()->lookup($externalOrderId);
    }

    public function parsePayoutWebhook(array $payload): PayoutReceipt
    {
        return $this->payouts()->parsePayoutWebhook($payload);
    }

    private function payouts(): HeropaymentPayoutService
    {
        return $this->payouts ??= new HeropaymentPayoutService($this->config, $this->client);
    }
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Drivers/HeropaymentPayoutServiceTest.php tests/Unit/Drivers/HeropaymentAdapterTest.php`, then `vendor/bin/pest`
Expected: PASS, with the full suite green.

- [ ] **Step 9: Commit**

```bash
git add src/Drivers/Heropayment/ src/Logging/PaymentLogger.php tests/Unit/Drivers/HeropaymentPayoutServiceTest.php tests/Unit/Drivers/HeropaymentAdapterTest.php
git commit -m "feat(heropayment): send and look up V2 withdrawals

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: `WithdrawalWorkflow::sendPayout()`, plus the `cancel()` and `markPaid()` changes

**Files:**
- Create: `src/Events/WithdrawalPayoutSent.php`, `src/Events/WithdrawalPayoutFailed.php`, `src/Events/PayoutFundsInsufficient.php`
- Modify: `src/Logging/TransactionLogger.php`
- Modify: `src/Withdrawals/WithdrawalWorkflow.php`
- Test: `tests/Feature/HeropaymentPayoutWorkflowTest.php` (new)

**Interfaces:**
- Consumes: `SendsPayouts`, `PayoutRequest`, `PayoutPreflight`, `PayoutReceipt`, `PayoutState`, both exceptions, `HeropaymentPayoutApi`.
- Produces:
  - `WithdrawalWorkflow::sendPayout(Transaction $transaction, Actor $actor): Result`
  - Events:
    - `WithdrawalPayoutSent(Transaction $transaction, Actor $actor)`
    - `WithdrawalPayoutFailed(Transaction $transaction, string $reason)`
    - `PayoutFundsInsufficient(Transaction $transaction, string $balance, string $required, string $walletCurrency)`
  - The workflow constructor becomes `(FundsLedger $ledger, TransferClaim $transferClaim = new TransferClaim, ?ConnectionRegistry $connections = null)`
  - A private `applyPayoutUpdate` call site. The method itself arrives in Task 6, which this task stubs; see Step 5.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/HeropaymentPayoutWorkflowTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Events\PayoutFundsInsufficient;
use Asciisd\CashierCore\Events\WithdrawalPayoutFailed;
use Asciisd\CashierCore\Events\WithdrawalPayoutSent;
use Asciisd\CashierCore\Models\AdminAction;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    HeropaymentPayoutApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function hpWithdrawal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'amount' => 100,
        'currency' => 'USD',
        'withdrawal_method' => 'crypto',
        'withdrawal_details' => ['payout_address' => 'TXyzCustomer', 'payout_currency' => 'usdttrc20'],
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function hpActor(): Actor
{
    return new Actor(id: 9, guard: 'admin', ip: '10.0.0.1');
}

function hpWorkflow(): WithdrawalWorkflow
{
    return app(WithdrawalWorkflow::class);
}

it('sends the payout and records it as sent while status stays Processing', function () {
    Event::fake([WithdrawalPayoutSent::class]);
    HeropaymentPayoutApi::fake();

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());

    $fresh = $transaction->fresh();

    expect($result->success)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->payout_reference)->toBe('hero-wd-1')
        ->and($fresh->metadata)->toHaveKey('payout_sent_at')
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['externalOrderId'] === 'WD-01TEST'
        && $request['customerId'] === '70001'
        && $request['priceAmount'] === '100.00');

    Event::assertDispatched(WithdrawalPayoutSent::class);
    expect(AdminAction::query()->where('action', 'withdrawal.send-payout')->exists())->toBeTrue();
    $this->ledger->assertNothingMoved();
});

it('refuses non-Processing rows and rows already sent', function (array $overrides, string $message) {
    HeropaymentPayoutApi::fake();

    $result = hpWorkflow()->sendPayout(hpWithdrawal($overrides), hpActor());

    expect($result->success)->toBeFalse()->and($result->message)->toContain($message);
    Http::assertNothingSent();
})->with([
    'pending' => [['status' => PaymentStatus::Pending], 'Processing'],
    'sent' => [['payout_state' => PayoutState::Sent], 'already been sent'],
    'paid' => [['payout_state' => PayoutState::Paid], 'already been sent'],
]);

it('refuses a withdrawal whose connection cannot send payouts', function () {
    $result = hpWorkflow()->sendPayout(hpWithdrawal(['provider' => 'manual']), hpActor());

    expect($result->success)->toBeFalse()->and($result->message)->toContain('cannot send payouts');
});

it('leaves the row untouched and alerts ops when the balance is short', function () {
    Event::fake([PayoutFundsInsufficient::class]);
    HeropaymentPayoutApi::fake(['balance' => '50.00']);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('50.00')
        ->and($transaction->fresh()->payout_state)->toBeNull()
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal'));
    Event::assertDispatched(PayoutFundsInsufficient::class, fn ($event) => $event->balance === '50.00');
});

it('records a rejection in metadata, never in error_message', function () {
    Event::fake([WithdrawalPayoutFailed::class]);
    HeropaymentPayoutApi::fake(['withdrawal' => Http::response(['message' => 'Payout address not valid'], 400)]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->success)->toBeFalse()
        ->and($fresh->status)->toBe(PaymentStatus::Processing)
        ->and($fresh->payout_state)->toBe(PayoutState::Failed)
        ->and($fresh->metadata['payout_error'])->toBe('Payout address not valid')
        ->and($fresh->error_message)->toBeNull()
        ->and($fresh->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);

    Event::assertDispatched(WithdrawalPayoutFailed::class);
});

it('marks an ambiguous send unknown and keeps the claim so cancel is refused', function () {
    HeropaymentPayoutApi::fake(['withdrawal' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $result = hpWorkflow()->sendPayout($transaction = hpWithdrawal(), hpActor());
    $fresh = $transaction->fresh();

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Do not resend')
        ->and($fresh->payout_state)->toBe(PayoutState::Unknown)
        ->and($fresh->metadata)->toHaveKey(TransferClaim::METADATA_KEY);

    expect(hpWorkflow()->cancel($fresh, hpActor())->success)->toBeFalse();
    $this->ledger->assertNothingMoved();
});

it('resends a failed payout under a new attempt id and records the old one', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Failed,
        'metadata' => ['ledger_account' => 70001, 'payout_error' => 'Payout address not valid'],
    ]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());
    $fresh = $transaction->fresh();

    expect($result->success)->toBeTrue()
        ->and($fresh->provider_transaction_id)->toBe('WD-01TEST-2')
        ->and($fresh->payout_state)->toBe(PayoutState::Sent)
        ->and($fresh->metadata['payout_attempts'][0])->toMatchArray([
            'order_id' => 'WD-01TEST',
            'state' => 'failed',
            'error' => 'Payout address not valid',
        ]);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/withdrawal')
        && $request['externalOrderId'] === 'WD-01TEST-2');
});

it('does not burn an attempt id when preflight refuses a resend', function () {
    HeropaymentPayoutApi::fake(['balance' => '1.00']);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Failed]);

    hpWorkflow()->sendPayout($transaction, hpActor());

    expect($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST');
});

it('re-uses the same order id when resending an unknown payout that lookup cannot find', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Unknown]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->success)->toBeTrue()
        ->and($transaction->fresh()->provider_transaction_id)->toBe('WD-01TEST');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/payments/order/WD-01TEST'));
});

// --- Review Focus 5 ---------------------------------------------------------

it('fails cleanly and releases the claim when payout details are missing', function () {
    HeropaymentPayoutApi::fake();

    $transaction = hpWithdrawal(['withdrawal_details' => ['payout_currency' => 'usdttrc20']]);

    $result = hpWorkflow()->sendPayout($transaction, hpActor());

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('payout_address')
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

// --- cancel / markPaid ----------------------------------------------------------

it('refuses cancel while a payout is in flight and allows it after a failure', function () {
    expect(hpWorkflow()->cancel(hpWithdrawal(['payout_state' => PayoutState::Sent]), hpActor())->success)->toBeFalse();

    $failed = hpWithdrawal([
        'provider_transaction_id' => 'WD-02TEST',
        'payout_state' => PayoutState::Failed,
    ]);

    expect(hpWorkflow()->cancel($failed, hpActor())->success)->toBeTrue();
    $this->ledger->assertMoved('correct', fn (array $m) => $m['amount'] === 100.0);
});

// --- Review Focus 1 ---------------------------------------------------------

it('cancel re-checks payout_state under the lock', function () {
    $stale = hpWithdrawal();                              // loaded while payout_state was null
    Transaction::query()->whereKey($stale->id)->update(['payout_state' => PayoutState::Sent->value]);

    $result = hpWorkflow()->cancel($stale, hpActor());

    expect($result->success)->toBeFalse()
        ->and($stale->fresh()->status)->toBe(PaymentStatus::Processing);
    $this->ledger->assertNothingMoved();
});

it('marks a sent payout paid when an admin closes it by hand', function () {
    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->markPaid($transaction, hpActor(), ['payout_reference' => 'checked in back office']);

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Paid)
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('leaves payout_state null when a never-sent withdrawal is marked paid', function () {
    $transaction = hpWithdrawal();

    hpWorkflow()->markPaid($transaction, hpActor());

    expect($transaction->fresh()->payout_state)->toBeNull();
});
```

`FakeLedger` records refunds under the operation name `'correct'`, and debits under `'debit'`.

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php`
Expected: FAIL with "Call to undefined method … sendPayout()".

- [ ] **Step 3: Create the events**

`src/Events/WithdrawalPayoutSent.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\DataObjects\Actor;
use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP accepted the payout; the withdrawal stays Processing until it lands.
 */
class WithdrawalPayoutSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly Actor $actor,
    ) {}
}
```

`src/Events/WithdrawalPayoutFailed.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP refused or failed the payout. The withdrawal stays Processing for an
 * admin to resend or cancel; $reason is internal PSP text — not for customers.
 */
class WithdrawalPayoutFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $reason,
    ) {}
}
```

`src/Events/PayoutFundsInsufficient.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Events;

use Asciisd\CashierCore\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The PSP balance cannot cover this payout — ops should top it up. Nothing
 * was sent; amounts are decimal strings in the wallet currency.
 */
class PayoutFundsInsufficient
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $balance,
        public readonly string $required,
        public readonly string $walletCurrency,
    ) {}
}
```

- [ ] **Step 4: Add the logger lines**

In `src/Logging/TransactionLogger.php`, after `withdrawalMarkedPaid()`:

```php
    public static function withdrawalPayoutSent(int|string $adminId, int $transactionId, string $orderId, ?string $reference): void
    {
        self::channel()->info('Withdrawal payout sent to provider', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'payout_reference' => $reference,
        ]);
    }

    public static function withdrawalPayoutRejected(int|string $adminId, int $transactionId, string $orderId, string $reason): void
    {
        self::channel()->warning('Withdrawal payout rejected by provider', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'reason' => $reason,
        ]);
    }

    public static function withdrawalPayoutOutcomeUnknown(int|string $adminId, int $transactionId, string $orderId, string $error): void
    {
        self::channel()->critical('Withdrawal payout outcome unknown - check status before any resend', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'error' => $error,
        ]);
    }

    public static function withdrawalPayoutPreflightRefused(int|string $adminId, int $transactionId, ?string $reason, string $message): void
    {
        self::channel()->warning('Withdrawal payout refused before sending', [
            'admin_id' => $adminId,
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'message' => $message,
        ]);
    }
```

- [ ] **Step 5: Implement `sendPayout()` and the helpers in the workflow**

In `src/Withdrawals/WithdrawalWorkflow.php`, add these imports:

```php
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\DataObjects\PayoutPreflight;
use Asciisd\CashierCore\DataObjects\PayoutReceipt;
use Asciisd\CashierCore\DataObjects\PayoutRequest;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Events\PayoutFundsInsufficient;
use Asciisd\CashierCore\Events\WithdrawalPayoutFailed;
use Asciisd\CashierCore\Events\WithdrawalPayoutSent;
use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use Asciisd\CashierCore\Exceptions\PayoutOutcomeUnknownException;
use Asciisd\CashierCore\Exceptions\PayoutRejectedException;
use Illuminate\Http\Client\ConnectionException;
use Throwable;
```

Replace the constructor:

```php
    public function __construct(
        private readonly FundsLedger $ledger,
        private readonly TransferClaim $transferClaim = new TransferClaim,
        private readonly ?ConnectionRegistry $connections = null,
    ) {}
```

Add after `markPaid()`:

```php
    /**
     * Send a Processing withdrawal's payout through its PSP connection.
     *
     * The balance is checked first; a shortfall sends nothing and leaves the
     * row as it was. A rejection marks the payout failed for an admin to fix
     * and resend. An ambiguous outcome marks it unknown and keeps the claim,
     * so nothing — not a resend, not a cancel — acts until it is resolved.
     * `status` stays Processing throughout; the customer never sees PSP state.
     */
    public function sendPayout(Transaction $transaction, Actor $actor): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if ($transaction->status !== PaymentStatus::Processing) {
            return Result::failed('Only withdrawals in Processing can be sent for payout.');
        }

        if (! $this->canSendPayout($transaction)) {
            return Result::failed('This withdrawal has already been sent for payout.');
        }

        $provider = $this->payoutProvider($transaction);

        if (! $provider) {
            return Result::failed("The {$transaction->provider} connection cannot send payouts.");
        }

        // An unknown send may have landed: resolve it before sending anything.
        if ($transaction->payout_state === PayoutState::Unknown) {
            $existing = $this->lookupPayout($provider, (string) $transaction->provider_transaction_id);

            if ($existing !== null) {
                $this->applyPayoutUpdate($transaction, $existing, 'sync');

                return Result::ok('The earlier payout was found at the provider; its status has been applied.', $transaction->refresh());
            }
        }

        $claimed = $this->transferClaim->acquire(
            $transaction,
            'withdrawal:send-payout',
            expectedStatus: PaymentStatus::Processing,
            requireNoTicket: false,
        );

        if (! $claimed) {
            return Result::failed('This withdrawal is being processed, or an earlier send is still settling. Try again shortly.');
        }

        // Re-check under the lock: the caller's instance may be stale.
        if (! $this->canSendPayout($claimed)) {
            $this->transferClaim->release($claimed);

            return Result::failed('This withdrawal has already been sent for payout.');
        }

        // A failed payment still holds its order id at the PSP, so a resend
        // needs a new one. It is only persisted once a send is attempted.
        $orderId = $claimed->payout_state === PayoutState::Failed
            ? $this->nextAttemptId($claimed)
            : (string) $claimed->provider_transaction_id;

        try {
            $request = $this->payoutRequest($claimed, $orderId);
            $preflight = $provider->preflight($request);
        } catch (PaymentProcessingException $e) {
            $this->transferClaim->release($claimed);

            return Result::failed($e->getMessage());
        }

        if (! $preflight->ok) {
            $this->transferClaim->release($claimed);

            TransactionLogger::withdrawalPayoutPreflightRefused($actor->id, $claimed->id, $preflight->reason, (string) $preflight->message);

            if ($preflight->reason === PayoutPreflight::INSUFFICIENT_FUNDS) {
                PayoutFundsInsufficient::dispatch(
                    $claimed,
                    (string) $preflight->balance,
                    (string) $preflight->required,
                    (string) $preflight->walletCurrency,
                );
            }

            return Result::failed((string) $preflight->message);
        }

        try {
            $receipt = $provider->send($request);
        } catch (PayoutRejectedException $e) {
            $this->transferClaim->settle($claimed, $this->withAttempt($claimed, $orderId, [
                'payout_state' => PayoutState::Failed,
            ], ['payout_error' => $e->getMessage()]));

            $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
                'outcome' => 'rejected', 'order_id' => $orderId, 'error' => $e->getMessage(),
            ]);

            TransactionLogger::withdrawalPayoutRejected($actor->id, $claimed->id, $orderId, $e->getMessage());

            WithdrawalPayoutFailed::dispatch($claimed, $e->getMessage());

            return Result::failed('The provider refused the payout: '.$e->getMessage());
        } catch (PayoutOutcomeUnknownException $e) {
            // Keep the claim: the payout may exist, and the claim's window is
            // the time the PSP needs to settle it. Nothing acts until then.
            $claimed->update($this->withAttempt($claimed, $orderId, [
                'payout_state' => PayoutState::Unknown,
            ], ['payout_error' => $e->getMessage()]));

            $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
                'outcome' => 'unknown', 'order_id' => $orderId, 'error' => $e->getMessage(),
            ]);

            TransactionLogger::withdrawalPayoutOutcomeUnknown($actor->id, $claimed->id, $orderId, $e->getMessage());

            return Result::failed('Payout outcome unknown. Do not resend; use Check status, or retry after 10 minutes.');
        } catch (PaymentProcessingException $e) {
            $this->transferClaim->release($claimed);

            return Result::failed($e->getMessage());
        }

        $this->transferClaim->settle($claimed, $this->withAttempt($claimed, $orderId, [
            'payout_state' => PayoutState::Sent,
            'payout_reference' => $receipt->reference,
            'provider_payload' => $receipt->payload,
        ], ['payout_sent_at' => now()->toIso8601String(), 'payout_error' => null]));

        $this->audit($actor, 'withdrawal.send-payout', $claimed, PaymentStatus::Processing, PaymentStatus::Processing, [
            'outcome' => 'sent', 'order_id' => $orderId, 'reference' => $receipt->reference,
        ]);

        TransactionLogger::withdrawalPayoutSent($actor->id, $claimed->id, $orderId, $receipt->reference);

        WithdrawalPayoutSent::dispatch($claimed, $actor);

        // A "not unique" send resolves to the existing payout, which may
        // already be further along than sent.
        if ($receipt->state !== PayoutState::Sent) {
            $this->applyPayoutUpdate($claimed, $receipt, 'sync');
        }

        return Result::ok('Payout sent.', $claimed->refresh());
    }

    /**
     * Stub until Task 6 replaces it with the full implementation.
     */
    public function applyPayoutUpdate(Transaction $transaction, PayoutReceipt $receipt, string $source = 'webhook'): bool
    {
        return false;
    }

    private function canSendPayout(Transaction $transaction): bool
    {
        return in_array($transaction->payout_state, [null, PayoutState::Failed, PayoutState::Unknown], true);
    }

    private function payoutProvider(Transaction $transaction): ?SendsPayouts
    {
        try {
            $provider = ($this->connections ?? app(ConnectionRegistry::class))
                ->get($transaction->connection ?? $transaction->provider);
        } catch (Throwable) {
            return null;
        }

        return $provider instanceof SendsPayouts ? $provider : null;
    }

    private function lookupPayout(SendsPayouts $provider, string $orderId): ?PayoutReceipt
    {
        try {
            return $provider->lookup($orderId);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * @throws PaymentProcessingException when the host stored no payout destination
     */
    private function payoutRequest(Transaction $transaction, string $orderId): PayoutRequest
    {
        $details = (array) ($transaction->withdrawal_details ?? []);
        $address = trim((string) ($details['payout_address'] ?? ''));
        $currency = trim((string) ($details['payout_currency'] ?? ''));

        if ($address === '' || $currency === '') {
            throw new PaymentProcessingException('This withdrawal has no payout_address / payout_currency in its details.');
        }

        $extraId = trim((string) ($details['payout_extra_id'] ?? ''));
        $email = trim((string) ($details['customer_email'] ?? ''));

        return new PayoutRequest(
            externalOrderId: $orderId,
            customerId: (string) ($transaction->metadata['ledger_account'] ?? $transaction->user_id),
            amount: (string) $transaction->amount,
            currency: (string) $transaction->currency,
            payoutCurrency: strtolower($currency),
            payoutAddress: $address,
            payoutExtraId: $extraId === '' ? null : $extraId,
            customerEmail: $email === '' ? null : $email,
        );
    }

    /**
     * WD-<ULID> → WD-<ULID>-2 → WD-<ULID>-3, always from the first attempt's id.
     */
    private function nextAttemptId(Transaction $transaction): string
    {
        $attempts = $transaction->metadata['payout_attempts'] ?? [];
        $base = (string) ($attempts[0]['order_id'] ?? $transaction->provider_transaction_id);

        return $base.'-'.(count($attempts) + 2);
    }

    /**
     * The attributes for a send outcome; when the attempt used a new order id,
     * the previous attempt is appended to `payout_attempts` and the row moves
     * to the new id so callbacks still correlate.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function withAttempt(Transaction $transaction, string $orderId, array $attributes, array $metadata): array
    {
        $current = $transaction->metadata ?? [];

        if ($orderId !== $transaction->provider_transaction_id) {
            $current['payout_attempts'] = [...($current['payout_attempts'] ?? []), [
                'order_id' => $transaction->provider_transaction_id,
                'state' => $transaction->payout_state?->value,
                'error' => $current['payout_error'] ?? null,
                'at' => now()->toIso8601String(),
            ]];

            $attributes['provider_transaction_id'] = $orderId;
        }

        return array_merge($attributes, ['metadata' => array_merge($current, $metadata)]);
    }
```

`$actor->id` is `int|string`. Change the `audit()` helper's cast only if static analysis complains; `'actor_id' => (string) $actor->id` already handles both.

- [ ] **Step 6: Guard `cancel()` and update `markPaid()`**

In `cancel()`, after the `in_array($transaction->status, …)` check:

```php
        if ($transaction->payout_state?->isInFlight()) {
            return Result::failed('A payout for this withdrawal is in flight at the provider. Check its status before cancelling.');
        }
```

In `cancel()`, directly after the `if (! $claimed) { … }` block:

```php
        // Re-check under the lock: a send may have settled since this instance was loaded.
        if ($claimed->payout_state?->isInFlight()) {
            $this->transferClaim->release($claimed);

            return Result::failed('A payout for this withdrawal is in flight at the provider. Check its status before cancelling.');
        }
```

In `markPaid()`, replace the closure that builds the `Succeeded` attributes:

```php
            fn (Transaction $locked): array => array_merge([
                'status' => PaymentStatus::Succeeded,
                'processed_at' => $locked->processed_at ?? now(),
                'metadata' => array_merge($locked->metadata ?? [], $payoutDetails, [
                    'paid_by_actor_id' => $actor->id,
                    'paid_at' => now()->toIso8601String(),
                ]),
            ], $locked->payout_state !== null ? ['payout_state' => PayoutState::Paid] : []),
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php tests/Feature/WithdrawalWorkflowTest.php`, then `vendor/bin/pest`
Expected: PASS, with the existing withdrawal tests unchanged and green.

- [ ] **Step 8: Commit**

```bash
git add src/Events/WithdrawalPayoutSent.php src/Events/WithdrawalPayoutFailed.php src/Events/PayoutFundsInsufficient.php src/Logging/TransactionLogger.php src/Withdrawals/WithdrawalWorkflow.php tests/Feature/HeropaymentPayoutWorkflowTest.php
git commit -m "feat(withdrawals): send payouts through the PSP with a balance preflight

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: `applyPayoutUpdate()` and `syncPayout()`

**Files:**
- Modify: `src/Withdrawals/WithdrawalWorkflow.php` (replace the Task 5 stub)
- Modify: `src/Logging/TransactionLogger.php`
- Test: `tests/Feature/HeropaymentPayoutWorkflowTest.php`

**Interfaces:**
- Consumes: everything from Task 5.
- Produces:
  - `WithdrawalWorkflow::applyPayoutUpdate(Transaction $transaction, PayoutReceipt $receipt, string $source = 'webhook'): bool`
  - `WithdrawalWorkflow::syncPayout(Transaction $transaction, Actor $actor): Result`
  - `TransactionLogger::withdrawalPayoutUpdated(...)`, `withdrawalPayoutUpdateIgnored(...)` and `withdrawalPaidAfterCancellation(...)`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/HeropaymentPayoutWorkflowTest.php`. Add imports for `Asciisd\CashierCore\DataObjects\PayoutReceipt` and `Asciisd\CashierCore\Events\WithdrawalMarkedPaid`, and add `Illuminate\Support\Facades\Log` if it isn't there already:

```php
function hpReceipt(string $status, array $payload = []): PayoutReceipt
{
    return (new \Asciisd\CashierCore\Drivers\Heropayment\HeropaymentAdapter)
        ->payoutReceipt(HeropaymentPayoutApi::withdrawal(array_merge(['status' => $status], $payload)));
}

it('closes the withdrawal as Succeeded on finished, without touching amount', function () {
    Event::fake([WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    $applied = hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished', [
        'paidAmount' => 6.85714285,
        'outcomeHash' => '0xabc',
        'outcomeAmount' => 99.1,
    ]));

    $fresh = $transaction->fresh();

    expect($applied)->toBeTrue()
        ->and($fresh->status)->toBe(PaymentStatus::Succeeded)
        ->and($fresh->payout_state)->toBe(PayoutState::Paid)
        ->and((string) $fresh->amount)->toBe('100.00')
        ->and($fresh->metadata['payout_outcome']['outcomeHash'])->toBe('0xabc');

    Event::assertDispatched(WithdrawalMarkedPaid::class, fn ($event) => $event->actor?->guard === 'system');
    expect(AdminAction::query()->where('action', 'withdrawal.payout-paid')->exists())->toBeTrue();
    $this->ledger->assertNothingMoved();
});

it('keeps the withdrawal Processing and alerts on failed or refunded', function (string $status) {
    Event::fake([WithdrawalPayoutFailed::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt($status));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Failed)
        ->and($transaction->fresh()->metadata['payout_error'])->toBe("Heropayment status: {$status}")
        ->and($transaction->fresh()->error_message)->toBeNull();

    Event::assertDispatched(WithdrawalPayoutFailed::class);
})->with(['failed', 'refunded']);

it('refreshes the payload but changes nothing else on an in-progress status', function () {
    Event::fake([WithdrawalPayoutFailed::class, WithdrawalMarkedPaid::class]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('exchanging'));

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->provider_payload['status'])->toBe('exchanging');

    Event::assertNotDispatched(WithdrawalPayoutFailed::class);
    Event::assertNotDispatched(WithdrawalMarkedPaid::class);
});

it('treats paid as final and ignores a late failed', function () {
    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);
    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished'));

    expect(hpWorkflow()->applyPayoutUpdate($transaction->fresh(), hpReceipt('failed')))->toBeFalse()
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
});

it('resolves an unknown payout and drops the leftover claim', function () {
    $transaction = hpWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('sending'));

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent)
        ->and($transaction->fresh()->metadata)->not->toHaveKey(TransferClaim::METADATA_KEY);
});

// --- Review Focus 2 ---------------------------------------------------------

it('logs critically and changes nothing when finished arrives for a cancelled withdrawal', function () {
    Log::spy();

    $transaction = hpWithdrawal(['status' => PaymentStatus::Canceled, 'payout_state' => PayoutState::Failed]);

    expect(hpWorkflow()->applyPayoutUpdate($transaction, hpReceipt('finished')))->toBeFalse()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Canceled);

    Log::shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'cancelled'))->once();
});

// --- syncPayout -------------------------------------------------------------------

it('syncs a sent payout from the provider', function () {
    HeropaymentPayoutApi::fake(['lookup' => Http::response(HeropaymentPayoutApi::withdrawal(['status' => 'finished']))]);

    $transaction = hpWithdrawal(['payout_state' => PayoutState::Sent]);

    $result = hpWorkflow()->syncPayout($transaction, hpActor());

    expect($result->success)->toBeTrue()
        ->and($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded);
    expect(AdminAction::query()->where('action', 'withdrawal.sync-payout')->exists())->toBeTrue();
});

it('reports when the provider has no such payout', function () {
    HeropaymentPayoutApi::fake();

    $result = hpWorkflow()->syncPayout(hpWithdrawal(['payout_state' => PayoutState::Unknown]), hpActor());

    expect($result->success)->toBeFalse()->and($result->message)->toContain('No payout found');
});

it('only syncs payouts that are sent or unknown', function () {
    expect(hpWorkflow()->syncPayout(hpWithdrawal(), hpActor())->success)->toBeFalse();
});
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php`
Expected: FAIL. `applyPayoutUpdate` returns false from the stub, and `syncPayout` is undefined.

- [ ] **Step 3: Add the logger lines**

In `src/Logging/TransactionLogger.php`, after `withdrawalPayoutPreflightRefused()`:

```php
    public static function withdrawalPayoutUpdated(int $transactionId, ?string $from, string $to, string $rawStatus, string $source): void
    {
        self::channel()->info('Withdrawal payout state updated', [
            'transaction_id' => $transactionId,
            'from' => $from,
            'to' => $to,
            'provider_status' => $rawStatus,
            'source' => $source,
        ]);
    }

    public static function withdrawalPayoutUpdateIgnored(int $transactionId, string $status, ?string $payoutState, string $rawStatus, string $source): void
    {
        self::channel()->info('Withdrawal payout update ignored', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'payout_state' => $payoutState,
            'provider_status' => $rawStatus,
            'source' => $source,
        ]);
    }

    public static function withdrawalPaidAfterCancellation(int $transactionId, string $status, string $orderId): void
    {
        self::channel()->critical('Provider reports a payout finished on a cancelled or closed withdrawal - manual intervention required', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'order_id' => $orderId,
        ]);
    }
```

- [ ] **Step 4: Implement both methods**

In `src/Withdrawals/WithdrawalWorkflow.php`, add the imports `Illuminate\Support\Arr`, `Illuminate\Support\Facades\DB` and `Asciisd\CashierCore\Events\WithdrawalMarkedPaid`, if missing (`WithdrawalMarkedPaid` is already imported). Replace the `applyPayoutUpdate()` stub with:

```php
    /**
     * Apply what the PSP reports about a payout — from a callback, a lookup,
     * or a send that resolved to an existing payout.
     *
     * `finished` closes the withdrawal as Succeeded and announces it exactly
     * like a manual markPaid(). `failed`/`refunded` leave it Processing for an
     * admin. Paid is final; a closed withdrawal is never reopened.
     *
     * @param  string  $source  `webhook` or `sync`
     * @return bool whether the row changed
     */
    public function applyPayoutUpdate(Transaction $transaction, PayoutReceipt $receipt, string $source = 'webhook'): bool
    {
        $model = Cashier::transactionModel();

        /** @var array{0: Transaction, 1: ?PayoutState}|null $applied */
        $applied = DB::transaction(function () use ($model, $transaction, $receipt, $source): ?array {
            $locked = $model::query()
                ->withoutGlobalScopes($model::cashierBypassedScopes())
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->type !== TransactionType::Withdrawal) {
                return null;
            }

            if ($locked->payout_state === PayoutState::Paid || $locked->status !== PaymentStatus::Processing) {
                if ($receipt->state === PayoutState::Paid && $locked->status !== PaymentStatus::Succeeded) {
                    TransactionLogger::withdrawalPaidAfterCancellation($locked->id, $locked->status->value, (string) $locked->provider_transaction_id);
                } else {
                    TransactionLogger::withdrawalPayoutUpdateIgnored($locked->id, $locked->status->value, $locked->payout_state?->value, $receipt->rawStatus, $source);
                }

                return null;
            }

            $from = $locked->payout_state;

            // Any write here also drops a leftover send claim: once the PSP has
            // answered, an unknown send is resolved and must not stay locked.
            $metadata = Arr::except($locked->metadata ?? [], [TransferClaim::METADATA_KEY]);

            $attributes = [
                'payout_state' => $receipt->state,
                'provider_payload' => $receipt->payload,
            ];

            if ($receipt->reference !== null) {
                $attributes['payout_reference'] = $receipt->reference;
            }

            if ($receipt->state === PayoutState::Paid) {
                $attributes['status'] = PaymentStatus::Succeeded;
                $attributes['processed_at'] = $locked->processed_at ?? now();
                $metadata['payout_outcome'] = Arr::only($receipt->payload, [
                    'outcomeHash', 'outcomeHashLink', 'outcomeAmount', 'outcomeCurrency',
                    'merchantAmountUsdt', 'feePercent', 'networkFee',
                ]);
                $metadata['paid_at'] = now()->toIso8601String();
            }

            if ($receipt->state === PayoutState::Failed) {
                $metadata['payout_error'] = $receipt->error;
            }

            $attributes['metadata'] = $metadata;

            $locked->update($attributes);

            return [$locked, $from];
        });

        if ($applied === null) {
            return false;
        }

        [$row, $from] = $applied;

        if ($transaction !== $row) {
            $transaction->setRawAttributes($row->getAttributes(), true);
        }

        TransactionLogger::withdrawalPayoutUpdated($row->id, $from?->value, $row->payout_state->value, $receipt->rawStatus, $source);

        if ($from === $row->payout_state) {
            return true;
        }

        $system = new Actor(id: "system:{$row->provider}", guard: 'system');

        if ($row->payout_state === PayoutState::Paid) {
            $this->audit($system, 'withdrawal.payout-paid', $row, PaymentStatus::Processing, PaymentStatus::Succeeded, [
                'source' => $source, 'reference' => $row->payout_reference,
            ]);

            WithdrawalMarkedPaid::dispatch($row, $system);
        }

        if ($row->payout_state === PayoutState::Failed) {
            $this->audit($system, 'withdrawal.payout-failed', $row, PaymentStatus::Processing, PaymentStatus::Processing, [
                'source' => $source, 'error' => $receipt->error,
            ]);

            WithdrawalPayoutFailed::dispatch($row, (string) $receipt->error);
        }

        return true;
    }

    /**
     * Ask the PSP for a sent or unknown payout's status and apply it — the
     * admin "check status" action, and the fallback when callbacks stop.
     */
    public function syncPayout(Transaction $transaction, Actor $actor): Result
    {
        if ($transaction->type !== TransactionType::Withdrawal) {
            return Result::failed('This action is only for withdrawal transactions.');
        }

        if (! $transaction->payout_state?->isInFlight()) {
            return Result::failed('Only sent or unknown payouts can be checked.');
        }

        $provider = $this->payoutProvider($transaction);

        if (! $provider) {
            return Result::failed("The {$transaction->provider} connection cannot send payouts.");
        }

        $orderId = (string) $transaction->provider_transaction_id;
        $receipt = $this->lookupPayout($provider, $orderId);

        if ($receipt === null) {
            $this->audit($actor, 'withdrawal.sync-payout', $transaction, $transaction->status, $transaction->status, [
                'order_id' => $orderId, 'found' => false,
            ]);

            return Result::failed("No payout found for order {$orderId} at the provider (or the lookup failed).");
        }

        $from = $transaction->status;

        $this->applyPayoutUpdate($transaction, $receipt, 'sync');

        $this->audit($actor, 'withdrawal.sync-payout', $transaction, $from, $transaction->status, [
            'order_id' => $orderId, 'found' => true, 'provider_status' => $receipt->rawStatus,
        ]);

        return Result::ok("Payout status: {$receipt->rawStatus}.", $transaction);
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/HeropaymentPayoutWorkflowTest.php`, then `vendor/bin/pest`
Expected: PASS. This includes Task 5's test "re-uses the same order id when resending an unknown payout", which now also runs through the real `applyPayoutUpdate`.

- [ ] **Step 6: Commit**

```bash
git add src/Withdrawals/WithdrawalWorkflow.php src/Logging/TransactionLogger.php tests/Feature/HeropaymentPayoutWorkflowTest.php
git commit -m "feat(withdrawals): apply payout callbacks and sync payout status

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Route withdrawal callbacks to `ProcessPayoutWebhook`

**Files:**
- Create: `src/Jobs/ProcessPayoutWebhook.php`
- Modify: `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php`
- Test: `tests/Feature/Webhooks/HeropaymentWebhookTest.php` (additions), `tests/Feature/ProcessPayoutWebhookTest.php` (new)

**Interfaces:**
- Consumes: `SendsPayouts::parsePayoutWebhook()`, `ProvidesWebhookTransactionId::extractWebhookTransactionId()`, `WithdrawalWorkflow::applyPayoutUpdate()`, `TransferClaim::hasFreshClaim()`.
- Produces: `ProcessPayoutWebhook(string $driver, array $payload, ?string $connection = null)` with public `$driver`, `$payload` and `$connectionName`.

- [ ] **Step 1: Write the failing routing tests**

Append to `tests/Feature/Webhooks/HeropaymentWebhookTest.php` and add `use Asciisd\CashierCore\Jobs\ProcessPayoutWebhook;`. The file's `beforeEach`, `heropaymentCallback()` and `signedHeroHeaders()` already exist:

```php
it('routes a withdrawal callback to the payout job, never the deposit job', function () {
    $payload = array_merge(heropaymentCallback('WD-01TEST', 'finished'), ['transactionType' => 'withdrawal']);

    $this->postJson('/api/webhooks/heropayment', $payload, signedHeroHeaders($payload))->assertOk();

    Queue::assertPushed(ProcessPayoutWebhook::class, fn (ProcessPayoutWebhook $job) => $job->payload['externalOrderId'] === 'WD-01TEST'
        && $job->connectionName === 'heropayment');
    Queue::assertNotPushed(ProcessPaymentProviderWebhook::class);
});

it('keeps routing deposit callbacks to the deposit job', function () {
    $payload = array_merge(heropaymentCallback('DEP-1'), ['transactionType' => 'deposit']);

    $this->postJson('/api/webhooks/heropayment', $payload, signedHeroHeaders($payload))->assertOk();

    Queue::assertPushed(ProcessPaymentProviderWebhook::class);
    Queue::assertNotPushed(ProcessPayoutWebhook::class);
});
```

- [ ] **Step 2: Write the failing job tests**

`tests/Feature/ProcessPayoutWebhookTest.php`:

```php
<?php

declare(strict_types=1);

use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\FundsLedger;
use Asciisd\CashierCore\Enums\PaymentStatus;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Jobs\ProcessPayoutWebhook;
use Asciisd\CashierCore\Models\Transaction;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Testing\FakeLedger;
use Asciisd\CashierCore\Tests\Fixtures\HeropaymentPayoutApi;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;

beforeEach(function () {
    config()->set('cashier-core.security.encrypt_provider_payload', false);
    config()->set('cashier-core.security.encrypt_withdrawal_details', false);

    HeropaymentPayoutApi::configure();

    $this->ledger = new FakeLedger;
    app()->instance(FundsLedger::class, $this->ledger);
});

function hpJobWithdrawal(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => 1,
        'provider' => 'heropayment',
        'provider_transaction_id' => 'WD-01TEST',
        'type' => TransactionType::Withdrawal,
        'status' => PaymentStatus::Processing,
        'payout_state' => PayoutState::Sent,
        'amount' => 100,
        'currency' => 'USD',
        'metadata' => ['ledger_account' => 70001],
        'mt5_ticket_number' => 'T-1',
    ], $overrides));
}

function hpRunJob(array $payload): ProcessPayoutWebhook
{
    $job = (new ProcessPayoutWebhook('heropayment', $payload, 'heropayment'))->withFakeQueueInteractions();

    $job->handle(app(ConnectionRegistry::class), app(WithdrawalWorkflow::class), new TransferClaim);

    return $job;
}

it('closes a withdrawal from a finished callback without crediting the ledger', function () {
    $transaction = hpJobWithdrawal();

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'finished']));

    expect($transaction->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($transaction->fresh()->payout_state)->toBe(PayoutState::Paid);
    $this->ledger->assertNothingMoved();
});

it('ignores a callback for an unknown order id', function () {
    hpRunJob(HeropaymentPayoutApi::withdrawal(['externalOrderId' => 'WD-NOPE', 'status' => 'finished']))
        ->assertNotReleased();

    expect(Transaction::query()->count())->toBe(0);
});

it('never matches a deposit row with the same order id', function () {
    $deposit = hpJobWithdrawal(['type' => TransactionType::Deposit, 'payout_state' => null]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'finished']));

    expect($deposit->fresh()->status)->toBe(PaymentStatus::Processing);
});

it('re-queues a callback that arrives while a send still holds the claim', function () {
    $transaction = hpJobWithdrawal([
        'payout_state' => null,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'waiting']))->assertReleased(30);

    expect($transaction->fresh()->payout_state)->toBeNull();
});

it('applies a callback to an unknown payout even though its claim is still held', function () {
    $transaction = hpJobWithdrawal([
        'payout_state' => PayoutState::Unknown,
        'metadata' => ['ledger_account' => 70001, TransferClaim::METADATA_KEY => now()->toIso8601String()],
    ]);

    hpRunJob(HeropaymentPayoutApi::withdrawal(['status' => 'sending']))->assertNotReleased();

    expect($transaction->fresh()->payout_state)->toBe(PayoutState::Sent);
});
```

- [ ] **Step 3: Run them to verify they fail**

Run: `vendor/bin/pest tests/Feature/ProcessPayoutWebhookTest.php tests/Feature/Webhooks/HeropaymentWebhookTest.php`
Expected: FAIL with "Class … ProcessPayoutWebhook not found".

- [ ] **Step 4: Create the job**

`src/Jobs/ProcessPayoutWebhook.php`:

```php
<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Jobs;

use Asciisd\CashierCore\Cashier;
use Asciisd\CashierCore\Connections\ConnectionRegistry;
use Asciisd\CashierCore\Contracts\ProvidesWebhookTransactionId;
use Asciisd\CashierCore\Contracts\SendsPayouts;
use Asciisd\CashierCore\Enums\PayoutState;
use Asciisd\CashierCore\Enums\TransactionType;
use Asciisd\CashierCore\Logging\PaymentLogger;
use Asciisd\CashierCore\Support\TransferClaim;
use Asciisd\CashierCore\Withdrawals\WithdrawalWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A verified payout (withdrawal) callback.
 *
 * Kept apart from ProcessPaymentProviderWebhook on purpose: the deposit
 * pipeline would move a failed payout to Failed and, on success, reconcile
 * `amount` to the crypto deducted from our balance.
 */
class ProcessPayoutWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Re-queues while a send settles count as attempts, so allow more than
     * the deposit job does.
     */
    public int $tries = 10;

    /** @see ProcessPaymentProviderWebhook::$uniqueFor */
    public int $uniqueFor = 120;

    /** How long to wait for an in-flight send to settle before retrying. */
    private const SETTLE_DELAY_SECONDS = 30;

    /** @see ProcessPaymentProviderWebhook::$connectionName */
    public readonly ?string $connectionName;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $driver,
        public readonly array $payload,
        ?string $connection = null,
    ) {
        $this->connectionName = $connection;

        $this->onConnection(config('cashier-core.queue.connection'));
        $this->onQueue(config('cashier-core.queue.queue', 'payments'));
    }

    public function uniqueId(): string
    {
        return 'payout:'.$this->driver.':'.sha1(json_encode($this->payload) ?: serialize($this->payload));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(ConnectionRegistry $registry, WithdrawalWorkflow $workflow, TransferClaim $claims): void
    {
        $provider = $registry->get($this->connectionName ?? $this->driver);

        $orderId = $provider instanceof ProvidesWebhookTransactionId
            ? $provider->extractWebhookTransactionId($this->payload)
            : null;

        if (! $provider instanceof SendsPayouts || $orderId === null || $orderId === '') {
            PaymentLogger::providerWebhookMissingTransactionId($this->driver);

            return;
        }

        $model = Cashier::transactionModel();

        $transaction = $model::query()
            ->withoutGlobalScopes($model::cashierBypassedScopes())
            ->where('provider', $this->driver)
            ->where('provider_transaction_id', $orderId)
            ->where('type', TransactionType::Withdrawal)
            ->first();

        if (! $transaction) {
            // Includes late callbacks for an earlier attempt's order id.
            PaymentLogger::providerWebhookTransactionNotFound($this->driver, $orderId);

            return;
        }

        // Heropayments can call back before sendPayout() has saved its result.
        // An unknown payout's claim is held on purpose and the callback is
        // exactly what resolves it, so only a live send is waited for.
        if ($transaction->payout_state !== PayoutState::Unknown && $claims->hasFreshClaim($transaction)) {
            $this->release(self::SETTLE_DELAY_SECONDS);

            return;
        }

        $workflow->applyPayoutUpdate($transaction, $provider->parsePayoutWebhook($this->payload), 'webhook');
    }
}
```

- [ ] **Step 5: Branch in the controller**

In `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php`, add `use Asciisd\CashierCore\Jobs\ProcessPayoutWebhook;`. Then replace the single dispatch line:

```php
        ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);
```

with:

```php
        // One callback URL serves both directions; the payload says which.
        // Withdrawal callbacks must never reach the deposit pipeline.
        if (($payload['transactionType'] ?? null) === 'withdrawal') {
            ProcessPayoutWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);
        } else {
            ProcessPaymentProviderWebhook::dispatch(self::DRIVER, $payload, self::DRIVER);
        }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/ProcessPayoutWebhookTest.php tests/Feature/Webhooks/HeropaymentWebhookTest.php`, then `vendor/bin/pest`
Expected: PASS, with the full suite green.

- [ ] **Step 7: Commit**

```bash
git add src/Jobs/ProcessPayoutWebhook.php src/Http/Controllers/Webhooks/HeropaymentWebhookController.php tests/Feature/ProcessPayoutWebhookTest.php tests/Feature/Webhooks/HeropaymentWebhookTest.php
git commit -m "feat(heropayment): route withdrawal callbacks to the payout job

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Configuration and contract docs

**Files:**
- Modify: `config/cashier-core.php` (the commented connection examples, after the Digiblox block)
- Modify: `.claude/skills/heropayments/references/quirks.md`
- Modify: `.claude/skills/heropayments/SKILL.md` (the "The code" list and the refunds line)

No tests: this task is documentation only. Verify it by re-running the suite, and by reading the diff.

- [ ] **Step 1: Document the connection in the config**

In `config/cashier-core.php`, inside the connections doc-comment and directly after the closing `| ],` of the Digiblox example (before the ` |` line that precedes `*/`), add:

```php
    |
    | Heropayments deposits open a hosted widget; withdrawals are pushed by
    | WithdrawalWorkflow::sendPayout() after an admin approves, and closed by
    | callback. The host stores payout_address, payout_currency (a
    | Heropayments ticker such as usdttrc20), and optionally payout_extra_id
    | and customer_email, in the withdrawal's details.
    |
    | 'heropayment' => [
    |     'driver' => 'heropayment',
    |     'base_url' => env('HEROPAYMENT_BASE_URL'),   // defaults to https://api.heropayments.io
    |     'api_key' => env('HEROPAYMENT_API_KEY'),
    |     'api_secret' => env('HEROPAYMENT_API_SECRET'),
    |     // Also the payout callback. Payouts refuse to send without one.
    |     'webhook_url' => env('HEROPAYMENT_WEBHOOK_URL'),
    |     // The contracted processing fee. Heropayments only reveals it after a
    |     // payment exists, so the payout balance check needs it configured.
    |     'fee_percent' => env('HEROPAYMENT_FEE_PERCENT'),
    |     // Withdrawals move real money and there is no sandbox. Off until the
    |     // flow is proven; every payout is capped at withdrawal_max_amount.
    |     'withdrawals_enabled' => env('HEROPAYMENT_WITHDRAWALS_ENABLED', false),
    |     'withdrawal_max_amount' => env('HEROPAYMENT_WITHDRAWAL_MAX_AMOUNT'),
    |     // Headroom over the estimated deduction for rate movement between
    |     // the balance check and the send.
    |     'balance_buffer_percent' => env('HEROPAYMENT_BALANCE_BUFFER_PERCENT', 2),
    | ],
```

- [ ] **Step 2: Record the unverified assumptions in quirks.md**

Open `.claude/skills/heropayments/references/quirks.md` and read the last numbered entry. Its current count is ten; use its heading format. Append three entries numbered after it, each marked **Unverified**:

```markdown
## 11. A failed payout still reserves its `externalOrderId` — **Unverified**

`WithdrawalWorkflow::sendPayout()` gives every resend after a failure a new
order id (`WD-<ULID>-2`, `-3`, …) on the assumption that Heropayments keeps a
failed or refunded payment's `externalOrderId` taken. The docs only say the id
"must be unique to create a transaction". Confirm on the first live failure;
if failed ids are released, the suffix is harmless but unnecessary.

## 12. A duplicate `externalOrderId` is a 4xx whose message contains "not unique" — **Unverified**

`HeropaymentPayoutService::send()` treats that response as "an earlier attempt
landed" and looks the payout up instead of failing. `errors.md` lists the
message (`Field externalOrderId for this user is not unique`, 400) but not the
body shape; the service reads `message`, then `error`, then the raw body.
This is the idempotency key that makes resending an unknown payout safe —
confirm it before relying on it at volume.

## 13. Which currency the withdrawal deduction is quoted in — **Unverified**

The payout preflight estimates the deduction as
`(amount × rate(currency → walletCurrency, withdrawal) × (1 + fee_percent) +
networkFee × rate(payoutCurrency → walletCurrency)) × (1 + buffer)` and
compares it with `v2/balance`. It assumes `merchantAmountUsdt` is denominated
in the balance's `walletCurrency` and that withdrawal network fees are native
payout-coin units (see `HeropaymentClient::getNetworkFees()`). Compare the
estimate with the first live payout's `merchantAmountUsdt`.
```

- [ ] **Step 3: Update SKILL.md**

In `.claude/skills/heropayments/SKILL.md`, add these under `## The code`:

```markdown
- `src/Drivers/Heropayment/HeropaymentPayoutService.php` — V2 withdrawals:
  balance preflight, send, lookup; fail-closed behind `withdrawals_enabled`
- `src/Jobs/ProcessPayoutWebhook.php` — withdrawal callbacks (routed by
  `transactionType`), applied through `WithdrawalWorkflow::applyPayoutUpdate()`
```

Replace the sentence "Refunds, capture, authorize and void all throw: Heropayments has no merchant-initiated refund." with:

```markdown
Payouts are supported through `SendsPayouts` (V2 only). Refunds, capture,
authorize and void all throw: Heropayments has no merchant-initiated refund.
```

- [ ] **Step 4: Verify**

Run: `vendor/bin/pest` and `php -l config/cashier-core.php`
Expected: all tests pass, and "No syntax errors detected".

- [ ] **Step 5: Commit**

```bash
git add config/cashier-core.php .claude/skills/heropayments/references/quirks.md .claude/skills/heropayments/SKILL.md
git commit -m "docs(heropayment): document payout config and unverified contract assumptions

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
