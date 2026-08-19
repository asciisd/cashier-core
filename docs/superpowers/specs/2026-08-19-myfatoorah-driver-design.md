# MyFatoorah driver — design

*2026-08-19*

Add MyFatoorah (`docs.myfatoorah.com`) as a bundled driver in `asciisd/cashier-core`,
alongside APS, Jenapay, Heropayment, Payport and Sticpay.

The gateway contract is vendored in the `myfatoorah` skill under
`.claude/skills/myfatoorah/`. Every non-obvious decision below cites the
`references/pitfalls.md` entry it rests on; read that file before implementing.

## Scope

Day one is **deposits, webhooks and sync** — the minimum that credits money
safely, and exactly what the APS driver ships.

Out of scope, and `supports()` says so: refunds, auth & capture, void,
tokenization, recurring, multi-vendor suppliers. `refund()`, `capture()`,
`authorize()` and `void()` throw `\BadMethodCallException`, matching how APS
reports the operations it does not have.

Refunds are deliberately excluded rather than deferred by accident. A MyFatoorah
refund is a request a human approves (`pitfalls.md` entry 10): `MakeRefund`
returning 200 means *filed*, not refunded; it takes no currency, so the amount
is in the account's base currency rather than the invoice's display currency;
and duplicate protection is explicitly the merchant's problem. That maps badly
onto `processRefund()`, which reserves a balance and expects a synchronous
`RefundResult`. Adding it later means designing that mapping, not just calling
one more endpoint.

## Generation: V3

Both generations are live on the same hosts under the same API key, and
MyFatoorah presents them as peers rather than as old and new
(`pitfalls.md` entry 2). We build on V3:

- `POST /v3/payments` returns `Data.PaymentURL` in **one** call. V2 needs
  `InitiatePayment` then `ExecutePayment`, because V2 identifies a payment
  method by a numeric id computed per account *and per invoice value*, which
  must be read fresh for every charge.
- `GET /v3/invoices/{InvoiceId}` is a complete sync source keyed by an id we
  already hold.
- V3 returns `ExpirationDate` as ISO 8601 UTC, so `pitfalls.md` entry 8's
  timezone trap does not apply — nothing here computes a local `Expired`.

`mapStatus()` is nonetheless written as **two separate tables**, V3 populated
and V2 structurally reserved, so V2 values can be added without restructuring.
Never one case-insensitive table: V2 spells success `Succss` and V3 spells it
`SUCCESS`, and a case-insensitive match hides that divergence and starts
silently dropping payments the day either side is corrected
(`pitfalls.md` entries 1–2).

## Files

`src/Drivers/Myfatoorah/` — the APS driver's three-file shape plus a dedicated
signature service. The signature service is warranted here (and is not for APS)
because MyFatoorah signs a canonical field list, not the request body.

| File | Responsibility |
|---|---|
| `MyfatoorahClient` | HTTP surface: `createPayment()`, `getInvoice()`. Bearer auth, `Idempotency-Key`, `PspHttp` timeout/retry posture. Owns the `IsSuccess` envelope guard. |
| `MyfatoorahSignatureService` | Canonical `key=value,key2=value2` string per event code → `base64(hmac_sha256)`; `hash_equals` compare. |
| `MyfatoorahAdapter` | `PaymentAdapterInterface`. Status tables, webhook → `TransactionWebhookUpdate`, invoice → `PaymentResult`. |
| `MyfatoorahProvider` | `PaymentProcessorInterface` + `ProvidesWebhookTransactionId` + `PreparesChargeData`. |

Also touched:

- `src/Http/Controllers/Webhooks/MyfatoorahWebhookController.php` — new.
- `routes/webhooks.php` — `Route::post('/myfatoorah', ...)->name('myfatoorah')`.
- `src/CashierCoreServiceProvider.php` — `'myfatoorah' => Drivers\Myfatoorah\MyfatoorahProvider::class` in `BUNDLED_DRIVERS`.
- `src/Testing/WebhookSimulator.php` — a `myfatoorah` signing recipe.
- `src/Cashier.php` — `fakeConnection()` defaults for the driver.
- `src/Enums/PaymentMethodBrand.php` — a `Knet` case (see *Payment method snapshot*).
- `config/cashier-core.php` — a commented connection example.
- `README.md` and `resources/boost/skills/cashier-core-development/SKILL.md` — MyFatoorah added to the bundled-driver lists.

