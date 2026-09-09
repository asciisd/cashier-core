---
name: cashier-core-development
description: "Build on and extend the asciisd/cashier-core v2 payment engine. Activates when working with named connections and drivers, ConnectionRegistry or Connections, PaymentService charges/refunds/sync, the webhook pipeline (WebhookProcessor, ReplayGuard, TransferClaim, WebhookRelay), the approve-first WithdrawalWorkflow, the FundsLedger / CustomerContract / PreparesChargeData / ProvidesWebhookTransactionId / ResolvesFundingAccount / FeeConfigurationContract contracts, fee calculation and settlement, the bundled APS/Jenapay/Heropayment/Payport/Sticpay/MyFatoorah drivers, payload sanitizing and encryption, cashier:* artisan commands, or the Cashier::fake / WebhookSimulator / FakeLedger testing seams."
---

# Cashier Core Development (v2)

## Package Overview

`asciisd/cashier-core` is a complete payment engine for Laravel — not a thin abstraction. It owns
charge orchestration, the webhook pipeline, the withdrawal state machine, fee arithmetic and the
PCI posture. The host owns its users, its funds system, and every side effect (mail, CRM, admin UI).

**Namespace:** `Asciisd\CashierCore` · **Requires:** PHP ^8.3, Laravel ^11|^12|^13

Seven direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport, Sticpay, MyFatoorah,
Xoala** — plus internal `manual`, `bank_transfer`, `crypto`. All are hosted-redirect (SAQ-A: no
PAN/CVV in the app).
Paytiko and KNET are separate plugins that register their drivers into this core.

## ⚠️ v1 is gone — do not reintroduce it

| v1 (removed) | v2 replacement |
|---|---|
| `PaymentFactory` facade, `PaymentFactoryInterface` | `ConnectionRegistry` + `Connections` |
| `config('cashier-core.processors')` | `connections` (PSP accounts) + `drivers` (driver → class) |
| `AbstractPaymentProcessor` as the only base | still present, but implementing `PaymentProcessorInterface` directly is fine |
| `TransactionService` | `PaymentService` + `Services\Webhooks\WebhookProcessor` |
| `cashier_transactions`, `cashier_payment_methods` | `transactions`, `refunds`, `cashier_webhook_events`, `cashier_admin_actions` |
| `processor_transaction_id`, `processor_response` column names | `provider_transaction_id`, `provider_payload` |

Upgrading a host? `UPGRADE-2.0.md` in the package root is the migration guide.

## Architecture

```
Cashier.php                    — static seams: ignoreRoutes/ignoreMigrations, transactionModel,
                                 customerModel, fake(), fakeConnection()
Connections/
├── Connections                — static reader over config: names(), get(), driverFor(),
│                                forDriver(), currencies(), exists()
└── ConnectionRegistry         — resolves a connection name to a provider instance
Contracts/
├── PaymentProcessorInterface  — what a driver implements
├── PaymentAdapterInterface    — provider payload ⇄ DTO mapping
├── CustomerContract           — host user model
├── FundsLedger                — host funds system (MT5, wallet, banking core)
├── ResolvesFundingAccount     — which account a deposit funds
├── FeeConfigurationContract   — the host's per-method pricing
├── PreparesChargeData         — a driver shaping its own charge payload
└── ProvidesWebhookTransactionId — correlation id extraction from a callback
Services/
├── PaymentService             — processPayment / processRefund / syncTransaction
└── Webhooks/{WebhookProcessor, ReplayGuard, WebhookRelay}
Withdrawals/WithdrawalWorkflow — request / approve / reject / markPaid / cancel
Fees/{FeeCalculator, FeeBreakdown, SettledAmount, SettledAmountResolver}
Drivers/{Aps, Jenapay, Heropayment, Payport, Sticpay, Myfatoorah, Xoala, Internal}
Http/Controllers/Webhooks/*    — one per bundled driver, all sharing the pipeline
Jobs/{ProcessPaymentProviderWebhook, RelayWebhook}
Models/{Transaction, Refund, WebhookEvent, AdminAction}
Support/{TransferClaim, PayloadRedactor, PayloadSanitizer, NullLedger, PspHttp}
Testing/{CashierFake, FakeProvider, FakeLedger, WebhookSimulator, SignedWebhook}
Casts/EncryptedArray           — writes obey the flag; reads accept ciphertext AND cleartext
```

