# Why the APS adapter reports `amount`, not `amount_in`

**Status:** fixed in 2.1.3. Affected 2.0.0 through 2.1.2 identically — not a
regression within v2.
**Component:** `src/Drivers/Aps/ApsAdapter.php`

This note records a defect that is easy to reintroduce, because the APS payload
offers four plausible amount fields and only one of them is comparable to what
the package stores.

---

## TL;DR

`ApsAdapter::fromWebhook()` mapped APS's `amount_in` — **what the customer was
debited**, including APS's own customer fee — into `TransactionWebhookUpdate::$amount`.
`WebhookProcessor` compares that figure against `transactions.requested_amount`
— **what we sent the PSP** — inside a 1% tolerance.

For any payment method whose settlement mode is `added`, those two numbers
differ by exactly the PSP fee percentage. `deviationBeyondTolerance()` therefore
fired on every successful deposit, and each one became `PaymentStatus::OnHold`
instead of being credited.

The guard was correct. The fee model was correct. The host configuration was
correct. The adapter picked the wrong field, and it was the only bundled driver
that did.

---

## Symptom

Every successful APS deposit lands in `on_hold` and is never credited by its
callback. The log line is:

```
Deposit success held for review instead of credited
{"provider":"aps","transaction_id":<id>,
 "reason":"amount deviates 5.00% from invoiced 100.00 (webhook reports 105.00)"}
```

The deviation percentage always equals the PSP's customer fee rate, on every
transaction, which is the tell: a genuine partial settlement would not land on
the same figure twice.

The callback itself is delivered, signature-verified, replay-guarded and
processed normally — this is not a delivery or signature problem. Releasing the
deposit requires an operator to run the sync action, which is what makes the
symptom look like "the webhook isn't arriving."

---

## The payload's four amounts

A completed APS deposit under `settlement_mode: added` carries all four, and
they are all different. Values below are an illustrative 100.00 order against a
5% PSP customer fee:

```json
{
  "id": "00000000-0000-0000-0000-000000000000",
  "external_id": "DEP-0000000000000000000000000",
  "status": "completed",
  "amount":       100.00,
  "amount_in":    105.00,
  "amount_out":   100.00,
  "amount_fee":     5.00,
  "amount_body":  100.00,
  "customer_fee":   5.00,
  "merchant_fee":   0.00,
  "fiscal_status": "done"
}
```

| Field | Meaning |
|---|---|
| `amount` | what we asked APS to collect — the order |
| `amount_in` | what the customer was debited, incl. APS's customer fee |
| `amount_out` | what APS settles to the merchant |
| `customer_fee` | APS's fee, charged on top at checkout |

The corresponding transaction row:

```
amount=100.00  requested_amount=100.00  charged_amount=105.00
psp_fee_amount=5.00  markup_amount=0.00  settlement_mode=added
```

Note `charged_amount` === `amount_in`. The fee model predicts the customer's
debit exactly; nothing is miscomputed anywhere.

---

## Root cause

### 1. What the adapter sent

`fromWebhook()` reported `amount_in`, so a 100.00 order arrived at the guard as
105.00.

### 2. What the processor expects

