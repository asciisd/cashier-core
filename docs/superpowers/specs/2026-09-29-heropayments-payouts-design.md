# Heropayments payouts — design

*2026-09-29*

Send approved withdrawals to the customer's crypto wallet through Heropayments
V2 (`POST /v2/withdrawal`), check our Heropayments balance before every send so
a payout is not refused for insufficient funds, and close the withdrawal from
Heropayments' callbacks.

This is the first driver that pushes a payout to a PSP. Today every withdrawal
ends with an admin calling `markPaid()` by hand; `DigibloxTransferService`
exists but nothing calls it, and APS only maps payout statuses. The contract
introduced here is generic so Digiblox (and others) can plug into it later
without changing the workflow.

Contract references: `.claude/skills/heropayments/references/` — `v2.md`
(Create a withdrawal, Get balance, Network fees, Minimum payment amount),
`callbacks.md` (V2 withdrawal callback), `overview.md` (withdrawal flow and
statuses), `errors.md` (`v2/withdrawal` rows).

## Scope

In:

- A `SendsPayouts` contract and its Heropayments implementation.
- A balance and minimum-amount preflight before every send.
- `WithdrawalWorkflow::sendPayout()`, `applyPayoutUpdate()`, `syncPayout()`.
- Routing Heropayments withdrawal callbacks away from the deposit pipeline.
- Two nullable columns on `transactions`: `payout_state`, `payout_reference`.

Out:

- The Custody flow (`custody/withdrawal`). Deposits use V2; payouts follow.
- Automatic payout on approval, or a queued auto-send. Sending is an explicit
  admin action.
- A scheduled reconcile command. `syncPayout()` is the building block if one
  is wanted later.
- Wiring Digiblox into the contract.
- The "withdrawal queue" Heropayments can enable per account. Its docs section
  is not vendored; see *Open questions*.

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Where the payout happens | A separate `sendPayout()` step on a Processing withdrawal | `approve()` stays PSP-agnostic; a shortfall leaves the withdrawal waiting instead of half-done |
| When the balance is checked | At send time only | That is the moment it matters; an approve-time check goes stale |
| A payout Heropayments reports failed | Back to the admin; no automatic MT5 refund | Most failures (bad address) are fixable by resending; `cancel()` already refunds |
| Where payout progress lives | New `payout_state` / `payout_reference` columns | `status` is customer-facing; ops need a queryable state; see below |

### Why `status` is not reused

`status` is what the customer sees. Reusing it for payout progress would show
the customer states like "Requires Action" that mean *ops* must act, and
Heropayments' internal errors ("insufficient funds, balance: X USDT20"). So:

- **`status`** — customer-facing. Pending → Processing → Succeeded, or
  Canceled. It stays **Processing** through every payout failure and retry.
- **`payout_state`** — ops-facing, indexed. The PSP side of the payout.

## Lifecycle

```
request()        Pending
approve()        Processing                      payout_state = null   (MT5 debited)
sendPayout()     Processing                      payout_state = sent | failed | unknown
callback         Processing                      payout_state = sent | failed
callback         Succeeded                       payout_state = paid
cancel()         Canceled   (only when payout_state is null or failed)
```

| `payout_state` | Meaning | Allowed next actions |
|---|---|---|
| `null` | Approved, never sent | `sendPayout`, `cancel`, `markPaid` |
| `sent` | Heropayments accepted it; waiting for the outcome | `syncPayout`, `markPaid` |
| `unknown` | The send timed out or errored; it may exist | `syncPayout`, `sendPayout` (looks up first), `markPaid` |
| `failed` | Heropayments refused or failed it; nothing is in flight | `sendPayout` (new attempt), `cancel`, `markPaid` |
| `paid` | Heropayments reported `finished`; `status` is Succeeded | none |

## Schema

New migration, `2026_09_29_000001_add_payout_columns_to_transactions_table.php`:

```php
$table->string('payout_state')->nullable()->index();   // PayoutState
$table->string('payout_reference')->nullable();        // PSP payment id
```

The package calls `loadMigrationsFrom()`, so hosts pick it up on `migrate`.
Hosts that published the migrations must re-publish (`cashier-core-migrations`
tag). Note this in the changelog.

New enum `Enums\PayoutState`: `Sent`, `Unknown`, `Failed`, `Paid`. The
`Transaction` model casts `payout_state` to it.

Payout metadata keys (in `metadata`, not queried):

