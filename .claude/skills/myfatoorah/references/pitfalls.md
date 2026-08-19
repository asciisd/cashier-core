# MyFatoorah: docs vs. reality

Where the live contract departs from the mirrored documentation, where the docs
contradict themselves, and where they are silent. Each entry says what to do
about it.

This file is hand-written; the generator never touches it. Keep it to genuine
divergences — do not restate what the mirrors already say.

**There is no MyFatoorah driver in this package yet**, so nothing here cites
our code. Every entry is instead marked with how it was established:

- **Verified** — proven against a second source, named in the entry. The
  strongest of these is MyFatoorah's own PHP library, `myfatoorah/library`
  2.2.10, whose git source is `https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Library`
  (Packagist lists no dist tarball, so clone it). What that library does is
  what MyFatoorah's own plugins do against the live API.
- **Unverified** — read out of the docs and internally consistent, but never
  seen against the live API. Confirm before relying on it, and promote the
  entry when you do.

## 1. `Succss` is the real V2 success value, not a typo in the docs

**Verified.** V2 spells the successful transaction status with one `e`:

```
InvoiceTransactions[].TransactionStatus ∈ InProgress | Succss | Failed | Canceled | Authorize
```

It looks like a documentation slip, and treating it as one is the single
fastest way to build a driver that never marks anything paid. It is not a slip:

- 11 occurrences across the mirror, in the OpenAPI `description` strings and in
  four example payloads. `"TransactionStatus": "Success"` appears **nowhere**
  in the V2 surface.
- MyFatoorah's own library tests for it literally:
  `if ($transaction->TransactionStatus == 'Succss')`, in
  `src/API/Payment/MyFatoorahPaymentStatus.php`.

V3 is unaffected — it spells the value `SUCCESS`. So a status map that serves
both generations must accept `Succss`, `SUCCESS` and nothing in between.
See entry 2 for why the case differs as well.

## 2. V2 and V3 are two different APIs on one host, with different vocabularies

**Verified** against both OpenAPI fragments in `api-v2.md` and `api-v3.md`.

They share a base URL and an API key, and nothing in the docs presents them as
generations — the site's own API reference lists them as two peer groups,
"Myfatoorah Api V3" and "Myfatoorah Api V2". They differ in almost everything
that matters to an adapter:

| | V2 | V3 |
|---|---|---|
| Paths | `/v2/ExecutePayment`, `/v2/GetPaymentStatus` | `/v3/payments`, `/v3/sessions`, `/v3/invoices` |
| Verbs | `POST` for reads too | REST: `POST`, `GET`, `PUT` |
| Invoice status | `Pending`, `Paid`, `Canceled` | `PENDING`, `PAID`, `CANCELED` |
| Transaction status | `InProgress`, `Succss`, `Failed`, `Canceled`, `Authorize` | `INPROGRESS`, `SUCCESS`, `FAILED`, `CANCELED`, `AUTHORIZE` |
| Auth & capture | `POST /v2/UpdatePaymentStatus` | `PUT /v3/payments/{paymentId}`, `OperationType` |
| Payment method | numeric `PaymentMethodId` from `InitiatePayment` | string `PaymentMethod`, e.g. `"CARD"` |

Same words, different case, plus the `Succss` split. Map V2 and V3 statuses in
separate tables rather than one case-insensitive table — a case-insensitive
match hides the `Succss`/`SUCCESS` divergence and will silently start dropping
payments the day either side is corrected.

## 3. Webhook signatures are over a canonical string, not the raw body

**Verified** against `src/MyFatoorahHelper.php::checkSignatureValidation`.

Nothing about this webhook is the usual `hmac(rawBody, secret)`. MyFatoorah
signs a string it builds from a **named subset of fields in a prescribed
order**:

1. Take the fields for this event type, in the documented order.
2. Join as `key=value,key2=value2` — no spaces, comma-separated, keys are the
   dotted paths (`Invoice.Id`), not the JSON nesting.
3. A null value becomes the empty string, and the key stays:
   `CreatedDate=...,CustomerEmail=,CustomerMobile=...`.