Class prefix is `Myfatoorah` (one capital), matching `Heropayment`. Driver
string, route segment and `getName()` are all `myfatoorah`.

## Connection config

```php
'myfatoorah' => [
    'driver' => 'myfatoorah',
    // Per country. api.myfatoorah.com serves KWT/BHR/OMN/JOR; SAU, ARE, QAT
    // and EGY each have their own host; every country shares the sandbox
    // apitest.myfatoorah.com. A merchant trading in two countries is two
    // connections, because MyFatoorah issues one API key per country and the
    // key must be sent to that country's host.
    'base_url' => env('MYFATOORAH_BASE_URL'),
    'api_key' => env('MYFATOORAH_API_KEY'),
    // Enabled per webhook in the portal ("secure key"). Mandatory for V2.
    'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
    // The charge currency. MyFatoorah cannot charge USD — see Currency below.
    'currency' => env('MYFATOORAH_CURRENCY', 'KWD'),
    // Optional. CARD | KNET | APPLE_PAY | GOOGLE_PAY. Omit to land the
    // customer on MyFatoorah's own picker showing every enabled method.
    'payment_method' => env('MYFATOORAH_PAYMENT_METHOD'),
    // Optional — fall back to the `payment.success` and
    // `cashier.webhooks.myfatoorah` routes where the host defines them.
    'redirect_url' => env('MYFATOORAH_REDIRECT_URL'),
    'webhook_url' => env('MYFATOORAH_WEBHOOK_URL'),
    // Optional. EN | AR. Defaults to the customer's cashierLocale().
    'language' => env('MYFATOORAH_LANGUAGE'),
],
```

The host is explicit config rather than derived from a `country` key or fetched
from `https://portal.myfatoorah.com/Files/API/mf-config.json` at runtime. This
matches every other driver here, keeps a network call and a cache dependency out
of the charge path, and satisfies the skill's "configuration to be refreshed,
not constants" point by virtue of being configuration. `mf-config.json` stays the
reference for *what* to put in `base_url`; the package does not read it.

The constructor throws `PaymentProcessingException` when `base_url`, `api_key`
or `currency` is missing, matching `ApsProvider`.

## Currency

`PaymentService` pins every charge to `cashier-core.currency.default` (USD here)
and forbids callers choosing one. MyFatoorah's `Order.Currency` accepts only
`SAR, BHD, AED, QAR, OMR, KWD, JOD, EGP` — **USD is not among them**.