`WebhookProcessor::deviationBeyondTolerance()`
([WebhookProcessor.php:278](../src/Services/Webhooks/WebhookProcessor.php#L278)):

```php
$expected = (float) ($transaction->requested_amount ?? $transaction->amount);
$tolerance = (float) config('cashier-core.webhooks.amount_tolerance_percent', 1.0) / 100;
$deviation = abs(((float) $update->amount) - $expected) / $expected;
if ($deviation > $tolerance) { /* → PaymentStatus::OnHold */ }
```

Its docblock states the intended basis explicitly: the reported amount is
compared against **what we asked the PSP for**.

### 3. Why `requested_amount` is the right basis — do not "fix" this side

[FeeBreakdown.php:17](../src/Fees/FeeBreakdown.php#L17) defines three numbers and
warns they must not be conflated: `amount` is what the customer asked to deposit
and what the ledger is credited; `requestedAmount` is what we send the PSP,
grossed up unless the PSP adds its own fee; `chargedAmount` is what the customer
is actually debited.

For `added` mode, `FeeCalculator` returns `amount + markup` as the requested
figure — the PSP appends its fee itself, so grossing up would charge the
customer twice — and `requested + pspFee` as the charged figure
([FeeCalculator.php:43](../src/Fees/FeeCalculator.php#L43),
[FeeCalculator.php:80](../src/Fees/FeeCalculator.php#L80)).
[PaymentService.php:120](../src/Services/PaymentService.php#L120) then hands
`requestedAmount`, not `chargedAmount`, to the driver.

So the chain is coherent: we send APS the order, APS adds its own customer fee,
debits the customer the sum, and settles the order to us. A guard comparing the
PSP's echo against the order is correct. The adapter simply handed it the
customer-debit figure.

### 4. The package already treated `amount_out` as the settlement figure

`WebhookProcessor::reportedSettlement()` reads APS's merchant settlement from the
`aps_amount_out` metadata key. The package therefore already classified
`amount_out` as "settled to us" and, by implication, `amount_in` as something
else — while the adapter used `amount_in` for the invoice comparison. That was an
inconsistency inside a single package.

### 5. Every other bundled driver reports the order amount

| Driver | `fromWebhook()` amount source | Meaning |
|---|---|---|
| Jenapay | `order_amount` | order |
| Heropayment | `priceAmount` (`payAmount` → metadata only) | order |
| Payport | `amount_currency` | order |
| Sticpay | `order_amount` | order |
| **APS (before 2.1.3)** | **`amount_in`** | **customer debit** ← outlier |

Heropayment is the clearest precedent: it deliberately keeps `payAmount`, what
the customer paid, in metadata and reports `priceAmount`, the order, as the
update amount.

---

## Why manual sync released it, and so masked the bug

`PaymentService::updateFromRetrievedResult()`
([PaymentService.php:439](../src/Services/PaymentService.php#L439)) deliberately
omits `amount` from the `TransactionWebhookUpdate` it builds, and the guard
short-circuits on a null amount. A sync therefore always settled the deposit
while the callback never could. That asymmetry is the diagnostic signature of
this class of bug.

No funds were ever mis-credited: `creditLedger()` reads `$transaction->amount`,
so the ledger received the correct net figure — just late, and only after a
manual sync.

---

## The fix

[ApsAdapter.php:97](../src/Drivers/Aps/ApsAdapter.php#L97), in `fromWebhook()`,
reports the order:

```php
amount: isset($inner['amount']) ? (float) $inner['amount'] : null,
```

Three decisions inside that one line:

- **`amount`, not `amount_out`.** Both hold the order figure in a normal
  settlement. `amount` is what `requested_amount` was built from, matches all
  four other bundled drivers, and is unambiguous under currency conversion.
  `amount_out` would additionally catch a genuine partial settlement, but risks
  false positives if APS ever reports it post-conversion — and partial
  settlement is already surfaced through the fee-drift path.
- **No fallback to `amount_in`.** Where APS omits the order figure the update
  reports `null`, the guard skips the comparison, and the deposit settles.
  Falling back to the customer debit would reinstate the bug on exactly the
  payload shape that triggered it.
- **`(float)`, not `(int) round(...)`.** The field is `?float` and APS quotes
  cents. Every driver rounds here, which is harmless for APS now that the field
  is exact, but the rounding only ever discarded information.

[ApsAdapter.php:56](../src/Drivers/Aps/ApsAdapter.php#L56),
`fromProviderPayload()`, has its preference flipped to match. Nothing compares
that figure against the invoice, but both paths read the same payload and must
not disagree about what the transaction was for.

---

## Regression tests

- `tests/Unit/Drivers/ApsAdapterTest.php` — a payload carrying `amount`,
  `amount_in` and `amount_out` together reports the order; a payload with no
  order figure reports `null` rather than falling back; `fromProviderPayload()`
  parity.
- `tests/Feature/WebhookProcessorTest.php` — an `added`-mode deposit through
  adapter → processor reaches `Succeeded` and credits the net amount; a callback
  reporting less than the invoice still lands `OnHold` with a `hold_reason`, so
  partial-settlement detection is not disarmed; `aps_amount_out` still drives
  `FeeDriftDetected`.

The pre-existing adapter test could not catch this: its fixture carried only
`amount_in`, so the wrong field preference never showed. Any fixture used to
test amount reporting must carry all three fields, as real payloads do.

---

## Explicitly out of scope

- **Do not** change `deviationBeyondTolerance()` to compare against
  `charged_amount`. That would make the guard correct for APS and wrong for
  `deducted` and `invoiced` modes, where the gross-up is already inside
  `requested_amount`, and it would weaken partial-settlement detection for every
  driver.
- **Do not** raise the default `amount_tolerance_percent`. A band wide enough to
  swallow a PSP fee blinds the guard on all drivers.
  `CASHIER_WEBHOOK_AMOUNT_TOLERANCE` exists for deliberate, temporary use with
  that cost understood.
- No host-application change is required. This was a package defect throughout.