| Key | Content |
|---|---|
| `payout_error` | Heropayments' refusal or failure reason. Never copied into `error_message`, which hosts may show the customer. |
| `payout_attempts` | List of earlier attempts: `{order_id, state, error, at}` |
| `payout_sent_at` | ISO timestamp of the accepted send |
| `payout_outcome` | From `finished`: `outcomeHash`, `outcomeHashLink`, `outcomeAmount`, `outcomeCurrency`, `merchantAmountUsdt`, `feePercent`, `networkFee` |

`provider_payload` always holds the latest raw create response or callback.

## Components

### `Contracts\SendsPayouts`

```php
interface SendsPayouts
{
    public function preflight(PayoutRequest $request): PayoutPreflight;
    public function send(PayoutRequest $request): PayoutReceipt;
    public function lookup(string $externalOrderId): ?PayoutReceipt;
    public function parsePayoutWebhook(array $payload): PayoutReceipt;
}
```

`parsePayoutWebhook()` keeps the callback job generic, so it never needs to know
it is handling a Heropayments payload.

`send()` throws one of two exceptions, and the difference drives the workflow:

- `PayoutRejectedException`: the PSP refused the request, so nothing was created. Safe to correct and resend.
- `PayoutOutcomeUnknownException`: timeout, 5xx, or a 2xx with no id. The payout may exist.

Both extend `PaymentProcessingException`.

### Data objects

- **`PayoutRequest`**: `externalOrderId`, `customerId`, `amount` (decimal
  string), `currency`, `payoutCurrency`, `payoutAddress`, `payoutExtraId`,
  `customerEmail`. Built by the workflow from the transaction:
  - `externalOrderId` is the row's current `provider_transaction_id`.
  - `amount` and `currency` come from the row.
  - `payoutCurrency`, `payoutAddress`, `payoutExtraId` and `customerEmail` come from `withdrawal_details` keys `payout_currency`, `payout_address`, `payout_extra_id` (optional) and `customer_email` (optional). The host puts them there and validates them, as it does for every withdrawal method today.
  - `customerId` resolves the same way deposits do: the MT5 login (`metadata.ledger_account`), falling back to `user_id`. This keeps Heropayments' per-customer daily limit keyed consistently.
- **`PayoutPreflight`**: `ok`, `balance`, `required`, `walletCurrency`, `reason`.
- **`PayoutReceipt`**: `reference` (Heropayments payment id), `rawStatus`,
  `state` (`PayoutState`), `payload`.

### `HeropaymentClient`

- `getBalance(): ?array` — `GET /v2/balance`, signed. Returns
  `{walletAddress, walletCurrency, balance}`.
- `createWithdrawal(array $body): array` — `POST /v2/withdrawal`, signed with
  the existing `encodePayload()` (Node `JSON.stringify` byte form, quirks
  entry 4). Must surface the HTTP status and body to the service rather than
  collapsing them, so the service can classify the outcome.
- `getPaymentByOrderId()` already exists and serves `lookup()`.

### `HeropaymentQuoteService`

Extend, do not duplicate:

- `rate()` gains a `$transactionType` parameter (default `deposit`). Payouts ask
  for `withdrawal`.
- `withdrawalNetworkFees(): array<string, float>` — the `type = withdrawal` rows
  of `/v2/network-fees`, keyed by ticker, in native payout-coin units.
- `minWithdrawal(string $currency): ?float` — `minWithdrawal` from
  `/v2/min-amount?currency=`.
- `providerFeePercent()` already reads `fee_percent`; reused as is.

The balance itself is **never cached**.

### `HeropaymentPayoutService implements SendsPayouts`

Construction mirrors `DigibloxTransferService`: config array in, client and
quote service built from it.

**Fail-closed config.** `preflight()` and `send()` both refuse with a
`PaymentProcessingException` unless:

- `withdrawals_enabled` is true (default false);
- `withdrawal_max_amount` is a strict decimal string greater than zero, and
  the amount does not exceed it (bcmath comparison; same pattern and
  reasoning as `DigibloxTransferService::create()`);
- `fee_percent` is set. It is needed for the estimate, and there is no safe
  default.
- (`send()` only) a callback URL resolves: `webhook_url`, or else the
  package route `<routes.name_prefix>heropayment`. Payouts are closed by
  callback, so one is required. The deposit path looks up the route as
  `webhooks.heropayment`, which never matches the registered name
  `cashier.webhooks.heropayment`. Payouts build the name from the configured
  prefix instead.