## Connections vs drivers

This is the single most important distinction in v2.

- A **driver** is an integration: `aps`, `payport`, `paytiko`. It is the plain string persisted to
  `transactions.provider`.
- A **connection** is one PSP *account* using that driver, with its own credentials and routing. It
  is persisted to `transactions.connection`.

Two APS merchant accounts are two connections (`aps`, `aps_binance`) sharing one driver (`aps`).
Correlation stays coarse on the driver so webhooks find their transaction regardless of account;
refunds and syncs use the exact account that took the charge.

```php
// config/cashier-core.php
'default_connection' => env('CASHIER_DEFAULT_CONNECTION', 'manual'),

'connections' => [
    'aps' => [
        'driver' => 'aps',
        'base_url' => env('APS_BASE_URL'),
        'merchant_guid' => env('APS_MERCHANT_GUID'),
        'app_token' => env('APS_APP_TOKEN'),
        'app_secret' => env('APS_APP_SECRET'),
        'callback_secret' => env('APS_CALLBACK_SECRET'),  // signs callbacks; not the app secret
        'deposit_method' => env('APS_DEPOSIT_METHOD'),    // charge() throws without it
        // Optional: redirect_url, webhook_url (default to the payment.success /
        // webhooks.aps routes) and checkout_host_map (card URL host rewrite).
    ],
    'aps_binance' => ['driver' => 'aps', /* second merchant account's creds */],
    // MyFatoorah is one API key per COUNTRY, sent to that country's host.
    // A merchant trading in two countries is two connections.
    'myfatoorah' => [
        'driver' => 'myfatoorah',
        'base_url' => env('MYFATOORAH_BASE_URL'),
        'api_key' => env('MYFATOORAH_API_KEY'),
        'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
        'currency' => 'KWD',            // MyFatoorah cannot charge USD
        'payment_method' => 'KNET',     // omit for MyFatoorah's own picker
    ],
    // Xoala's Standard Checkout is entered by a browser form POST, not a URL —
    // the package serves a signed bridge page that submits it for you.
    'xoala' => [
        'driver' => 'xoala',
        'base_url' => env('XOALA_BASE_URL'),
        'member_id' => env('XOALA_MEMBER_ID'),
        'secure_key' => env('XOALA_SECURE_KEY'),
        'totype' => env('XOALA_TOTYPE'),          // 2nd checksum field; wrong value fails silently
        'username' => env('XOALA_USERNAME'),      // optional; sent as merchant.username for the auth token retrieve() needs
        'transaction_type' => env('XOALA_TRANSACTION_TYPE', 'DB'), // DB authorizes+captures; PA holds funds
    ],
],

'drivers' => [],   // bundled + plugin drivers merge in automatically
```

Resolution order for a connection's provider class: the connection's own `class` key → the
`drivers` map (host entry wins over plugin wins over bundled) → `ProcessorNotFoundException`.

```php
Connections::names();                 // ['aps', 'aps_binance', ...]
Connections::driverFor('aps_binance');// 'aps'
Connections::forDriver('aps');        // ['aps' => [...], 'aps_binance' => [...]]
Connections::currencies('heropayment');

app(ConnectionRegistry::class)->get('aps_binance');   // PaymentProcessorInterface
```

**Never** new up a provider class directly and never persist a driver string that did not come from
`Connections::driverFor()`.

## Host integration (four seams)

```php
// 1. Customer model — required
class User extends Authenticatable implements CustomerContract
{
    public function cashierId(): int|string { return $this->id; }
    public function cashierEmail(): string { return $this->email; }
    public function cashierName(): string { return $this->name; }
    public function cashierLocale(): string { return $this->locale ?? 'en'; }
}

// 2. Funds system — required for real money movement.
//    Unbound, the default NullLedger refuses every movement loudly rather than
//    pretending funds moved.
$this->app->singleton(FundsLedger::class, Mt5FundsLedger::class);

// 3. Funding-account resolution — optional. The default honors an explicit
//    `trading_account_login` and nothing else; bind your own when deposits name
//    the account indirectly (by reference).
$this->app->singleton(ResolvesFundingAccount::class, TradingAccountResolver::class);

// 4. Models — optional host subclass of the package Transaction
'models' => ['transaction' => \App\Models\Transaction::class, 'customer' => \App\Models\User::class],
```

