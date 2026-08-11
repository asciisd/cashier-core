# Bug: APS driver reports the customer's debit as the webhook amount, so every fee-added APS deposit is held for review

**Package:** `asciisd/cashier-core`
**Version observed:** 2.1.2 (present identically in 2.0.0, 2.1.1, 2.1.2 — not a regression within v2)
**Component:** `src/Drivers/Aps/ApsAdapter.php`
**Severity:** High — no successful APS deposit is ever credited by its webhook
**Layer:** Package. No host-application change is required or appropriate.

---

## TL;DR

`ApsAdapter::fromWebhook()` maps APS's `amount_in` (**what the customer was debited**, including
APS's own customer fee) into `TransactionWebhookUpdate::$amount`. `WebhookProcessor` compares that
figure against `transactions.requested_amount` (**what we sent the PSP**) inside a 1% tolerance.

For any payment method whose settlement mode is `added`, those two numbers differ by exactly the PSP
fee percentage — 6.25% in production — so `deviationBeyondTolerance()` always fires and every
successful APS deposit becomes `PaymentStatus::OnHold` instead of being credited.

The guard is correct. The fee model is correct. The host config is correct. **The adapter picks the
wrong field**, and it is the only bundled driver that does.

---

## Symptom

Every successful APS deposit lands in `on_hold` and is never credited. In production logs:

```
07:56:14 production.INFO: Payment provider webhook received
         {"provider":"aps","provider_transaction_id":"d540c361-…","status":"succeeded"}
07:56:14 production.WARNING: Deposit success held for review instead of credited
         {"provider":"aps","transaction_id":688,
          "reason":"amount deviates 6.00% from invoiced 100.00 (webhook reports 106.00)"}
```

Four consecutive successful APS deposits behaved identically (txns 673, 681, 687, 688). Each had to
be released by an operator clicking the Nova sync action, minutes to an hour later.

The webhook itself is delivered, signature-verified, replay-guarded and processed normally — this is
not a delivery or signature problem.

---

## Evidence

### The APS payload (stored `provider_payload`, txn 688)

```json
{
  "id": "d540c361-0b03-4f14-902b-c69da1b98014",
  "external_id": "DEP-01KZQX1SR2TE7WPP53M0R4X6Z3",
  "status": "completed",
  "amount":      100,
  "amount_in":   106.25,
  "amount_out":  100,
  "amount_fee":  6.25,
  "amount_body": 100,
  "customer_fee": 6.25,
  "merchant_fee": 0,
  "external_extra": { "conversion_rate": 0.87569367304862 },
  "fiscal_status": "done"
}
```

APS's own field semantics:

| Field | Meaning | Value |
|---|---|---|
| `amount` | what we asked APS to collect (the order) | 100.00 |
| `amount_in` | what the customer was debited, incl. APS's customer fee | 106.25 |
| `amount_out` | what APS settles to the merchant | 100.00 |
| `customer_fee` | APS's fee, charged on top at checkout | 6.25 |

### The transaction row

```
id=688  amount=100.00  requested_amount=100.00  charged_amount=106.25
        psp_fee_amount=6.25  markup_amount=0.00  settlement_mode=added  currency=USD
```

Note `charged_amount` (106.25) === APS's `amount_in` (106.25). The fee model predicted the customer's
debit exactly. Nothing is miscomputed anywhere.

---

## Root cause

### 1. What the adapter sends

`src/Drivers/Aps/ApsAdapter.php:82`, in `fromWebhook()`:

```php
amount: isset($inner['amount_in']) ? (int) round((float) $inner['amount_in']) : null,
```

`amount_in` = 106.25 → `(int) round(...)` → **106**.

### 2. What the processor expects

`src/Services/Webhooks/WebhookProcessor.php:278-310`, `deviationBeyondTolerance()`:

```php
$expected = (float) ($transaction->requested_amount ?? $transaction->amount);   // 100.00
$tolerance = (float) config('cashier-core.webhooks.amount_tolerance_percent', 1.0) / 100;  // 0.01
$deviation = abs(((float) $update->amount) - $expected) / $expected;            // |106-100|/100 = 0.06
if ($deviation > $tolerance) { /* → PaymentStatus::OnHold */ }
```

Its docblock states the intended basis explicitly:

> The reported amount is compared against **what we asked the PSP for** (`requested_amount`, falling
> back to `amount`), inside the configured tolerance band.

### 3. Why `requested_amount` is the right basis (do not "fix" this side)

`src/Fees/FeeBreakdown.php` defines the three numbers and warns they must not be conflated:

```php
/** What the customer asked to deposit, and what MT5 is credited. */   public float $amount;
/** What we send the PSP — grossed up unless the PSP adds its own fee. */ public float $requestedAmount;
/** What the customer is actually debited. */                          public float $chargedAmount;
```

And `FeeCalculator::requestedAmount()` for `added` mode:

> `added`: the PSP appends its fee itself, so requesting the gross-up too would charge the customer
> twice.

```php
if ($mode->pspAddsAtCheckout()) {
    return $this->round($amount + $markup);          // requested = 100.00 — sent to APS
}
…
chargedAmount: $mode->pspAddsAtCheckout()
    ? $this->round($requested + $pspFee)             // charged  = 106.25 — customer's debit
    : $requested,
```

`PaymentService.php:120` then hands `requestedAmount` (not `chargedAmount`) to the driver:

```php
$paymentData['amount'] = $breakdown->requestedAmount;
```

So the chain is coherent: **we sent APS 100.00, APS added its own 6.25 customer fee, debited the
customer 106.25, and settled 100.00 to us.** The guard comparing the PSP's echo against 100.00 is
correct. The adapter simply hands it the customer-debit figure instead of the order figure.

### 4. The package already treats `amount_out` as the settlement figure