**`preflight()`**, in this order:

1. The amount is not over the cap.
2. `minWithdrawal(payoutCurrency)` → if the amount, converted to the payout
   currency at the withdrawal rate, is below it, refuse with
   `reason = below_minimum`.
3. `getBalance()` → `balance`, `walletCurrency`. A failed balance lookup
   refuses (`reason = balance_unavailable`); it never passes.
4. Estimate what Heropayments will deduct, in the wallet currency:

   ```
   base      = amount × rate(currency → walletCurrency, withdrawal)
   fee       = networkFee(payoutCurrency) × rate(payoutCurrency → walletCurrency, withdrawal)
   required  = (base × (1 + fee_percent/100) + fee)
               × (1 + balance_buffer_percent/100)
   ```

   Network fees come back in the payout coin's own units, not USDT as the
   docs claim (see the note on `HeropaymentClient::getNetworkFees()`), so
   the fee is converted. The conversion is skipped when the payout coin is
   the wallet coin. A payout coin with no withdrawal fee row refuses; it is
   never treated as a zero fee.

   `balance_buffer_percent` defaults to `2`. All arithmetic in bcmath at scale
   8; float inputs from the quote service are formatted with `%.8F` first.
   A missing rate or network fee refuses (`reason = quote_unavailable`).
5. `balance < required` → `ok = false`, `reason = insufficient_funds`.

**`send()`** builds the body:

```json
{
  "customerId": "...", "payoutAddress": "...", "payoutCurrency": "usdttrc20",
  "priceCurrency": "usd", "priceAmount": "100.00", "payoutExtraId": "...",
  "customerEmail": "...", "externalOrderId": "WD-...", "callbackUrl": "...",
  "fiat": true
}
```

`payoutExtraId` and `customerEmail` are omitted when empty. `callbackUrl` is
the package's Heropayments webhook route; Heropayments recommends one static
URL per transaction type, and one route serves both because the payload
carries `transactionType`.

It then classifies the response:

| Response | Result |
|---|---|
| 2xx with `id` | `PayoutReceipt` |
| 4xx whose message contains `not unique` | `lookup(externalOrderId)`; found → its receipt, otherwise `PayoutOutcomeUnknownException` |
| any other 4xx | `PayoutRejectedException` with Heropayments' message |
| 5xx, timeout, connection error, 2xx without `id` | `PayoutOutcomeUnknownException` |

`errors.md` marks `timeout of 15000ms exceeded` and `internal server error` as
"check whether the withdrawal was created". The 5xx rows `Amount is bigger
than maximum` and `Amount is not in range` are rejections in practice, but
classifying all 5xx as unknown is the safe default; the lookup resolves them.

**Status mapping** (V2 withdrawal vocabulary, `overview.md`):

| Heropayments | `PayoutState` |
|---|---|
| `waiting` `confirming` `exchanging` `hold` `sending` | `Sent` |
| `finished` | `Paid` |
| `failed` `refunded` | `Failed` |
| anything else | `Sent`, and log it as unrecognised |

Mapping an unrecognised status to `Sent` never closes or refunds anything, so
it is the safe default.

### `HeropaymentProvider`

Implements `SendsPayouts` by delegating to `HeropaymentPayoutService`, so the
workflow can resolve payout support from the provider it already registers.
`supports('payout')` returns true.

## Workflow

`WithdrawalWorkflow` gains a `ConnectionRegistry` dependency and three
methods. It resolves the provider with
`$registry->get($tx->connection ?? $tx->provider)` and requires it to be an
instance of `SendsPayouts`.

### `sendPayout(Transaction $tx, Actor $actor): Result`

1. **Guards.** Each refuses with `Result::failed`:
   - the row is a withdrawal
   - it is in `Processing`
   - the provider supports payouts
   - `payout_state` is `null`, `failed` or `unknown`
2. **New attempt id.** When `payout_state` is `failed`, push the current attempt onto `payout_attempts` and set `provider_transaction_id` to the next attempt id: `WD-<ULID>-2`, then `-3`. A failed payment still holds its `externalOrderId`.
3. **Unknown first.** When `payout_state` is `unknown`, `lookup()` first:
   - Found: `applyPayoutUpdate()` with that receipt and return.
   - Not found: continue with the **same** `externalOrderId`. If the first attempt did land, Heropayments answers `not unique` and `send()` resolves it by lookup.