`FundsLedger` is four methods: `credit()`, `debit()`, `correct()`, `refresh()`. `credit`/`debit`/
`correct` return a `?LedgerTicket` (null = refused). An ambiguous failure (timeout) must **throw**,
not return null — the engine leaves the transfer claim in place on a throw rather than risking a
double credit.

## Charging

```php
$result = app(PaymentService::class)->processPayment(
    customer: $user,                    // CustomerContract
    paymentData: ['amount' => 100, 'trading_account_login' => 555555],
    connection: 'aps',                  // connection name — NOT a driver
    feeConfiguration: $paymentMethod,   // FeeConfigurationContract, or null
    selectedMethodAttributes: [],       // payment_method_* columns the user picked
);

if ($result->requiresAction()) {
    return redirect($result->getRedirectUrl());
}
```

What `processPayment()` does, in order:

1. Resolves the provider from the connection; derives the driver string.
2. Merges `user_id` / `user_email` into `paymentData['metadata']`.
3. Resolves the ledger account via `ResolvesFundingAccount` and exposes it as
   `metadata.trading_account_login`.
4. Pins `currency` to `cashier-core.currency.default` — **callers cannot choose a currency**; a
   driver that charges in a fixed currency overrides it in `prepareChargeData()`.
5. Resolves fees via `FeeCalculator` and hands the PSP the **grossed-up** amount, so the customer's
   deposit survives them. From here on `$result->amount` is the *requested* figure, not the deposit.
6. Calls the driver's `PreparesChargeData::prepareChargeData()` hook if implemented.
7. Charges, sanitizes + redacts the response payload, persists the transaction with its fee
   snapshot, dispatches `ChargeCreated`.

The transaction carries an immutable fee snapshot: `requested_amount`, `charged_amount`,
`psp_fee_amount`, `markup_amount`, `settlement_mode` (`Added` | `Deducted` | `Invoiced`).

**Never add a per-provider branch to `PaymentService`.** That is exactly what `PreparesChargeData`
exists to prevent.

## Webhook pipeline

Routes: `POST {prefix}/{driver}` for each bundled driver, registered from `routes/webhooks.php`
under the `cashier-core.routes` group (prefix, middleware, `without_middleware`, `name_prefix`).
Disable with `Cashier::ignoreRoutes()` or `routes.enabled = false`.

```
POST api/webhooks/{driver}
  → {Driver}WebhookController (EnforcesSignatureVerification)
      1. signature verified against EVERY configured connection of that driver;
         the matching account identifies the sender and rides with the job
      2. ReplayGuard claims sha256(driver, signature, body) in cashier_webhook_events
         → duplicate: return the provider's expected ACK, dispatch nothing
      3. WebhookRelay (optional, per driver) forwards the RAW body verbatim
      4. ProcessPaymentProviderWebhook::dispatch() on the `payments` queue
           (ShouldBeUnique on driver + payload hash; $tries + backoff)
  → WebhookProcessor::process(driver, providerTransactionId, TransactionWebhookUpdate)
      5. correlate on (provider, provider_transaction_id), bypassing host tenant
         scopes but NEVER SoftDeletes
  → WebhookProcessor::applyUpdate()
      6. re-read under lock; duplicate-status and out-of-order guards
      7. amount/currency assertion → PaymentStatus::OnHold + DepositHeldForReview
         when the reported amount deviates beyond webhooks.amount_tolerance_percent
      8. FundsLedger::credit() gated on an atomic TransferClaim
      9. events dispatched — the host does the mail, the CRM, the admin alerting
```

Key invariants:

- **Signature verification is non-disableable in production.** `verify_signature=false` is honored
  only outside production; in production the controller refuses the delivery and logs critical.
- **The replay guard claims before dispatching**, so a duplicate is never relayed twice either.
- **Only the delivery that actually changes the status runs side effects.** A retry that lands on an
  already-Succeeded row is ignored, not re-credited.
- **The relay forwards the raw body under its original Content-Type.** Re-encoding a parsed array
  changes key order and escaping and breaks any signature the recipient verifies. It falls back to
  re-encoding only when there is no raw body (a request built from parameters).
