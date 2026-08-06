## Cashier Core (asciisd/cashier-core) — v2

A complete payment engine for Laravel: connection-based charge orchestration, a hardened webhook
pipeline (signature enforcement, replay guard, row-locked transitions, amount-deviation holds), an
approve-first withdrawal workflow with an audit trail, and a `FundsLedger` contract that keeps the
host's funds system (MT5, wallet, banking core) behind an interface the host owns.

Five direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport, Sticpay** — plus
internal `manual`, `bank_transfer` and `crypto` providers. All are hosted-redirect: no PAN or CVV
touches the application (SAQ-A posture). Paytiko and KNET are separate plugins
(`asciisd/cashier-paytiko`, `asciisd/knet`) that register their drivers into this core.

### v1 → v2: what is gone

**Do not use these — they no longer exist.** If you see them in code, it is pre-2.0 and must be migrated.

- `PaymentFactory` facade / `PaymentFactoryInterface` — replaced by `ConnectionRegistry` + `Connections`.
- `config('cashier-core.processors')` — replaced by `connections` (PSP accounts) + `drivers` (driver string → class).
- `TransactionService` — replaced by `PaymentService` (charge/refund/sync) and `Services\Webhooks\WebhookProcessor` (webhook application).
- `cashier_transactions` / `cashier_payment_methods` tables — v2 owns `transactions`, `refunds`, `cashier_webhook_events`, `cashier_admin_actions`.
- `processor_*` column names — the columns are `provider`, `connection`, `provider_transaction_id`, `provider_payload`.

### Connections vs drivers (read before touching any charge path)

A **driver** is an integration (`aps`, `payport`, `paytiko`). A **connection** is one PSP *account*
using that driver, with its own credentials and routing. Two APS merchant accounts are two
connections sharing one driver.

- `transactions.provider` stores the **driver** string (coarse — keeps webhook correlation stable).
- `transactions.connection` stores the **connection** name (exact — refunds and syncs reuse the account that took the charge).
- Host payment methods must name a **connection**, never a driver.

@verbatim
<code-snippet name="Named connections" lang="php">
// config/cashier-core.php
'default_connection' => env('CASHIER_DEFAULT_CONNECTION', 'manual'),

'connections' => [
    'aps' => [
        'driver' => 'aps',                       // persisted to transactions.provider
        'merchant_guid' => env('APS_MERCHANT_GUID'),
        'app_token' => env('APS_APP_TOKEN'),
        'app_secret' => env('APS_APP_SECRET'),
        'callback_secret' => env('APS_CALLBACK_SECRET'),
    ],
    'aps_binance' => ['driver' => 'aps', /* second merchant account */],
],
</code-snippet>
@endverbatim

Bundled drivers resolve automatically. A connection with a `class` key overrides the driver map;
plugins append their drivers to `cashier-core.drivers` (a host entry always wins).

### Host integration seams

@verbatim
<code-snippet name="The four host seams" lang="php">
// 1. Customer model
class User extends Authenticatable implements \Asciisd\CashierCore\Contracts\CustomerContract
{
    public function cashierId(): int|string { return $this->id; }
    public function cashierEmail(): string { return $this->email; }
    public function cashierName(): string { return $this->name; }
    public function cashierLocale(): string { return $this->locale ?? 'en'; }
}

// 2. Funds system — REQUIRED. Without it the default NullLedger refuses every
//    movement loudly rather than pretending funds moved.
$this->app->singleton(FundsLedger::class, Mt5FundsLedger::class);

// 3. Optional: which account a deposit funds (resolved by reference, etc.)
$this->app->singleton(ResolvesFundingAccount::class, TradingAccountResolver::class);

// 4. Optional: host transaction model extending the package base model
'models' => ['transaction' => \App\Models\Transaction::class, 'customer' => \App\Models\User::class],
</code-snippet>
@endverbatim

### Charging

@verbatim
<code-snippet name="Charging a connection" lang="php">
use Asciisd\CashierCore\Services\PaymentService;

$result = app(PaymentService::class)->processPayment(
    customer: $user,                    // CustomerContract
    paymentData: ['amount' => 100, 'trading_account_login' => 555555],
    connection: 'aps',                  // connection name, not driver
    feeConfiguration: $paymentMethod,   // FeeConfigurationContract, or null
);

if ($result->requiresAction()) {
    return redirect($result->getRedirectUrl());
}
</code-snippet>
@endverbatim

- Fees resolve through `FeeCalculator` **before** charging; the PSP is handed the grossed-up amount.
  The transaction stores the customer's deposit in `amount` plus an immutable snapshot
  (`requested_amount`, `charged_amount`, `psp_fee_amount`, `markup_amount`, `settlement_mode`).
- Drivers needing to shape their own payload (routing hints, fixed currency, billing shape)
  implement `PreparesChargeData` — **the orchestrator carries no per-provider branches**. Never add one.
- The currency always comes from `cashier-core.currency.default`; callers cannot pick one. A driver
  that charges in a fixed currency overrides it in `prepareChargeData()`.

### Webhooks

The package registers `POST {prefix}/{driver}` for each bundled driver (default prefix
`api/webhooks`; group configurable under `cashier-core.routes`; disable with `Cashier::ignoreRoutes()`).
Every delivery goes through one pipeline:

1. **Signature verification** against each configured account of that driver — the matching account
   identifies the sender and rides with the queued job. `verify_signature=false` is honored only
   outside production; in production the controller refuses and logs critical.
2. **Replay guard** — a digest of (driver, signature, body) is claimed in `cashier_webhook_events`;
   a duplicate gets the provider's expected ACK without dispatching.