4. `base64(hmac_sha256(string, secret))`, compared against the
   `MyFatoorah-Signature` header. Compare with `hash_equals`.

Signing the raw body, or the whole `Data` object, or the fields in payload
order, all fail — and fail identically, so the error tells you nothing about
which mistake you made.

## 4. V1 and V2 webhooks order the signature fields by different rules

**Verified** against `src/MyFatoorahWebhook.php` and `MyFatoorahHelper.php`.
This is the part `webhooks.md` does not say out loud, and it is the reason a
verifier that works on one version silently rejects the other.

- **V1** signs the entire `Data` object, with its keys sorted
  **case-insensitively alphabetically** (`uksort($dataModel, 'strcasecmp')`).
  For `EventType == 2` (refund) the `GatewayReference` key is **dropped
  before** sorting.
- **V2** signs a **fixed hand-listed subset per event**, in the documented
  order, which is neither alphabetical nor payload order.

The V2 lists, transcribed from the seven data-model pages in `webhooks.md`:

| Code | Event | Signed fields, in order |
|---|---|---|
| 1 | `PAYMENT_STATUS_CHANGED` | `Invoice.Id`, `Invoice.Status`, `Transaction.Status`, `Transaction.PaymentId`, `Invoice.ExternalIdentifier` |
| 2 | `REFUND_STATUS_CHANGED` | `Refund.Id`, `Refund.Status`, `Amount.ValueInBaseCurrency`, `ReferencedInvoice.Id` |
| 3 | `BALANCE_TRANSFERRED` | `Deposit.Reference`, `Deposit.ValueInBaseCurrency`, `Deposit.NumberOfTransactions` |
| 4 | `SUPPLIER_STATUS_CHANGED` | `Supplier.Code`, `KycDecision.Status` |
| 5 | `RECURRING_UPDATES` | `Recurring.Id`, `Recurring.Status`, `Recurring.InitialInvoiceId` |
| 6 | `DISPUTE_STATUS_CHANGED` | `Dispute.DisputeTransactionId`, `Dispute.Status`, `Invoice.Id`, `Invoice.Status`, `Transaction.Status`, `Transaction.PaymentId`, `Invoice.ExternalIdentifier` |
| 7 | `SUPPLIER_UPDATE_REQUEST_CHANGED` | `Supplier.Code`, `RequestStatus.Status` |

MyFatoorah's own library implements codes 1–5 only and throws `Worng event.`
on 6 and 7, so the dispute and supplier-update events are unproven in the
field even though the docs specify them. Codes 6 and 7 above are therefore
**Unverified**; 1–5 are Verified against the library.

## 5. The header that tells you which signature rule to apply is undocumented

**Verified** against `src/MyFatoorahWebhook.php::getMfHeaders`, which reads it
and throws `Wrong request.` when it is absent.

MyFatoorah sends `MyFatoorah-Webhook-Version: v1|v2` alongside
`MyFatoorah-Signature`. It appears nowhere in `webhooks.md` — neither the
Webhook V1, Webhook V2, nor Webhook Signature page mentions it. Without it
there is no reliable way to know which of the two schemes in entry 4 to apply,
because the payload shapes are close enough to confuse (`EventType` + `Event`
+ `Data` in V1 against `Event.Code` + `Event.Name` + `Data` in V2).

Read the header; treat any value other than `v1` or `v2` as a rejection.
Do not infer the version from the payload shape.

## 6. `IsSuccess: false` arrives in at least five different shapes

**Verified** against `src/MyFatoorah.php::getJsonErrors` and `getHtmlErrors`,
which exist purely to normalise them. The Response Model page in `features.md`
documents only the first.

1. `ValidationErrors: [{Name, Error}]` — the documented envelope.
2. `FieldsErrors: [{Name, Error}]` — the same thing under a different key. The
   library checks `ValidationErrors` and falls back to `FieldsErrors`.
3. `Data.ErrorMessage` — a string on the data object, no error array at all.
4. `Message` + `MessageDetail` and **no `IsSuccess` field**, for a routing
   error: `"No HTTP resource was found that matches the request URI …"`. A
   parser that keys off `IsSuccess` reads this as a success.
