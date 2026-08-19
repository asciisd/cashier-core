# Cashier Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/asciisd/cashier-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/cashier-core)
[![Total Downloads](https://img.shields.io/packagist/dt/asciisd/cashier-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/cashier-core)
[![License](https://img.shields.io/packagist/l/asciisd/cashier-core.svg?style=flat-square)](https://packagist.org/packages/asciisd/cashier-core)

A complete, PCI-DSS-aligned payment engine for Laravel: connection-based charge
orchestration, a hardened webhook pipeline (signature enforcement, replay guard,
row-locked transitions, amount-deviation holds), an approve-first withdrawal
workflow with an audit trail, and a ledger contract that keeps your funds system
(MT5, wallet, banking core) behind an interface you own.

Five direct PSP drivers ship bundled — **APS, Jenapay, Heropayment, Payport,
Sticpay** — plus internal `manual`, `bank_transfer` and `crypto` providers. All
of them are hosted-redirect: no PAN or CVV ever touches your application
(SAQ-A posture). Paytiko and KNET remain separate plugins
(`asciisd/cashier-paytiko`, `asciisd/knet`) built on this core.

Upgrading from 1.x? Read [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Requirements

- PHP ^8.3
- Laravel ^11 | ^12 | ^13

## Installation

```bash
composer require asciisd/cashier-core

php artisan cashier:install --migrate
php artisan cashier:check
```

`cashier:check` is the doctor — it validates connections, the security posture,
and the database indexes, and exits non-zero for CI.

## Concepts: connections and drivers

A **driver** is an integration (`aps`, `payport`, ...). A **connection** is one
PSP *account* using that driver — its credentials and routing. Two APS merchant
accounts are two connections sharing one driver. Your payment methods should
name a connection, not a driver; the driver string is what gets persisted to
`transactions.provider`, so webhook correlation stays coarse while refunds and
syncs use the exact account that took the charge (`transactions.connection`).

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
        'callback_secret' => env('APS_CALLBACK_SECRET'),   // signs callbacks; not the app secret
        'deposit_method' => env('APS_DEPOSIT_METHOD'),     // charge() throws without it
        // Optional: default to the `payment.success` / `webhooks.aps` routes.
        'redirect_url' => env('APS_REDIRECT_URL'),
        'webhook_url' => env('APS_WEBHOOK_URL'),
        // Optional: APS returns card checkout URLs on its JSON API host.
        'checkout_host_map' => ['api.pci-gw.com' => 'form.pci-gw.com'],
    ],
    'aps_apple_pay' => [
        'driver' => 'aps',            // same driver, different account
        'base_url' => env('APS_APPLEPAY_BASE_URL'),
        'merchant_guid' => env('APS_APPLEPAY_MERCHANT_GUID'),
        'app_token' => env('APS_APPLEPAY_APP_TOKEN'),
        'app_secret' => env('APS_APPLEPAY_APP_SECRET'),
        'callback_secret' => env('APS_APPLEPAY_CALLBACK_SECRET'),
        'deposit_method' => env('APS_APPLEPAY_DEPOSIT_METHOD'),
    ],
],
```

APS issues a separate merchant account per product, so a second APS method is a
second *account* — and adding one is configuration only: no driver code, no new
route, no webhook registration. The accounts share the single `webhooks.aps`
URL because APS callbacks carry no merchant identifier and the sender is found
by trying each account's `callback_secret` until one verifies. An account's
`deposit_method` guid comes from `GET /api/v3/{merchantGuid}/info` on that
account.

Bundled drivers resolve automatically; a connection with a `class` key
overrides the driver map, and plugins append their drivers to
`cashier-core.drivers`.

## Host integration

The package never assumes your user model, your account model, or your funds
system. You plug in through four seams:

```php
// 1. Your user model
class User extends Authenticatable implements \Asciisd\CashierCore\Contracts\CustomerContract
{
    public function cashierId(): int|string { return $this->id; }
    public function cashierEmail(): string { return $this->email; }
    public function cashierName(): string { return $this->name; }
    public function cashierLocale(): string { return $this->locale ?? 'en'; }
}

// 2. Your funds system — without this binding, the default NullLedger
//    refuses every movement loudly instead of pretending funds moved.
$this->app->singleton(FundsLedger::class, Mt5FundsLedger::class);

// 3. Optional: resolve which account a deposit funds (by reference, etc.).
$this->app->singleton(ResolvesFundingAccount::class, TradingAccountResolver::class);

// 4. Optional: your own transaction model extending the package base model.
'models' => [
    'transaction' => \App\Models\Transaction::class,
    'customer' => \App\Models\User::class,
],
```

## Charging

```php
use Asciisd\CashierCore\Services\PaymentService;

$result = app(PaymentService::class)->processPayment(
    customer: $user,
    paymentData: ['amount' => 100, 'trading_account_login' => 555555],
    connection: 'aps',
    feeConfiguration: $paymentMethod,   // implements FeeConfigurationContract, or null
);

if ($result->requiresAction()) {
    return redirect($result->getRedirectUrl());
}
```

- Fees are resolved by `FeeCalculator` before charging and the PSP is handed
  the grossed-up amount; the transaction stores the customer's deposit in
  `amount` plus an immutable fee snapshot (`requested_amount`,
  `charged_amount`, `psp_fee_amount`, `markup_amount`, `settlement_mode`).
- Drivers that need to shape their own payload (routing hints, fixed
  currencies, billing shape) implement `PreparesChargeData` — the orchestrator
  carries no per-provider branches.
- The charge response payload is sanitized and redacted before persistence,
  and `ChargeCreated` is dispatched with the persisted transaction.

## Webhooks

The package registers `POST {prefix}/{driver}` for every bundled driver
(default prefix `api/webhooks`, group configurable under
`cashier-core.routes`; disable with `Cashier::ignoreRoutes()`).

Every delivery passes through the same hardened pipeline:

1. **Signature verification** against each configured account of the driver —
   the matching account identifies the sender, and the matched connection rides
   with the queued job. Setting `verify_signature=false` is honored only
   outside production; in production the controllers refuse the delivery and
   log critical.
2. **Replay guard** — a digest of (driver, signature, body) is claimed in
   `cashier_webhook_events`; a duplicate delivery gets the provider's expected
   ACK without dispatching anything.
3. **Queued processing** on the dedicated `payments` queue with a unique job
   (driver + payload hash).
4. **Row-locked transition** — correlation bypasses your tenant scopes but
   never SoftDeletes, guards run against a locked re-read, and only the one
   delivery that changes the status runs side effects.
5. **Amount assertion** — a success whose reported amount deviates from the
   invoice beyond `webhooks.amount_tolerance_percent` (or whose currency
   mismatches) becomes `PaymentStatus::OnHold`: no credit, no success events,
   `DepositHeldForReview` instead. Client-controlled rails (crypto) reconcile
   to what actually arrived instead.
6. **Ledger credit** under an atomic claim (`TransferClaim`) — two racing
   processes cannot both credit, and an ambiguous failure (timeout) leaves the
   claim in place rather than risking a double credit.

Side effects are yours: the package sends no mail and touches no CRM. Listen
for the events:

| Event | When |
|---|---|
| `ChargeCreated` | charge persisted (hosted-page redirect may follow) |
| `WebhookReceived` / `WebhookRejected` | delivery accepted (redacted payload) / refused |
| `TransactionStatusChanged` | every status transition, with source (`webhook`/`sync`) |
| `DepositSucceeded` / `DepositFailed` / `DepositHeldForReview` | deposit outcomes |
| `FundsCredited` / `FundsCreditFailed` | ledger credit outcomes |
| `FeeDriftDetected` | PSP settlement diverges from configured fees |
| `RefundSucceeded` / `RefundFailed` | refund outcomes |
| `WithdrawalRequested/Approved/Rejected/MarkedPaid/Cancelled/DebitFailed` | withdrawal lifecycle |
| `AdminActionRecorded` | every audited admin money action |

## Withdrawals

`WithdrawalWorkflow` implements the approve-first state machine: `request()`
creates a Pending row (duplicate-window guarded) without touching the ledger;
`approve()` debits under a claim and moves to Processing; `markPaid()`,
`reject()` and `cancel()` complete the lifecycle, refunding the debit where one
was taken. Every admin action writes a `cashier_admin_actions` row (actor,
guard, IP, before/after status) — PCI DSS 10.2 — and returns a `Result` DTO
your admin panel can render directly. Legacy debit-at-submission mode is one
config flag (`withdrawals.approve_first = false`).

## Sync

`PaymentService::syncTransaction($transaction)` pulls the provider's current
state (using the stored connection's credentials) and applies it through the
same webhook pipeline — a sync that recovers a missed success also fires the
deposit events and credits the ledger.

## Security posture (PCI DSS v4)

- Hosted-redirect drivers only; the sanitizer strips card-shaped keys before
  any payload is persisted (3.2.1, 4.2.1 — SAQ-A preserved).
- `provider_payload` / `withdrawal_details` are encrypted at rest, toggleable
  via `cashier-core.security` (3.4.1, 3.5.1). The cast reads cleartext rows too,
  so a host adopting the engine on an existing table can turn encryption on
  first and let `cashier:encrypt-historical` catch the back catalogue up.
- `PayloadRedactor` runs on every logged payload and URL (10.2 log hygiene).
- `cashier:purge` enforces retention on payloads and replay-guard rows (3.2.1/3.3).
- Signature verification is non-disableable in production; replay protection
  via `cashier_webhook_events` (4.2.1, 6.2.4).
- `cashier_admin_actions` audit trail on every admin money action (10.2.1–10.3).
- The package never holds ledger credentials — the host binds `FundsLedger`
  (7.x least privilege).

## Testing your integration

```php
// Fake connections: charges record in-process, nothing reaches a PSP.
$fake = Cashier::fake(['aps']);
$fake->whenCharging('aps', $declinedResult);           // queue outcomes
$fake->assertChargedOn('aps', fn ($data) => $data['amount'] === 100);

// Real drivers without sandbox secrets: driver-appropriate test credentials.
Cashier::fakeConnection('aps_binance', ['callback_secret' => 'other']);

// Webhooks signed exactly as the PSP would sign them.
$delivery = WebhookSimulator::make('aps', $payload, connection: 'aps_binance');
$this->postJson($delivery->uri, $delivery->payload, $delivery->headers)->assertOk();

// Ledger assertions without an MT5 server.
app()->instance(FundsLedger::class, $ledger = new FakeLedger);
$ledger->assertMoved('credit', fn ($m) => $m['amount'] === 100.0);
$ledger->refuseAll();          // simulate a refusing ledger
```

## Artisan commands

| Command | Purpose |
|---|---|
| `cashier:install` | Publish config, optionally migrate, print the integration checklist |
| `cashier:check` | Doctor: connections, security posture, indexes, bindings — non-zero exit on failure |
| `cashier:publish` | Publish config and/or migrations (`--config`, `--migrations`, `--force`) |
| `cashier:purge` | Enforce payload/webhook-event retention (`--dry-run`) |
| `cashier:encrypt-historical` | One-off: encrypt + sanitize payloads written before encryption was on (`--dry-run`, `--chunk`, `--column`, `--skip-sanitize`) |

## License

MIT — see [LICENSE.md](LICENSE.md).