- Client-controlled rails (crypto) reconcile the deposit to what actually arrived, within
  `settlement.tolerance_percent`, instead of holding.

### Events (the host's extension surface)

| Event | When |
|---|---|
| `ChargeCreated` | charge persisted (hosted-page redirect may follow) |
| `WebhookReceived` / `WebhookRejected` | delivery accepted (redacted payload) / refused |
| `TransactionStatusChanged` | every transition, with `source` (`webhook` \| `sync`) |
| `DepositSucceeded` / `DepositFailed` / `DepositHeldForReview` | deposit outcomes |
| `FundsCredited` / `FundsCreditFailed` | ledger credit outcomes |
| `FeeDriftDetected` | PSP settlement diverges from configured fees |
| `RefundSucceeded` / `RefundFailed` | refund outcomes |
| `WithdrawalRequested/Approved/Rejected/MarkedPaid/Cancelled/DebitFailed` | withdrawal lifecycle |
| `AdminActionRecorded` | every audited admin money action |

## Withdrawals

`WithdrawalWorkflow` — approve-first by default (`withdrawals.approve_first`):

| Method | Effect |
|---|---|
| `request(CustomerContract, WithdrawalRequestData)` | Pending row, duplicate-window guarded, **no ledger movement** |
| `approve(Transaction, Actor, ?ledgerAccount)` | debit under a claim → Processing |
| `reject(Transaction, Actor, string $reason, ?ledgerAccount)` | refunds a taken debit → Failed |
| `markPaid(Transaction, Actor, array $payoutDetails)` | Processing → Succeeded |
| `cancel(Transaction, ?Actor, ?ledgerAccount)` | refunds a taken debit → Canceled |

All admin methods take an `Actor` DTO, write a `cashier_admin_actions` audit row (actor, guard, IP,
before/after status — PCI DSS 10.2) and return a `Result` DTO an admin panel can render directly.
Legacy debit-at-submission is `approve_first = false`.

## Sync (the recovery path)

```php
app(PaymentService::class)->syncTransaction($transaction);   // bool
```

Calls `retrieve()` on the provider resolved from the **stored connection**, maps the `PaymentResult`
to a `TransactionWebhookUpdate`, and applies it through `applyUpdate(source: 'sync')`. A sync that
recovers a missed success fires the deposit events and credits the ledger exactly as the webhook
would — which is why it is the recovery tool for drivers with no resync endpoint.

## Building a driver

1. **Client** — `Drivers\{Name}\{Name}Client`: the HTTP surface. Use `Support\PspHttp` for the
   shared timeout/retry posture. Keep a raw-body signing helper available.
2. **Signature service** (if the PSP signs) — `{Name}SignatureService`. Document the exact field
   order per operation in the docblock; a wrong order yields a well-formed hash the PSP rejects.
3. **Adapter** — `{Name}Adapter implements PaymentAdapterInterface`. Document the provider's raw
   status vocabulary in `mapStatus()`. If poll and callback vocabularies differ, add a separate
   `mapCallbackStatus()` rather than overloading one map.
4. **Provider** — `{Name}Provider implements PaymentProcessorInterface`, plus:
   - `ProvidesWebhookTransactionId` — **the correlation seam**. A provider whose callback names the
     id differently from its charge response *must* implement this or its webhooks never find their
     transaction.
   - `PreparesChargeData` — if it needs routing hints, a fixed currency, or a billing shape.
   Constructor takes `array $config = []` (the connection config) and throws
   `PaymentProcessingException` when credentials are missing. Throw `\BadMethodCallException` for
   unsupported operations and report that honestly through `supports()`.
5. **Register** — add the driver string to `BUNDLED_DRIVERS` (in-package) or append to
   `cashier-core.drivers` from a plugin service provider, with the host's entry winning.
6. **Webhook controller** — `Http/Controllers/Webhooks/{Name}WebhookController`, using
   `EnforcesSignatureVerification`. Verify against **every** configured connection of the driver,
   claim the replay guard, then dispatch. Add the route to `routes/webhooks.php`.
7. **Tests** — unit adapter + signature service (checked against a vendor reference implementation
   reproduced in the test, not against the service itself), plus a webhook feature test covering:
   valid signature, invalid signature, tampered field, unknown key, duplicate replay, out-of-order
   after Succeeded, and unknown transaction.

