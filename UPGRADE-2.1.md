# Upgrading to 2.1

Refunds. In 2.0 they were structurally incomplete: `processRefund()` called the
driver, returned a DTO and persisted nothing, while `Models\Refund` still
described the 1.x schema. This release makes the refund path work and closes
the hole that came with it.

## The hole

Nothing wrote a refund row, so `$transaction->refunds()->sum('amount')` — the
expression a "can this still be refunded?" guard is naturally built on — always
returned `0`. Any such guard passed every time, and **the same transaction could
be refunded repeatedly**. If you built one, re-read it against this release.

## Breaking: refund and capture amounts are `float`

Amounts are in **major units**, matching `transactions.amount` — a partial
refund of $95.50 is `95.5`.

```diff
-public function refund(string $transactionId, ?int $amount = null): RefundResult
+public function refund(string $transactionId, ?float $amount = null): RefundResult

-public function capture(string $transactionId, ?int $amount = null): PaymentResult
+public function capture(string $transactionId, ?float $amount = null): PaymentResult
```

`RefundResult::$amount` and `TransactionWebhookUpdate::$amount` widen to `float`
with them. Every bundled driver already cast its `int` straight to `float`
before sending it, so the wire format is unchanged — but the parameter truncated
on the way in, and a $95.50 refund reached the PSP as $95. Callers passing `int`
keep working; **custom drivers must update their signatures**, and strict
`toBe(100)` assertions on those properties become `toBe(100.0)`.

`PaymentResult::$amount` is deliberately left `int` — a wider change for
another release.

## `Models\Refund` now matches its own migration

It described the 1.x schema: `processor_refund_id` / `processor_response`
against a migration writing `provider_refund_id` / `provider_payload`, a uuid
key against a bigint one, and an `integer` cast on a `decimal` column. Since
nothing wrote through it, the mismatch never surfaced. The table name now comes
from `cashier-core.database.tables.refunds`, and the model is swappable via
`cashier-core.models.refund` / `Cashier::refundModel()`.

**If you created `cashier_refunds` from a 1.x migration, migrate it.** Column
map: `processor_refund_id` → `provider_refund_id`, `processor_response` →
`provider_payload`, `id`/`transaction_id` uuid → bigint, `amount` from minor to
major units.

## Balance accounting

`processRefund()` now reserves before it calls out:

1. Lock the transaction row, sum refunds in `succeeded|pending|processing`,
   and refuse if the request exceeds what is left (or if the amount is `<= 0`).
   Omit `$amount` to refund the outstanding balance.
2. Insert a `pending` refund row and release the lock — the PSP call happens
   outside it, so a slow gateway cannot block webhooks for that transaction.
3. Write the outcome back. A refusal or a thrown `PaymentProcessingException`
   marks the row `failed`, which releases the balance again; without that an
   outage would permanently consume a customer's remaining refundable amount.

In-flight attempts count against the balance, so two requests moments apart
cannot both pass.

`RefundSucceeded` / `RefundFailed` now carry the `Refund` as an optional second
constructor argument and are dispatched from `processRefund()` — in 2.0 they
existed but nothing dispatched them.