3. **Queued processing** on the dedicated `payments` queue, `ShouldBeUnique` on driver + payload hash.
4. **Row-locked transition** — correlation bypasses host tenant scopes but **never SoftDeletes**;
   guards re-read under lock; only the delivery that changes status runs side effects.
5. **Amount assertion** — a success deviating from the invoice beyond
   `webhooks.amount_tolerance_percent` (or with a currency mismatch) becomes `PaymentStatus::OnHold`:
   no credit, no success events, `DepositHeldForReview` instead. Client-controlled rails (crypto)
   reconcile to what actually arrived, within `settlement.tolerance_percent`.
6. **Ledger credit** under an atomic `TransferClaim` — two racing processes cannot both credit, and
   an ambiguous failure (timeout) leaves the claim in place rather than risking a double credit.

Side effects belong to the host: the package sends no mail and touches no CRM. Listen for events
(`ChargeCreated`, `WebhookReceived`/`WebhookRejected`, `TransactionStatusChanged`,
`DepositSucceeded`/`DepositFailed`/`DepositHeldForReview`, `FundsCredited`/`FundsCreditFailed`,
`FeeDriftDetected`, `RefundSucceeded`/`RefundFailed`, the `Withdrawal*` lifecycle, `AdminActionRecorded`).

### Withdrawals

`WithdrawalWorkflow` is the approve-first state machine: `request()` creates a Pending row
(duplicate-window guarded) without touching the ledger; `approve()` debits under a claim and moves
to Processing; `markPaid()`, `reject()` and `cancel()` finish the lifecycle, refunding the debit
where one was taken. Every admin action writes a `cashier_admin_actions` row (actor, guard, IP,
before/after status — PCI DSS 10.2) and returns a `Result` DTO. Legacy debit-at-submission is one
flag: `withdrawals.approve_first = false`.

### Sync

`PaymentService::syncTransaction($transaction)` pulls the provider's current state using the
**stored connection's** credentials and applies it through the same webhook pipeline — a sync that
recovers a missed success fires the deposit events and credits the ledger exactly as the webhook would.

### Conventions

- **Never** resolve a provider by newing it up. Go through `ConnectionRegistry::get($connection)`.
- **Never** persist a driver string that is not the connection's `driver` — `Connections::driverFor()` is the only source.
- **Never** bypass `WebhookProcessor::applyUpdate()` to change a transaction's status. It owns the lock, the guards, the OnHold assertion and the ledger claim.
- **Never** include `SoftDeletes` in `Transaction::cashierBypassedScopes()` — a deleted transaction must not move funds.
- **Never** log or persist a raw PSP payload without the sanitizer/`PayloadRedactor`. `redact_keys` matches case-insensitively with a **trailing wildcard only**, so a PascalCase PSP key (`ClientIP`) needs its own lowercase entry (`clientip`).
- **Never** hand-write to `cashier_webhook_events` or `cashier_admin_actions`; `ReplayGuard` and `WithdrawalWorkflow` own them.
- Use `Logging\PaymentLogger` / `Logging\TransactionLogger`, not the `Log::` facade.

### Testing seams

@verbatim
<code-snippet name="Testing an integration" lang="php">
$fake = Cashier::fake(['aps']);                  // charges record in-process
$fake->whenCharging('aps', $declinedResult);
$fake->assertChargedOn('aps', fn ($data) => $data['amount'] === 100);

Cashier::fakeConnection('aps_binance');          // real driver, test credentials

$delivery = WebhookSimulator::make('aps', $payload, connection: 'aps_binance');
$this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

app()->instance(FundsLedger::class, $ledger = new FakeLedger);
$ledger->assertMoved('credit', fn ($m) => $m['amount'] === 100.0);
</code-snippet>
@endverbatim

### Artisan commands

| Command | Purpose |
|---|---|
| `cashier:install` | Publish config, optionally migrate, print the integration checklist |
| `cashier:check` | Doctor: connections, security posture, indexes, bindings — non-zero exit for CI |
| `cashier:publish` | Publish config and/or migrations |
| `cashier:purge` | Enforce payload / webhook-event retention (`--dry-run`) |
| `cashier:encrypt-historical` | One-off: encrypt + sanitize payloads written before encryption was on |

### Enums

- `PaymentStatus` — Pending, Processing, Succeeded, Failed, Canceled, **OnHold**, RequiresAction, RequiresCapture, RequiresConfirmation, RequiresPaymentMethod. **There is no `Refunded` case** — refund/void callbacks map to `Canceled`.
- `TransactionType` — Deposit, Withdrawal, Refund, Bonus, TransferFrom, TransferTo.
- `SettlementMode` — Added, Deducted, Invoiced.
- `RefundStatus`, `PaymentMethodType`, `PaymentMethodBrand`.

### Config

`config/cashier-core.php`: `default_connection`, `connections`, `drivers`, `models`, `routes`,
`queue` (defaults to the `payments` queue — **the host must run a worker for it**), `currency`,
`limits`, `settlement`, `withdrawals`, `webhooks` (incl. `relay` per driver), `security`
(encryption, `redact_keys`, retention), `database`, `logging`.

### Known gap

Refunds do not persist a `Models\Refund` row — `PaymentService::processRefund()` returns a DTO and
nothing writes the table, whose columns also disagree with the package's own refunds migration.
Any "already refunded" guard reading `$transaction->refunds()->sum('amount')` therefore always
reads 0. Do not build on the refund persistence path until it is reconciled.