### Contract signatures

```php
interface PaymentProcessorInterface {
    public function charge(array $data): PaymentResult;
    public function refund(string $transactionId, ?float $amount = null): RefundResult;   // major units
    public function capture(string $transactionId, ?float $amount = null): PaymentResult; // major units
    public function authorize(array $data): PaymentResult;
    public function void(string $transactionId): PaymentResult;
    public function retrieve(string $transactionId): ?PaymentResult;
    public function getPaymentStatus(string $transactionId): string;
    public function validatePaymentData(array $data): array;
    public function parseWebhook(array $payload): TransactionWebhookUpdate;
    public function verifyWebhookSignature(array $payload, string $signature): bool;
    public function getName(): string;
    public function supports(string $feature): bool;
}

interface PaymentAdapterInterface {
    public function fromProviderResponse(mixed $response): PaymentResult;
    public function fromProviderPayload(string $transactionId, array $payload): PaymentResult;
    public function fromWebhook(array $payload): TransactionWebhookUpdate;
    public function mapStatus(mixed $providerStatus): PaymentStatus;
    public function getProviderName(): string;
}

interface PreparesChargeData {
    public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array;
}

interface ProvidesWebhookTransactionId {
    public function extractWebhookTransactionId(array $payload): ?string;
}

interface FeeConfigurationContract {
    public function settlementMode(): SettlementMode;
    public function feePercentage(): float;
    public function feeFixed(): float;
    public function markupPercentage(): float;
    public function markupFixed(): float;
    public function feeConfigurationId(): int|string|null;
}
```

There is no `toProviderFormat()` — outgoing payload construction lives in the provider's `charge()`.

## DTOs

All `readonly` with promoted properties, in `Asciisd\CashierCore\DataObjects`.

```php
new PaymentResult(
    success: bool, transactionId: string, status: PaymentStatus,
    amount: int,                   // still int — see the 2.1 note under Refunds
    currency: string, message: ?string,
    metadata: ?array,              // 'redirect_url' for hosted pages
    processorResponse: mixed, errorCode: ?string,
    paymentMethodSnapshot: ?PaymentMethodSnapshot,
);  // isSuccessful() isFailed() requiresAction() getRedirectUrl() toArray()

new TransactionWebhookUpdate(
    status: PaymentStatus, processorResponse: array,
    paymentMethodSnapshot: ?PaymentMethodSnapshot, metadata: ?array,
    errorCode: ?string, errorMessage: ?string,
    amount: ?float,                // float since 2.1, major units
    currency: ?string, description: ?string,
    additionalAttributes: array,
);  // carries NO transaction id — correlation is ProvidesWebhookTransactionId's job

new RefundResult(
    success: bool, refundId: string, originalTransactionId: string,
    status: RefundStatus,
    amount: float,                 // float since 2.1, major units
    currency: string, message: ?string, metadata: ?array,
    processorResponse: ?string, errorCode: ?string,
);  // isSuccessful() isFailed() toArray()

new LedgerTicket(...);          // returned by FundsLedger movements
new Actor(...);                 // admin identity for the audit trail
new Result(...);                // workflow outcome for admin panels
```

Note the argument order on `PaymentResult`: `transactionId` comes **before** `status`, and the raw
payload field is `processorResponse` (not `providerPayload`). Mind the mixed units: `PaymentResult`
carries minor units while `RefundResult` and `TransactionWebhookUpdate` carry major.

## Enums

| Enum | Cases |
|---|---|
| `PaymentStatus` | Pending, Processing, Succeeded, Failed, Canceled, **OnHold**, RequiresAction, RequiresCapture, RequiresConfirmation, RequiresPaymentMethod |
| `TransactionType` | Deposit, Withdrawal, Refund, Bonus, TransferFrom, TransferTo |
| `SettlementMode` | Added, Deducted, Invoiced |
| `RefundStatus` | Pending, Processing, Succeeded, Failed, Canceled |
| `PaymentMethodType` / `PaymentMethodBrand` | card/wallet/crypto taxonomy for the method snapshot |

**There is no `Refunded` payment status.** Refund and void callbacks map to `Canceled`.
`OnHold` is v2's addition — a success the engine refused to credit pending review.