4. **Claim.** `TransferClaim::acquire($tx, 'withdrawal:send-payout', expectedStatus: Processing, requireNoTicket: false)`. Refuse if not acquired.
5. **Preflight.** If it isn't `ok`:
   - release the claim
   - fire `PayoutFundsInsufficient` when the reason is `insufficient_funds`
   - return `Result::failed` with the numbers, e.g. "Heropayments balance 120.50 usdttrc20; this payout needs ~203.10."
6. **Send.**
   - **Receipt:** settle with `payout_state = sent` (or the receipt's state, if a `not unique` lookup found it further along), `payout_reference`, `payout_sent_at` and `provider_payload`. Audit `withdrawal.send-payout`, fire `WithdrawalPayoutSent`, return ok.
   - **`PayoutRejectedException`:** settle with `payout_state = failed` and `payout_error`; this also releases the claim. Audit, fire `WithdrawalPayoutFailed`, and return `Result::failed` with the reason.
   - **`PayoutOutcomeUnknownException`:** set `payout_state = unknown` and **keep the claim**. The claim then expires after 10 minutes, which is the window for the PSP to settle. Audit it and return `Result::failed("Payout outcome unknown. Do not resend; use Check status, or retry after 10 minutes.")`.

### `applyPayoutUpdate(Transaction $tx, PayoutReceipt $receipt, string $source): bool`

Runs under the row lock (`lockForUpdate` inside `DB::transaction`), the same
pattern as `WebhookProcessor::applyUpdate()`. Any state change it writes also
drops a leftover `TransferClaim` key, as `settle()` does. A lookup that resolves
an `unknown` send must not leave the row claimed for the rest of the 10-minute
window.

- **Ignore:**
  - rows that aren't withdrawals
  - rows whose `payout_state` is already `paid`, which is final
  - a `status` that is no longer `Processing`, logged as out of order
  - receipts whose state equals the current one; only `provider_payload` is refreshed
- **`Sent`:** set `payout_state = sent` and refresh `provider_payload`. A `hold` status is also logged for ops.
- **`Paid`:**
  - set `status = Succeeded`, `payout_state = paid`, `processed_at` and `payout_outcome`
  - audit `withdrawal.payout-paid` with a system actor (`new Actor('system:heropayment', 'system')`)
  - fire `WithdrawalMarkedPaid`, the same event as a manual `markPaid()`, so host notifications need no change
- **`Failed`:** set `payout_state = failed` and `payout_error` (from the payload status plus any message), then fire `WithdrawalPayoutFailed`. `status` stays Processing.

### `syncPayout(Transaction $tx, Actor $actor): Result`

The admin "check status" action. Allowed when `payout_state` is `sent` or
`unknown`:

- **Found:** `lookup(provider_transaction_id)` finds the payout, which is fed into `applyPayoutUpdate(…, 'sync')`, and the result is audited.
- **Not found:** reported as-is. For `unknown` that means sending may be safe, and the admin decides.

This covers callbacks Heropayments stopped retrying after five attempts.

### Changes to existing methods

- **`cancel()`** refuses when `payout_state` is `sent` or `unknown`. Money may already be on its way, and refunding MT5 then would pay the customer twice. Both of those states are ones where it is allowed today.
- **`markPaid()`** stays the manual override. When `payout_state` is not null it also sets `payout_state = paid`.

## Callbacks

`HeropaymentWebhookController` keeps its signature check, replay guard and
relay. After them it branches:

- `transactionType === 'withdrawal'` → dispatch `ProcessPayoutWebhook`
- otherwise → `ProcessPaymentProviderWebhook`, unchanged

Withdrawal callbacks therefore never reach `WebhookProcessor`. It would not
credit them (it checks `type === Deposit`), but it would move a `failed`
callback to `Failed`, and on `finished` its settlement step could overwrite
`amount` with `paidAmount`, which for a withdrawal is the crypto amount
deducted from our balance.

**`ProcessPayoutWebhook`** (queued; tries and backoff like
`ProcessPaymentProviderWebhook`):

1. Find the row: `provider = heropayment`, `provider_transaction_id =
   externalOrderId`, `type = Withdrawal`, host scopes bypassed. If none is found, log and
   return; this includes a late callback for an earlier attempt id.
2. If the row holds a fresh `TransferClaim` (a `sendPayout()` is mid-flight,
   and Heropayments called back before it settled), `release(30)` back onto the
   queue instead of dropping the callback. The exception is `payout_state =
   unknown`: that claim is held on purpose, and the callback is what resolves
   it, so it is applied.
3. Build the receipt with the adapter's mapping and call
   `applyPayoutUpdate($tx, $receipt, 'webhook')`.

## Events

New, in `src/Events`:

| Event | When | Payload |
|---|---|---|
| `WithdrawalPayoutSent` | A send was accepted | transaction, actor |
| `WithdrawalPayoutFailed` | A send was rejected, or a callback reported `failed`/`refunded` | transaction, reason |
| `PayoutFundsInsufficient` | Preflight found the PSP balance short | transaction, balance, required, wallet currency |

Existing `WithdrawalMarkedPaid` fires on `finished`.

`TransactionLogger` gains matching payout log methods.

## Configuration

Under `cashier-core.connections.heropayment` (documented in
`config/cashier-core.php` alongside the Digiblox block):

```php
'withdrawals_enabled' => env('HEROPAYMENT_WITHDRAWALS_ENABLED', false),
'withdrawal_max_amount' => env('HEROPAYMENT_WITHDRAWAL_MAX_AMOUNT'),   // required when enabled
'fee_percent' => env('HEROPAYMENT_FEE_PERCENT'),                       // existing key; required when enabled
'balance_buffer_percent' => env('HEROPAYMENT_BALANCE_BUFFER_PERCENT', 2),
```

## Testing

Pest, `Http::fake`, no live calls.

**`HeropaymentClient`**
- `getBalance()` and `createWithdrawal()` send the `x-api-key` and `x-api-sign`
  headers, with the signature over the `encodePayload()` bytes, including a
  body with a `/` and a non-ASCII email.

**`HeropaymentPayoutService`**
- Fail-closed: withdrawals disabled; cap missing, empty, zero, non-numeric or
  `2.5e1`; amount over cap; `fee_percent` unset.
- Preflight arithmetic: balance exactly equal to `required` passes; one unit
  below fails with `insufficient_funds` and correct numbers; below minimum;
  balance lookup fails; rate or network fee missing.
- Send classification: 2xx with id; 400 bad address → rejected; 400 `not
  unique` + lookup found → receipt; 400 `not unique` + lookup empty → unknown;
  500; timeout; 2xx without id.
- Status mapping, including an unrecognised status → `Sent`.

**`WithdrawalWorkflow`**
- `sendPayout`:
  - each guard
  - success sets `sent` and the reference
  - a rejection sets `failed` and puts the reason in metadata, never in `error_message`
  - unknown keeps the claim, and `cancel()` is refused meanwhile
  - a shortfall releases the claim, fires `PayoutFundsInsufficient` and leaves the row untouched
  - resend after `failed` uses `-2` and records the attempt
  - resend after `unknown` looks up first
- `applyPayoutUpdate`:
  - each mapping row
  - `paid` is final, so a late `failed` is ignored
  - `finished` fires `WithdrawalMarkedPaid` and never touches `amount`
- `cancel()` refused for `sent` and `unknown`, and allowed for `null` and `failed`.
- `syncPayout` found and not found.

**Callbacks**
- A withdrawal callback dispatches `ProcessPayoutWebhook` and never
  `ProcessPaymentProviderWebhook`, and no ledger credit happens.
- Deposit callbacks are unchanged.
- A callback against a row with a fresh claim is released back onto the queue.

## Rollout

- `withdrawals_enabled = false` by default. Heropayments lists no sandbox, so
  the first send moves real money: enable with a small
  `withdrawal_max_amount` and send one small payout end to end first.
- Record in `.claude/skills/heropayments/references/quirks.md`, marked
  **Unverified** until that first run confirms them:
  1. a failed or refunded payment still reserves its `externalOrderId`;
  2. a duplicate `externalOrderId` returns a 4xx whose message contains
     `not unique`;
  3. which currency `v2/rate` quotes the withdrawal deduction in, against
     `walletCurrency` from `v2/balance`.

## Open questions

- **Withdrawal queue.** `v2.md` says an infrastructural error can put the
  withdrawal into a queue "if this option is turned on for your account". That
  section is not vendored. If it is on for our account, a queued payout may
  arrive later with a status this design maps to `Sent`, which is safe. Ask
  Heropayments whether it is enabled and vendor the section.
- **Estimated price endpoint.** `overview.md` links `POST v2/estimate`, which
  the vendored collection does not contain. The preflight uses `v2/rate`
  instead. If Heropayments confirms `v2/estimate` returns the exact deduction,
  it could replace the fee formula.
