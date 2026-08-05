# Upgrading to cashier-core 2.0

2.0 turns cashier-core from a factory-pattern skeleton into the complete
payment engine extracted from a production trading platform. It is a breaking
release; this guide lists every change a 1.x host must act on, roughly in the
order you will hit them.

## Requirements

- PHP ^8.3 (was ^8.2)
- Laravel ^11 | ^12 | ^13

## Removed from 1.x

| Removed | Replacement |
|---|---|
| `Factory\PaymentFactory` + `PaymentFactoryInterface` + `Facades\PaymentFactory` | `Connections\ConnectionRegistry` (container-bound; also aliased as `cashier.connections`) |
| `Registry\PaymentProviderRegistry` reading app-owned config | Deprecated subclass of `ConnectionRegistry` — existing injection sites keep working; migrate the type-hint when convenient |
| `Processors\PaytikoProcessor` (dead code) | The `asciisd/cashier-paytiko` plugin |
| `Models\PaymentMethod`, `Traits\Payable` | Host-owned; the package only needs `FeeConfigurationContract` from your method catalog |
| `Services\TransactionService` | `Services\Webhooks\WebhookProcessor` (locked transitions, guards, credit) |
| `"version"` key in composer.json | Tagged releases |

## Configuration: processors → connections + drivers

The 1.x `cashier-core.processors` map is gone. Configuration is now built
around **named connections** (one per PSP account, `driver` string + that
account's credentials) and a **driver map** (driver string → provider class,
bundled drivers merged automatically). See the README's *Concepts* section.

If your app previously kept connections in its own config
(`transactions.providers` with a `default_provider`), the registry still reads
that as a legacy fallback — but move it to `cashier-core.connections` during
the upgrade; the fallback exists for the transition, not forever.

## The database schema is now package-owned

`php artisan cashier:publish --migrations` (or let the package load them). New
tables: `cashier_webhook_events` (replay guard) and `cashier_admin_actions`
(admin audit trail). The `transactions` migration is the full column reference,
including the **unique `(provider, provider_transaction_id)` index** — before
migrating an existing table, check for duplicates:

```sql
SELECT provider, provider_transaction_id, COUNT(*) c
FROM transactions
WHERE provider_transaction_id IS NOT NULL AND deleted_at IS NULL
GROUP BY 1, 2 HAVING c > 1;
```

Point `cashier-core.models.transaction` at your own model extending
`Asciisd\CashierCore\Models\Transaction` to keep your traits, observers and
display casts. If your model carries tenant/user global scopes, list them in
`cashierBypassedScopes()` so webhooks can correlate — never include
SoftDeletes there.

## New: `PaymentStatus::OnHold`

Success webhooks whose reported amount/currency deviates from the invoice
beyond `webhooks.amount_tolerance_percent` now land in `OnHold` instead of
crediting. **Audit every `match`/`switch` over `PaymentStatus` in your app** —
an unhandled enum case in a match throws. Give it a label/color in your admin
panel and decide the resolution path (admin sync or manual release).

## Webhooks

- The package now registers the webhook routes itself (`api/webhooks/{driver}`
  by default) — remove your app-side route definitions or call
  `Cashier::ignoreRoutes()`. The `$registersRoutes` / `$runsMigrations` flags
  are actually read in 2.0.
- `ProcessPaymentProviderWebhook` now takes `(string $driver, array $payload,
  ?string $connection)` — the enum-typed 1.x-era job payloads no longer
  deserialize. Drain your queues (or keep an app-side shim subclass) before
  switching.
- Signature verification cannot be disabled in production; deliveries are
  refused and a critical log line is written if you try.
- Duplicate deliveries are ACKed by the replay guard without dispatching. If
  your tests asserted "second delivery dispatches and is de-duplicated
  downstream", they now assert "second delivery dispatches nothing".

## Side effects moved behind events

The 1.x-era hosts sent invoice mail and admin notifications inside the webhook
path. The package dispatches events only (`DepositSucceeded`,
`DepositHeldForReview`, `FundsCredited`, `WithdrawalRequested`, ...) — attach
your listeners for mail/CRM/notifications. Ledger movement goes through your
`FundsLedger` binding; the default `NullLedger` refuses every movement.

`mt5_ticket_number` keeps its historical column name — read it as "ledger
ticket". The MT5-specific transfer code you may have had in-app is replaced by
`Support\TransferClaim` + your `FundsLedger`.

## Fees

`FeeCalculator::for()` now takes any `FeeConfigurationContract` instead of an
app `PaymentMethod` model — implement the contract on your method catalog. The
math is unchanged from the extracted app implementation (gross-up solved so
`requested − pspFee` lands exactly on `amount + markup`; ceil-to-cent).

## Plugins

`asciisd/cashier-paytiko` and `asciisd/knet` require their own 2.x-compatible
majors (core `^2.0`); update them together with the core. Plugin processors
implement `PreparesChargeData` for their charge-shaping quirks and register
their driver string in `cashier-core.drivers`.

## After upgrading

```bash
php artisan cashier:check     # must pass before taking payments
```

and schedule `cashier:purge` (daily) for retention enforcement.