## Security posture (PCI DSS v4)

- Hosted-redirect drivers only; `PayloadSanitizer` strips card-shaped keys before anything is
  persisted (3.2.1, 4.2.1 — SAQ-A preserved).
- `provider_payload` / `withdrawal_details` are encrypted at rest via `Casts\EncryptedArray`,
  toggleable under `security.*` (3.4.1, 3.5.1). Reads accept **both** ciphertext and legacy
  cleartext — the fallback keys on "raw value is valid JSON", not on "decryption failed", so a
  key-rotation break still throws. That inverts the deploy order: flip encryption on first, then
  backfill behind it with `cashier:encrypt-historical`.
- `PayloadRedactor` runs on every logged payload and URL (10.2). **Matching is case-insensitive
  with a trailing wildcard only** — a PascalCase PSP key (`ClientIP`) needs its own lowercase
  entry (`clientip`); `client_ip` will not match it.
- Signature verification non-disableable in production; replay protection in
  `cashier_webhook_events` (4.2.1, 6.2.4).
- `cashier_admin_actions` audits every admin money action (10.2.1–10.3).
- `cashier:purge` enforces retention on payloads and replay rows (3.2.1/3.3).
- The package never holds ledger credentials — the host binds `FundsLedger` (7.x least privilege).

## Testing

```php
// 1. Fake connections — charges record in-process, nothing reaches a PSP.
$fake = Cashier::fake(['aps']);
$fake->whenCharging('aps', $declinedResult);
$fake->whenRetrieving('aps', $result);
$fake->assertCharged();
$fake->assertChargedOn('aps', fn (array $data) => $data['amount'] === 100);
$fake->assertChargedCount(1);
$fake->assertNothingCharged();
$fake->assertRefunded();

// 2. Real drivers without sandbox secrets — driver-appropriate test credentials.
//    The driver is inferred from the name's prefix, so this yields a second APS account.
Cashier::fakeConnection('aps_binance', ['callback_secret' => 'other']);

// 3. Webhooks signed exactly as the PSP would sign them.
$delivery = WebhookSimulator::make('aps', $payload, connection: 'aps_binance');
$this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

// 4. Ledger assertions without a real funds system.
app()->instance(FundsLedger::class, $ledger = new FakeLedger);
$ledger->assertMoved('credit', fn (array $m) => $m['amount'] === 100.0);
$ledger->assertMovedCount(1);
$ledger->assertNothingMoved();
$ledger->refuseAll();            // simulate a refusing ledger
$ledger->throwOnNextCall();      // simulate an ambiguous failure
```

Traps worth knowing:

- **`$connection` collides with `Queueable`.** The queued webhook job threads the matched connection
  as `connectionName`, not `$connection`.
- **Testbench:** leaving `$app['env'] = 'production'` at teardown turns Testbench's
  `migrate:rollback` prompt into a mock. Restore the env in a `finally`.
- `Command::warn()` / `Command::fail()` are public on `Illuminate\Console\Command` — a private
  helper named `warn`/`fail` is a fatal. The console commands use `postureWarn`/`checkFail`.

## Artisan commands

| Command | Purpose |
|---|---|
| `cashier:install [--migrate]` | Publish config, optionally migrate, print the integration checklist |
| `cashier:check` | Doctor: connections resolve, security posture, unique index, customer model. FAILs on unresolvable connections / missing default / missing unique index / bad customer model; WARNs on NullLedger, encryption off, signature verification off (FAIL in production). Non-zero exit for CI |
| `cashier:publish [--config] [--migrations] [--force]` | Publish assets |
| `cashier:purge [--dry-run]` | Enforce payload / webhook-event retention |
| `cashier:encrypt-historical [--dry-run] [--chunk] [--column] [--skip-sanitize] [--force]` | One-off backfill: encrypt + sanitize payloads written before encryption was on. Chunked and resumable; re-running is a no-op. Sanitizes `provider_payload` only — **never** `withdrawal_details`, whose IBAN the payout needs |

`cashier:check` has a known design gap: a connection declared ahead of its credentials fails the
check permanently. Either supply credentials or don't declare the connection until it goes live.

## Conventions