5. An **HTML page, not JSON** — `403 Forbidden` from
   `Microsoft-Azure-Application-Gateway/v2` when the caller's IP is blocked.

`ValidationErrors[].Error` may also be an empty string with the field name in
`Name`, so an error message assembled only from `Error` values comes out blank.
Parse defensively and always keep the raw body.

## 7. An invoice holds many transactions; there is no single "the" transaction

**Verified** against `src/API/Payment/MyFatoorahPaymentStatus.php`.

V2's `GetPaymentStatus` returns `Data.InvoiceTransactions[]`, one entry per
attempt. `Data.InvoiceStatus` is only ever `Pending`, `Paid` or `Canceled`, and
it does not track the attempts. To decide the outcome you must:

- scan the array for **any** transaction with `TransactionStatus == 'Succss'`;
  if one exists the invoice is paid, whatever the other entries say;
- otherwise find the last transaction — by `PaymentId` when you have one, else
  the one with the greatest `TransactionDate` — and read its status and
  `Error`.

`GetRefundStatus` is the same shape: `RefundStatusResult` is an array, because
an invoice can carry several partial refunds at once, each with its own
`RefundId`, `RefundStatus` and `RRN`.

## 8. `Expired` is a status you compute, not one the API returns

**Verified** against `MyFatoorahPaymentStatus::getErrorData`.

No V2 or V3 enum contains `Expired`. MyFatoorah's own library synthesises it by
comparing `ExpiryDate` + `ExpiryTime` against **now in the vendor's country
timezone** — and in test mode it hardcodes `Asia/Kuwait` regardless of which
country the account belongs to.

Two consequences. Unpaid-and-past-expiry is indistinguishable from
unpaid-and-still-open unless you do this comparison yourself. And the invoice
expiry fields are **local wall-clock in the account's timezone**, not UTC and
not offset-qualified, so parsing them as UTC shifts every expiry by up to four
hours. Contrast the webhook payloads, which *are* ISO 8601 UTC.

The timezone per country is in the config file described in entry 9.

## 9. Base URL is per country, and MyFatoorah publishes it as JSON

**Verified** — the file is public and was fetched and compared byte-for-byte
against the copy vendored in the library.

`intro.md`'s API-key page gives a six-row table of hosts. The machine-readable
version is richer and is what the library actually reads:

```
https://portal.myfatoorah.com/Files/API/mf-config.json
```

Keyed by 3-letter country code — `KWT`, `SAU`, `ARE`, `QAT`, `BHR`, `OMN`,
`JOR`, `EGY` — each with `v1`, `v2`, `testv1`, `testv2`, `portal`,
`testPortal`, and the `timeZone` entry 8 needs. Four countries share
`https://api.myfatoorah.com` (`KWT`, `BHR`, `OMN`, `JOR`); the rest have their
own host. Every country shares one sandbox, `https://apitest.myfatoorah.com`.

The library caches this file for an hour and, on a `403`, falls back to its
vendored copy — so treat the country map as configuration, not as a constant
compiled into a driver.

**A multi-country account has one API key per country.** The key selects the
country implicitly; pointing a key at the wrong country's host is a
configuration error nothing in the response will spell out for you.

## 10. Refunds are requests for a human to approve, not operations

**Unverified** — stated plainly in `features.md`, not yet seen end to end.

`MakeRefund` does not move money. It files a request that MyFatoorah's finance
team reviews and executes; the customer sees nothing until they do. So:

- A `200` from `MakeRefund` means *filed*, not *refunded*. The outcome arrives
  later as `REFUND_STATUS_CHANGED` (event 2) or via `GetRefundStatus`.
- Refund statuses are `Refunded`, `Canceled`, `Pending` — and here `Canceled`
  means **rejected by MyFatoorah**, not cancelled by you. Note that the same
  word means something else again in the payment vocabulary, where
  MyFatoorah's own library maps webhook `CANCELED` to `Expired`.