`WebhookProcessor::reportedSettlement()` (line ~490) reads APS's merchant settlement from
`aps_amount_out`:

```php
$reported = $metadata['settlement_reported_amount']
    ?? $metadata['payport_merchant_amount']
    ?? $metadata['aps_amount_out']
    ?? $metadata['sticpay_merchant_amount']
    ?? null;
```

The package thus already classifies `amount_out` as "settled to us" and, by implication, `amount_in`
as something else — while the adapter uses `amount_in` for the invoice comparison. That is an
inconsistency inside a single package.

### 5. Every other bundled driver uses the order amount

| Driver | `fromWebhook()` amount source | Meaning |
|---|---|---|
| Jenapay | `order_amount` | order |
| Heropayment | `priceAmount` (`payAmount` → metadata only) | order |
| Payport | `amount_currency` | order |
| Sticpay | `order_amount` | order |
| **APS** | **`amount_in`** | **customer debit** ← outlier |

Heropayment is the clearest precedent: it deliberately keeps `payAmount` (what the customer paid) in
metadata and reports `priceAmount` (the order) as the update amount.

---

## Why it surfaced now (for context)

Not a package regression. The host adopted the cashier-core v2 engine — which introduced this guard
— on 2026-08-06. Between that deploy and 2026-08-10 every APS transaction was failed or canceled.
The first APS **success** under v2 (txn 673, 2026-08-10 08:31) was held, and so was every one after
it. Under the previous engine there was no amount assertion, so the mismatch was simply invisible.

No funds were mis-credited at any point: `creditLedger()` reads `$transaction->amount`, so the ledger
always received the correct net figure — just late, and only after a manual sync.

---

## Why manual sync releases it (and masks the bug)

`PaymentService::updateFromRetrievedResult()` (line 439-473) deliberately omits `amount` from the
`TransactionWebhookUpdate` it builds. The guard therefore short-circuits:

```php
if ($update->amount === null) {
    return null;                     // no hold
}
```

So a sync always settles the deposit, while the webhook never can. That asymmetry is what makes the
symptom look like "the webhook isn't arriving."

---

## Proposed fix

In `src/Drivers/Aps/ApsAdapter.php`, report the **order** amount, not the customer's debit.

**`fromWebhook()` (line 82):**

```php
// APS's `amount` is the figure we asked it to collect — the same basis as
// `transactions.requested_amount`, which WebhookProcessor compares against.
// `amount_in` is the customer's debit *including* APS's own customer fee, so
// reporting it holds every `settlement_mode: added` deposit for review.
// The merchant settlement (`amount_out`) is already surfaced for fee-drift
// reporting via the `aps_amount_out` metadata key.
amount: isset($inner['amount']) ? (float) $inner['amount'] : null,
```

**`fromProviderPayload()` (line 52)** has the same inverted preference and should match:

```php
- amount: (int) round((float) ($payload['amount_in'] ?? $payload['amount'] ?? 0)),
+ amount: (int) round((float) ($payload['amount'] ?? $payload['amount_in'] ?? 0)),
```

(`PaymentResult::$amount` is `int` by design, so the cast stays on this path.)

### Secondary, optional: stop discarding cents on the webhook path

`TransactionWebhookUpdate::$amount` is `?float` (`src/DataObjects/TransactionWebhookUpdate.php:24`),
but every driver casts to `(int) round(...)` before assigning it. With the corrected field APS is
exact (100.00), so this does not bite here — but a PSP settling a non-integer amount would be
compared on a rounded figure. Worth a separate decision; it is a cross-driver convention, not an APS
defect.

### Consider: `amount` vs `amount_out`

Both are 100.00 in the observed payload.

- **`amount` (recommended)** — the order figure, exactly what `requested_amount` holds. Matches all
  four other bundled drivers. Unambiguous under currency conversion (`external_extra.conversion_rate`
  appears in these payloads).
- `amount_out` — would additionally let the guard catch a genuine partial settlement, but risks false
  positives if APS ever reports it post-conversion, and partial settlement is already surfaced
  through the fee-drift path (`reportedSettlement()` → `aps_amount_out`).

---

## Suggested regression tests

1. **The reported bug** — `fromWebhook()` on the real payload above returns
   `$update->amount === 100.0`, not `106.25`/`106`.
2. **End-to-end guard** — a `settlement_mode: added` deposit (`amount` 100.00,
   `requested_amount` 100.00, `charged_amount` 106.25) processed through
   `WebhookProcessor::applyUpdate()` with that payload reaches `PaymentStatus::Succeeded` and
   credits the ledger — not `OnHold`.
3. **Guard still works** — a payload with `amount: 50` against a 100.00 invoice still yields
   `OnHold` with a `hold_reason`. The fix must not disarm partial-settlement detection.
4. **Fee drift unaffected** — `aps_amount_out` still reaches `reportedSettlement()`, and a payload
   whose `amount_out` diverges from `amount + markup` still dispatches `FeeDriftDetected`.
5. **Retrieve path parity** — `fromProviderPayload()` returns 100 for the same payload.

---

## Explicitly out of scope

- **Do not** change `deviationBeyondTolerance()` to compare against `charged_amount`. That would make
  the guard correct for APS and wrong for `deducted`/`invoiced` modes, where the gross-up is already
  inside `requested_amount`, and it would weaken partial-settlement detection for every driver.
- **Do not** raise the default `amount_tolerance_percent`. A 6.25% band would blind the guard on all
  drivers. (The host can set `CASHIER_WEBHOOK_AMOUNT_TOLERANCE` as a temporary stopgap, with that
  cost understood, until this ships.)
- No host-application change is needed. The APS payment method's fee configuration, `config/cashier-core.php`,
  and the APS credentials are all correct.