- **Never** resolve a provider outside `ConnectionRegistry`.
- **Never** persist a driver string that did not come from `Connections::driverFor()`.
- **Never** bypass `WebhookProcessor::applyUpdate()` to change a transaction's status — it owns the
  lock, the guards, the OnHold assertion and the ledger claim.
- **Never** add `SoftDeletes` to `Transaction::cashierBypassedScopes()`; a deleted transaction must
  not move funds.
- **Never** add a per-provider branch to `PaymentService` — implement `PreparesChargeData` instead.
- **Never** persist or log a raw PSP payload without the sanitizer/redactor.
- **Never** write `cashier_webhook_events` or `cashier_admin_actions` by hand.
- Use `Logging\PaymentLogger` / `Logging\TransactionLogger`, never the `Log::` facade. Both resolve
  the channel with `Log::getFacadeRoot()` when unconfigured — `Log::channel(null)` returns null
  under a host's `Log::shouldReceive()` and every log line then fatals on the return type.
- The host **must run a worker for the `payments` queue** (`cashier-core.queue.queue`). Horizon
  never warns about an unconsumed queue — every webhook job would sit unread and no deposit would
  ever be credited. Keep the worker `timeout` **below** `queue.connections.*.retry_after`, or a
  killed worker's job is released and the credit path runs twice.

## Refunds (2.1)

```php
$result = app(PaymentService::class)->processRefund(
    $transaction->provider_transaction_id,
    95.50,              // major units; omit for the outstanding balance
    'customer request',
);
```

`processRefund()` **reserves before it calls out**:

1. Lock the transaction row, sum refunds in `succeeded|pending|processing`, refuse anything
   exceeding the remainder (or `<= 0`).
2. Insert a `pending` `Models\Refund` row, then **release the lock** — the PSP call happens outside
   it, so a slow gateway cannot block webhooks for that transaction.
3. Write the outcome back. A refusal or a thrown `PaymentProcessingException` marks the row `failed`,
   which releases the balance again; without that an outage would permanently consume the customer's
   remaining refundable amount.

In-flight attempts count against the balance, so two requests moments apart cannot both pass.
`RefundSucceeded` / `RefundFailed` carry the `Refund` as an optional second constructor argument and
are dispatched from here.

### ⚠️ 2.0 → 2.1 breaking change: refund/capture amounts are `float`

```diff
-public function refund(string $transactionId, ?int $amount = null): RefundResult
+public function refund(string $transactionId, ?float $amount = null): RefundResult

-public function capture(string $transactionId, ?int $amount = null): PaymentResult
+public function capture(string $transactionId, ?float $amount = null): PaymentResult
```

`RefundResult::$amount` and `TransactionWebhookUpdate::$amount` widen with them. Units are **major**,
matching `transactions.amount` — a $95.50 refund is `95.5`. Every bundled driver already cast the int
to float before sending, so the wire format is unchanged, but the parameter truncated on the way in
and a $95.50 refund reached the PSP as $95; `TransactionWebhookUpdate::$amount` likewise truncated a
webhook-reported 95.50 to 95 *before* the OnHold tolerance comparison ran against a decimal column.

Callers passing `int` keep working. **Custom drivers must update their signatures**, and strict
`toBe(100)` assertions on those properties become `toBe(100.0)`.
`PaymentResult::$amount` is deliberately still `int` — a wider change for another release.

### `Models\Refund`

Swappable via `cashier-core.models.refund` / `Cashier::refundModel()`; table from
`database.tables.refunds`. Columns: `transaction_id`, `provider_refund_id`, `amount` (decimal, major
units), `currency`, `status` (`RefundStatus`), `reason`, `metadata`, `provider_payload`,
`processed_at`, `failed_at`. Scopes: `successful()`, `failed()`, `pending()`, `byAmount()`,
`byCurrency()`.

**A host whose `cashier_refunds` table came from a 1.x migration must migrate it** —
`processor_refund_id` → `provider_refund_id`, `processor_response` → `provider_payload`,
`id`/`transaction_id` uuid → bigint, `amount` minor → major units. See `UPGRADE-2.1.md`.

> Historical note: in 2.0 nothing wrote a refund row, so
> `$transaction->refunds()->sum('amount')` — the natural basis for a "can this still be refunded?"
> guard — always returned 0 and the same transaction could be refunded repeatedly. If a host built
> such a guard against 2.0, re-read it against 2.1.