- **`MakeRefund` takes no currency.** The amount is in the account's base
  currency, which is not necessarily the invoice's display currency. Send a
  display-currency amount and you refund the wrong number.
- The docs explicitly put duplicate-refund protection on the merchant: "the
  refund request responsibility is on the vendor side not on MyFatoorah Side."
  There is no server-side guard against filing the same partial refund twice.
  Use the idempotency key (entry 11).

## 11. Idempotency exists, is opt-in, and lasts 250 minutes

**Unverified** — documented in `features.md`, not yet exercised.

Send `Idempotency-Key: <unique-value>`. A repeat within **250 minutes** returns
the cached response verbatim instead of acting again. It is the only protection
against a double charge or a double refund on a retry, and nothing turns it on
for you. 250 minutes is an unusual window — long enough to cover a retry storm,
far short of a day — so a nightly reconciliation job cannot lean on it.

## 12. Success is final and can arrive more than once

**Unverified** — stated in `features.md` and in `webhooks.md`, consistent with
the guard in the library's `checkforWebHook2ProcessMessage`.

MyFatoorah states two rules that a webhook handler has to encode:

- **A success status is final and cannot be overridden.** Once a payment reads
  success, later events for that invoice do not undo it.
- **Duplicate events happen**, and not rarely enough to ignore: some methods —
  KNET is named — send MyFatoorah several webhooks, and MyFatoorah forwards
  them. A success event **overrides any other status even if it arrives
  second**, so ordering cannot be assumed.

The webhook and the browser redirect fire at the same moment, and the docs
expect the webhook to land first because it is server-to-server. Both paths
therefore race to update the same order, and both must be idempotent.

Retry behaviour differs by version: V1 retries four times, ~100 s each, 10 s
apart, then gives up **permanently** and only logs the failure; V2's retry
count and delay are configured in the portal, capped at 5 retries and 180 s.
Either way, a handler that returns non-200 for a transient reason can lose the
event for good — `GetWebhooks` exists to re-read missed events, and is the only
way back.

## 13. The site's own index omits 52 live pages

**Verified** — every page was fetched, and each returned 200 with content that
matched no indexed page.

`llms.txt` and the sidebar list 167 pages. 52 more are live and linked from
listed pages, including:

- **Webhook V1** and its six V1 data models, which is the scheme any merchant
  integrated before V2 is still receiving.
- **Every Google Pay and STC Pay guide.** Neither method appears in the
  index at all, though `payment-methods` lists both as supported.
- `rejection-reasons`, `payment-inquiry`, `updatepaymentstatus`,
  `card-view-form`, and the V2-era originals of most `v3-` pages.

They are mirrored here anyway, and the generator's `drift()` check fails the
run when a *new* unlisted page appears. Do not use `llms.txt` alone to decide
whether MyFatoorah documents something.

## 14. Things the docs never state

Gaps rather than divergences. Each will cost time; none has an answer in the
mirror.

- **Rate limits.** `features.md` says `GetPaymentStatus` and V3 `GET /payments`
  are rate limited and that your account manager can raise the limit. No number
  is published, and no `429` or `Retry-After` behaviour is described.
- **Whether webhook secret and API key are separately issued.** The signing
  secret is enabled per webhook in the portal; nothing says whether one account
  with several webhook endpoints gets one secret or several. If it is several,
  a shared endpoint must identify the sender by which secret verifies — the
  problem `aps-payments` entry 3 already solves for another gateway.
- **How to correlate a callback to your order without storing MyFatoorah's
  ids.** The redirect appends `paymentId` only; `Invoice.ExternalIdentifier`
  (V3) and `CustomerReference` (V2) are the fields carrying your own reference,
  and they are different fields in the two generations.
- **What a `PaymentMethodId` is stable against.** V2 requires the numeric id
  from `InitiatePayment`, which is computed per account and per invoice value.
  Nothing states whether an id is stable across calls, so it should be read
  fresh rather than configured — but that is inference, not documentation.
- **No changelog, and no API versioning within a generation.** `updatedAt` per
  page is the only signal, which is why the mirror records it and why the diff
  is the changelog. `SOURCES.md` has the refresh procedure.