The provider implements `PreparesChargeData` and overwrites the pinned currency
with the connection's `currency`, which is the documented purpose of that hook
("a driver that charges in a fixed currency overrides it in
`prepareChargeData()`"). The transaction is then persisted in that currency. No
conversion happens anywhere in the package — putting an FX rate inside a payment
driver would make the ledger credit disagree with what the customer was shown.

```php
public function prepareChargeData(CustomerContract $customer, string $connection, array $paymentData): array
{
    $paymentData['currency'] = $this->config['currency'];

    return $paymentData;
}
```

## Charge

One call, `POST /v3/payments`:

```
Headers   Authorization: Bearer <api_key>
          Idempotency-Key: <ExternalIdentifier>

Body      PaymentMethod        // omitted entirely when the connection sets none
          Order:               { Amount, Currency, ExternalIdentifier: 'DEP-'.Str::ulid() }
          Customer:            { Name, Email, Reference: (string) $customer->cashierId() }
          IntegrationUrls:     { Redirection, Webhook }
          Language             // EN | AR
          IpAddress
          MetaData:            { UDF1: <ExternalIdentifier> }
```

`Redirection` and `Webhook` come from config, falling back to `route('payment.success')`
and `route('cashier.webhooks.myfatoorah')` where the host defines them — the same
pattern and the same `Route::has()` guard `ApsProvider::charge()` uses.

`Idempotency-Key` carries the same ULID as `Order.ExternalIdentifier`. It is
opt-in, lasts 250 minutes, and is the **only** protection against a double
charge on a retry (`pitfalls.md` entry 11).

On the response:

- `Data.InvoiceId` → `PaymentResult::$transactionId`.
- `Data.PaymentURL` → `metadata.redirect_url`, which is what makes
  `PaymentResult::requiresAction()` true and drives the host's redirect.
- `Data.PaymentCompleted === true` is the non-3DS case, where the outcome is
  already in `Data.TransactionDetails.Transaction.Status`; map it. Otherwise the
  status is `Pending`.
- `metadata.myfatoorah_external_id` records the ULID, mirroring
  `aps_external_id`.

### Correlation key is `InvoiceId`, not `PaymentId`

`Data.PaymentId` is **null on every redirect-flow create response** — it only
has a value in the non-3DS completed case — so it cannot be the correlation key.
`InvoiceId` is present at create, arrives in the webhook as `Data.Invoice.Id`,
and is the lookup key for `GET /v3/invoices/{InvoiceId}`. It goes in
`transactions.provider_transaction_id`, and `extractWebhookTransactionId()`
returns `Data.Invoice.Id`.

### Response envelope

`IsSuccess: false` arrives in at least five shapes (`pitfalls.md` entry 6), and
the documented one is only the first:

1. `ValidationErrors: [{Name, Error}]` — documented.
2. `FieldsErrors: [{Name, Error}]` — same thing, different key.
3. `Data.ErrorMessage` — a string, no error array at all.
4. `Message` + `MessageDetail` and **no `IsSuccess` field** (a routing error).
   A parser keyed off `IsSuccess` reads this as a success.
5. An **HTML page, not JSON** — a 403 from Azure Application Gateway when the
   caller's IP is blocked.

`MyfatoorahClient` has one `envelope()` method that normalises all five into a
single `PaymentProcessingException` carrying a message assembled defensively —
`ValidationErrors[].Error` may be an empty string with the field name in `Name`,
so a message built only from `Error` values comes out blank. The raw body is
always retained on the result, never discarded.

Transport failures are caught as `HttpClientException`, not `RequestException` —
a connection timeout or DNS failure is a `ConnectionException`, a sibling rather
than a subclass, and would otherwise escape as an uncaught 500 with nothing
logged. This is the bug `ApsProvider::charge()` documents; the same catch applies
here.

## Webhook

`POST {prefix}/myfatoorah` → `MyfatoorahWebhookController`, using
`EnforcesSignatureVerification`. Order of operations:

1. **Version gate.** Read `MyFatoorah-Webhook-Version`. Accept only `v2`;
   reject `v1`, absent, or anything else with a 403, a `WebhookRejected` event,
   and a critical log line naming the value received. The header is
   **undocumented** — it appears nowhere in `webhooks.md` — but MyFatoorah's own
   library reads it and throws without it (`pitfalls.md` entry 5). Never infer
   the version from the payload shape: V1 (`EventType` + `Event` + `Data`) and
   V2 (`Event.Code` + `Event.Name` + `Data`) are close enough to confuse, and
   guessing wrong produces a signature failure that says nothing about why.
2. **Event routing.** Read `Event.Code`. Code `1` (`PAYMENT_STATUS_CHANGED`) is
   the only event acted on. Codes 2–7 are ACKed `200` and dropped with a log —
   a non-200 for a transient reason can lose an event permanently, and
   `GetWebhooks` is the only way back (`pitfalls.md` entry 12).

   Dropping happens **before** signature verification, deliberately. Verifying
   an event we then discard would mean implementing and maintaining six more
   field lists to reach the same outcome, and an unverified payload that is
   never acted on cannot do harm — nothing is dispatched, nothing is persisted
   beyond the log line. The one thing this forfeits is knowing whether a
   dropped code-2 event was genuine, which matters only once refunds exist, at
   which point code 2 moves into the verified path.
3. **Signature.** Verify `MyFatoorah-Signature` against **every** configured
   `myfatoorah` connection's `webhook_secret`, the matching one identifying the
   sender — exactly the loop `ApsWebhookController::connectionThatSigned()`
   runs. The docs never say whether one account with several webhook endpoints
   gets one secret or several (`pitfalls.md` entry 14); this design is correct
   either way it turns out, and is required regardless once a second country's
   connection exists.
4. **Replay guard**, then `ProcessPaymentProviderWebhook::dispatch()` with the
   matched connection — unchanged from the shared pipeline.

### Signature recipe

Not `hmac(rawBody, secret)`. MyFatoorah signs a string built from a **named
subset of fields in a prescribed order** (`pitfalls.md` entries 3–4):

1. Take the fields for this event code, in the documented order.
2. Join as `key=value,key2=value2` — no spaces, comma-separated. Keys are the
   **dotted paths** (`Invoice.Id`), not the JSON nesting.
3. A null value becomes the empty string and the key stays:
   `Invoice.Id=6409988,Invoice.Status=PAID,...,Invoice.ExternalIdentifier=`.
4. `base64(hmac_sha256(string, secret))`, compared with `hash_equals`.

`MyfatoorahSignatureService` implements **one** field list — code 1, the only
event this driver acts on, Verified against MyFatoorah's own library:

| Code | Event | Signed fields, in order |
|---|---|---|
| 1 | `PAYMENT_STATUS_CHANGED` | `Invoice.Id`, `Invoice.Status`, `Transaction.Status`, `Transaction.PaymentId`, `Invoice.ExternalIdentifier` |

The remaining lists are recorded here as documentation for whoever extends the
driver, and are **not implemented**. Codes 2–5 are Verified against the library;
codes 6 and 7 are documented but Unverified, because the library itself throws
on them.

| Code | Event | Signed fields, in order | Status |
|---|---|---|---|
| 2 | `REFUND_STATUS_CHANGED` | `Refund.Id`, `Refund.Status`, `Amount.ValueInBaseCurrency`, `ReferencedInvoice.Id` | Verified |
| 3 | `BALANCE_TRANSFERRED` | `Deposit.Reference`, `Deposit.ValueInBaseCurrency`, `Deposit.NumberOfTransactions` | Verified |
| 4 | `SUPPLIER_STATUS_CHANGED` | `Supplier.Code`, `KycDecision.Status` | Verified |
| 5 | `RECURRING_UPDATES` | `Recurring.Id`, `Recurring.Status`, `Recurring.InitialInvoiceId` | Verified |
| 6 | `DISPUTE_STATUS_CHANGED` | `Dispute.DisputeTransactionId`, `Dispute.Status`, `Invoice.Id`, `Invoice.Status`, `Transaction.Status`, `Transaction.PaymentId`, `Invoice.ExternalIdentifier` | Unverified |
| 7 | `SUPPLIER_UPDATE_REQUEST_CHANGED` | `Supplier.Code`, `RequestStatus.Status` | Unverified |

The canonical-string builder itself is event-agnostic — it takes an ordered list
of dotted paths and the `Data` object — so adding an event later is a new
constant, not new logic.

The docblock states the exact field order per event. A wrong order yields a
well-formed hash the gateway rejects, and signing the raw body, the whole `Data`
object, or the fields in payload order all fail *identically* — the error tells
you nothing about which mistake you made.

### Webhook → `TransactionWebhookUpdate`

- **status** — from `Data.Transaction.Status` through the V3 table.
- **amount** — `Data.Amount.ValueInDisplayCurrency`. This is the basis that
  matches the `Order.Currency` we charged and therefore
  `transactions.requested_amount`, which `WebhookProcessor` compares it against.
  `ValueInBaseCurrency` is the figure converted to the account's base currency
  and would put deposits outside the tolerance band and hold them for review —
  the same class of bug the APS adapter documents at length for `amount_in`
  versus `amount`. Left null when the field is absent; the guard skips a null
  amount, and a fallback to the base-currency value would reinstate the bug.
- **currency** — `Data.Amount.DisplayCurrency`.
- **errorMessage** — `Data.Transaction.Error.Message` on `Failed` and `Canceled`
  alike. `WebhookProcessor` writes `error_message` on both, and restricting it
  to `Failed` leaves the customer staring at a bare "Canceled" with the reason
  unread in metadata.
- **metadata** — `myfatoorah_invoice_id`, `myfatoorah_payment_id`,
  `myfatoorah_transaction_id`, `myfatoorah_invoice_status`,
  `myfatoorah_transaction_status`, `myfatoorah_receivable_amount`,
  `myfatoorah_service_charge`, `myfatoorah_event_reference`.

### Duplicate and out-of-order deliveries need no new code

`pitfalls.md` entry 12 states two rules: a success is final, and duplicates
happen — with a success overriding any other status *even when it arrives
second*. Both are already satisfied:

- `ReplayGuard` drops a byte-identical redelivery before it is dispatched.
- `WebhookProcessor` blocks transitions only *away from* `Succeeded`; a
  `Failed → Succeeded` update is applied normally.

This is recorded so the implementation does not add a redundant guard, and so a
future reader knows the requirement was checked rather than missed.

## Status mapping

Two separate tables. V3 today; the V2 table is a stub with no arms, present so
its values can be added without restructuring, and never merged with V3's.

```
V3 transaction   SUCCESS → Succeeded   FAILED → Failed   CANCELED → Canceled
                 INPROGRESS → Processing   AUTHORIZE → RequiresCapture
V3 invoice       PAID → Succeeded   PENDING → Pending   CANCELED → Canceled
default          → Pending
```

`CANCELED` maps to `Canceled`, not to a refunded state — the package has no
`Refunded` payment status, and refund and void outcomes map to `Canceled`
throughout. MyFatoorah's own library maps a webhook `CANCELED` to a synthesised
`Expired`; we do not, because `Expired` is not a status this package models and
V3's UTC `ExpirationDate` makes it unnecessary.

## Retrieve and sync

`retrieve()` calls `GET /v3/invoices/{InvoiceId}` — not
`GET /v3/payments/{paymentId}`, which needs an id we do not have for redirect
flows.

An invoice holds an **array** of transactions, one per attempt, and
`Data.Invoice.Status` does not track them (`pitfalls.md` entry 7). So:

1. Scan `Data.Transactions[]` for **any** entry with `Status == 'SUCCESS'`. If
   one exists the invoice is paid, whatever the other entries say.
2. Otherwise take the entry with the greatest `TransactionDate` and read its
   `Status` and `Error`.
3. With no transactions at all, fall back to `Data.Invoice.Status`.

### The no-transactions message

**Spec defect, corrected.** An earlier draft of step 3 assumed the API returns
a normal envelope carrying an empty `Transactions` array. It does not.
`api-v3.md`, in the 🚧 note above the Get-Invoice-by-InvoiceId definition:

> If the invoice doesn't exist **OR** the invoice exists but has no
> transactions, the API will return the `"Message": "No invoices match this
> InvoiceId"`.

One message, two meanings, and nothing in the response distinguishes them. So
a real invoice with no payment attempt yet arrives as an `IsSuccess: false`
rejection. Taken at face value that made `getInvoice()` return `null`,
`syncTransaction()` log `transactionNotFoundAtProvider` and return false for
every healthy pending deposit, and `MyfatoorahClient` log
`providerTransactionLookupFailed` at warning for each one — while step 3's
branch in the adapter was unreachable in production.

**Decided: treat this message as Pending.** Every `provider_transaction_id`
this driver holds came back from a successful create-payment, so the invoice
does exist and "no attempts yet" is the realistic reading. `getInvoice()`
recognises the message and returns
`['Invoice' => ['Id' => $invoiceId, 'Status' => 'PENDING'], 'Transactions' => []]`,
logged at info because it is a normal state, not a lookup failure. Step 3's
branch is what consumes it, and is now reachable.

The cost of the decision is bounded and one-directional: an invoice MyFatoorah
genuinely does not know reports Pending rather than missing, which leaves a
stale row pending instead of flagging it. Nothing is credited either way.

Genuine failures — HTTP errors, other envelope rejections, non-JSON bodies —
still return `null` and still log via
`PaymentLogger::providerTransactionLookupFailed()`, which carries both the
envelope's assembled message and the truncated raw body.

`getPaymentStatus()` returns the raw provider string from the same lookup, so
the unattempted case reports `PENDING` rather than `unknown`.

Sync matters more than usual here: `pitfalls.md` entry 12 notes V1 retries give
up permanently and V2's are capped at 5, so a webhook can be lost for good.
`syncTransaction()` is the recovery path and credits the ledger exactly as the
webhook would.

## Payment method snapshot

Both the webhook and the invoice lookup carry `Transaction.Card` — `Brand`,
masked `Number`, `ExpiryMonth`/`ExpiryYear`, `FundingMethod` — and the package
has the columns, so `PaymentMethodSnapshot::fromCardData()` is populated from
`Brand` plus the last four of the masked number. `PayloadSanitizer` strips the
card-shaped keys before anything is persisted, so the SAQ-A posture is
unchanged: the snapshot's brand and last four are the only card facts that
survive, which is what those columns exist for.

`PaymentMethodBrand` has no KNET case, so KNET would otherwise land on `Other`.
Add:

```php
case Knet = 'knet';
```

`label()` returns `'KNET'` and `getType()` returns `PaymentMethodType::DebitCard`
— KNET is Kuwait's national debit network. Only those two methods need new arms;
`requiresLastFour()` and `getIcon()` both have `default` arms. Both `label()` and
`getType()` are exhaustive `match ($this)`, so omitting either is an
`UnhandledMatchError` at runtime, not a compile-time miss.

## Testing seams

`WebhookSimulator::make('myfatoorah', $payload, connection: ...)` builds a
delivery signed by the same `MyfatoorahSignatureService` the controller verifies
with, and sets both the `MyFatoorah-Signature` and `MyFatoorah-Webhook-Version:
v2` headers. `Cashier::fakeConnection()` gains `myfatoorah` defaults
(`base_url`, `api_key`, `webhook_secret`, `currency`), so
`fakeConnection('myfatoorah_sau')` yields a viable second-country account
through the existing prefix inference.

## Tests

**Unit — `tests/Unit/Drivers/MyfatoorahAdapterTest.php`**

- Both status tables, including that `Succss` is *not* accepted by the V3 table.
- Webhook amount basis: display currency, not base currency; null when absent.
- `errorMessage` carried on `Canceled` as well as `Failed`.
- Invoice scan: a `SUCCESS` among failures wins; otherwise latest by
  `TransactionDate`; empty array falls back to invoice status.
- All five `IsSuccess: false` shapes, including the no-`IsSuccess` routing error
  and the HTML 403.
- Card snapshot, including KNET → `Knet`/`DebitCard`.

**Unit — `tests/Unit/Drivers/MyfatoorahSignatureServiceTest.php`**

Checked against the canonical strings transcribed **literally** from
`webhooks.md`'s sample event — a vendor reference reproduced in the test, not
the service checked against itself. Covers the code 1 field order, the
null-becomes-empty-string rule (with the key retained), and that the builder
takes its field order from the constant rather than from payload order.

**Feature — `tests/Feature/Webhooks/MyfatoorahWebhookTest.php`**

Valid signature; invalid signature; a tampered field; a signature from an
unknown secret; a missing version header; a `v1` version header; an unhandled
event code (ACKed, nothing dispatched); duplicate replay; out-of-order after
`Succeeded`; unknown transaction; and — with two connections configured — that
the second country's account is matched and rides through to the job.

## Risks

- **The picker path is contested.** The V3 spec says `PaymentMethod` "is
  required for redirection cases", while `DisplayPaymentMethods` says omitting
  both shows every enabled method. Leaving `payment_method` unset is therefore
  the one behaviour in this design that needs confirming against the sandbox
  before a connection relies on it. Setting `payment_method` avoids the question
  entirely, and the config comment says so.
- **Rate limits are unpublished.** `GET /v3/payments` and `GetPaymentStatus` are
  documented as rate limited with no number, no `429` behaviour and no
  `Retry-After` (`pitfalls.md` entry 14). A bulk sync job could hit an
  undocumented wall. `PspHttp::idempotent()`'s retry posture is what we have;
  nothing further is designed for it.
- **Refund events will arrive and be dropped.** A merchant issuing a refund from
  the MyFatoorah portal produces a code 2 webhook that this driver ACKs and
  ignores, so the local transaction stays `Succeeded`. That is a consequence of
  the day-one scope, not an oversight; it is the first thing to revisit when
  refunds are added.
