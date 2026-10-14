# Xoala driver — design

*2026-09-09*

Add Xoala (`checkout-docs.xoala.com`) as a bundled driver in
`asciisd/cashier-core`, alongside APS, Jenapay, Heropayment, Payport, Sticpay
and MyFatoorah.

Xoala is a white-label of the Paymentz platform: the documentation site is
brand-templated, and its sample payloads are served live from
`sandbox.paymentplug.com`. That matters when reading the docs — several pages
render the brand name as an empty string ("*Merchant ID as shared by* ."), and
the request/response samples are fetched by JavaScript rather than being in the
page source. The vendored copies under `.claude/skills/xoala/references/`
resolve both.

Hosts, from the brand's own property record
(`/transactionServices/REST/v2/getProperty/checkout-docs.xoala.com`):

| Environment | Host |
|---|---|
| Sandbox | `https://secure-checkout-sandbox.xoala.com/` |
| Live | `https://secure-checkout.xoala.com/` |

## Scope

Day one is **deposits, callbacks and sync** — the same scope the Payport driver
ships, and the minimum that credits money safely.

`supports()` reports `charge` and `webhook`. `refund()`, `capture()`,
`authorize()` and `void()` throw `\BadMethodCallException`, matching how the
other hosted-redirect drivers report operations they do not have.

Refund (`RF`) and Reverse (`RV`) are excluded deliberately, not by oversight.
Both key on Xoala's `paymentId`, which does not exist until the customer has
actually paid — see *Identity* below. Adding them later means storing that id at
callback time and adding a second checksum rule, not redesigning anything here.

Payouts (`PO`) are out of scope entirely: withdrawals in this package run
through `WithdrawalWorkflow`, and only the APS driver participates.

## Flow: Standard Checkout

Xoala offers three deposit paths. We build on **Standard Checkout**.

- **Standard Checkout** — the customer's browser POSTs to
  `{host}/transaction/Checkout`; Xoala hosts the payment page. No PCI scope, and
  it covers every method enabled on the account.
- **REST Asynchronous** (`/transactionServices/REST/v1/payments`) returns
  `redirect.url` server-to-server, which would match every existing driver's
  shape exactly — but it only avoids PCI scope for non-card methods. Card brands
  require posting card data from our server.
- **Invoice API** (`invoiceServices/REST/v1/generate`) returns a
  `invoiceDetails.transactionUrl` we could redirect to, but it is built around
  emailing the customer an invoice with an expiration period. That is a
  different product shape from a deposit funnel.

Standard Checkout's cost is that its entry point is a **form POST, not a URL**,
and `PaymentResult` carries a `redirect_url`. That gap is what the bridge below
closes.

## Files

`src/Drivers/Xoala/` — the Payport driver's four-file shape.

| File | Responsibility |
|---|---|
| `XoalaClient` | HTTP surface: `authToken()`, `inquiry()`. Owns the auth-token cache and the response envelope guard. |
| `XoalaSignatureService` | Every MD5 rule in one place: request, callback, redirect-back, inquiry. `hash_equals` on verify. |
| `XoalaAdapter` | `PaymentAdapterInterface`. Status tables, callback → `TransactionWebhookUpdate`, inquiry → `PaymentResult`. |
| `XoalaProvider` | `PaymentProcessorInterface` + `ProvidesWebhookTransactionId` + `PreparesChargeData`. |

Also touched:

- `src/Http/Controllers/XoalaCheckoutController.php` — new, the bridge.
- `src/Http/Controllers/Webhooks/XoalaWebhookController.php` — new.
- `resources/views/xoala/checkout.blade.php` — new, publishable.
- `routes/checkout.php` — new route file for the bridge (see *Routing*).
- `routes/webhooks.php` — `Route::post('/xoala', ...)->name('xoala')`.
- `src/CashierCoreServiceProvider.php` — `'xoala' => Drivers\Xoala\XoalaProvider::class`
  in `BUNDLED_DRIVERS`; `loadViewsFrom()` and the view publish tag; the second
  route group.
- `src/Testing/WebhookSimulator.php` — an `xoala` signing recipe.
- `src/Cashier.php` — `fakeConnection()` defaults for the driver.
- `config/cashier-core.php` — a commented connection example, and a
  `routes.checkout` block.
- `README.md` and `resources/boost/skills/cashier-core-development/SKILL.md` —
  Xoala added to the bundled-driver lists.
- `.claude/skills/xoala/` — new, mirroring the `myfatoorah` skill: `SKILL.md`
  plus `references/` holding the vendored gateway contract (standard checkout,
  API specifications, status descriptions, callback payloads, the checksum
  rules, and a `SOURCES.md` recording where each came from). This is not
  optional polish — the doc site renders its samples from JavaScript and blanks
  the brand name throughout, so without the vendored copies the contract is not
  readable from the URL alone.

Class prefix is `Xoala`. Driver string, route segment and `getName()` are all
`xoala`.

## Connection config

```php
'xoala' => [
    'driver' => 'xoala',
    // Sandbox: https://secure-checkout-sandbox.xoala.com
    'base_url' => env('XOALA_BASE_URL'),
    // Merchant's unique id, assigned by Xoala. Authenticates every request.
    'member_id' => env('XOALA_MEMBER_ID'),
    // Generated in the Xoala dashboard. Signs every checksum; never sent.
    'secure_key' => env('XOALA_SECURE_KEY'),
    // Required, and account-specific: the spec calls it "Merchant's Partner
    // name". It is the second field of the request checksum, so a wrong value
    // fails the payment at the hosted page with no useful message.
    'totype' => env('XOALA_TOTYPE'),
    // Optional. Required by Xoala on some account shapes ("Conditional" in
    // the spec); sent only when set.
    'terminal_id' => env('XOALA_TERMINAL_ID'),
    // Optional. Restricts the hosted page to one method — e.g. CC. Unset
    // shows every method the account has.
    'payment_mode' => env('XOALA_PAYMENT_MODE'),
    'payment_brand' => env('XOALA_PAYMENT_BRAND'),
    // Optional. PA (preauthorization) or DB (debit). Defaults to DB, which
    // authorizes and captures in one step — what a deposit wants.
    'transaction_type' => env('XOALA_TRANSACTION_TYPE', 'DB'),
    // Optional. The currency this connection invoices in; declared to the
    // engine before the charge so it can price the leg. See `currency()`.
    'currency' => env('XOALA_CURRENCY'),
    // Optional — these fall back to the `payment.success` and
    // `cashier.webhooks.xoala` routes where the host defines them.
    'redirect_url' => env('XOALA_REDIRECT_URL'),
    'webhook_url' => env('XOALA_WEBHOOK_URL'),
    // Optional. Language of the hosted page. Defaults to the customer's
    // cashierLocale(), falling back to `en`.
    'language' => env('XOALA_LANGUAGE'),
],
```

A second Xoala merchant account is another connection on the same driver. Both
post to the one `cashier.webhooks.xoala` URL and the payload names no merchant,
so the sender is identified by whose secure key verifies the checksum — the
same resolution MyFatoorah uses.

## Identity: what `provider_transaction_id` holds

`provider_transaction_id` is **our** `merchantTransactionId`, minted as
`DEP-<ulid>`, not Xoala's `paymentId`.

This is forced, not preferred. `charge()` makes no server call, so no Xoala id
exists at the moment the transaction row is written; `paymentId` first appears
in the callback. Payport keys on its order id for the same reason.

Everything downstream follows from that choice:

- `extractWebhookTransactionId()` returns `merchantTransactionId`.
- `retrieve()` inquires by `idType=MID`, which is the lookup keyed on the
  merchant's own id.
- Xoala's `paymentId` is kept in `metadata.xoala_payment_id` when the callback
  brings it, so a later refund implementation has it without a lookup.

## Charge, and the bridge

`charge()` makes **no HTTP call**. It validates, mints the id, resolves the
currency and returns `Pending` with:

```
metadata.redirect_url  → signed route cashier.checkout.xoala, 30-minute expiry
metadata.xoala_fields  → the optional extras the bridge will render
```

`xoala_fields` carries only what the transaction row cannot supply: the
customer's name, email, IP and country, plus the resolved `paymentMode`,
`paymentBrand` and `lang`. Everything the checksum covers — amount, currency,
`merchantTransactionId`, the two URLs — the bridge reads from the transaction
row and the connection config, so there is exactly one authoritative source for
each signed value.

`PaymentResult::requiresAction()` is true whenever `metadata.redirect_url` is
set, so the engine treats this exactly like any other hosted-page charge and
`PaymentLogger::hostedPaymentPageCreated()` fires as usual.

The bridge — `GET {prefix}/xoala/checkout/{merchantTransactionId}` — then:

1. Rejects anything whose signature is absent or expired (`signed` middleware).
2. Loads the transaction by `provider_transaction_id`, scoped to the `xoala`
   driver. Unknown id → 404.
3. Refuses any transaction not `Pending`. A settled or failed deposit must not
   be re-presentable as a payable form.
4. Resolves the provider from the transaction's own `connection`, so a second
   Xoala account is signed with its own key.
5. **Recomputes** the checksum and renders the auto-submitting form.

The checksum is recomputed rather than stored. `metadata` is a plain array cast,
not encrypted, and persisting a request authenticator there buys nothing — the
signature service stays the only place that knows the rule, and there is no
second copy to drift.

```
POST {base_url}/transaction/Checkout
  memberId, totype, amount, currency, merchantTransactionId,
  merchantRedirectUrl, notificationUrl, transactionType,
  checksum, [terminalid, paymentMode, paymentBrand, lang,
  firstName, lastName, email, ip, country]
```

`merchantRedirectUrl` is url-encoded, as the spec requires.

### Amount formatting is load-bearing

The amount the bridge renders and signs is the **charge leg** read from the
transaction row — `charge_amount ?? amount` — formatted
`number_format($amount, 2, '.', '')`, matching the spec's
`[0-9]{1,8}\.[0-9]{2}`.

The checksum is computed over that *string*. `50` and `50.00` are different
inputs producing different hashes, so the formatting is not cosmetic: get it
wrong and Xoala rejects the payment at the hosted page. One helper formats it,
used by both the field set and the signature service.

`prepareChargeData()` declares the connection's currency to the engine before
the charge, exactly as Payport does, so a foreign-currency connection has its
leg converted and priced before anything is signed. Where no currency is
configured this resolves to `cashier-core.currency.default` and the engine
converts nothing.

## Checksums

Four rules, all MD5 over pipe-joined values, all in `XoalaSignatureService`.

| Purpose | Composition |
|---|---|
| Checkout request | `memberId\|totype\|amount\|merchantTransactionId\|merchantRedirectUrl\|secureKey` |
| Callback (notification) | `paymentId\|merchantTransactionId\|amount\|transactionStatus\|secureKey` |
| Redirect-back (browser POST) | `paymentId\|merchantTransactionId\|amount\|status\|secureKey` |
| Inquiry | `memberId\|secureKey\|<id being inquired>` |

The callback and redirect-back rules are the same rule under two field names.
The docs' worked example is `77251|011E1D8A5C034|156.00|N|<secret>` — the
**short** status, `Y`/`N`/`P`/`3D`/`C`. On a callback that value arrives as
`transactionStatus` (the payload also carries a long `status` such as
`capturesuccess`); on the redirect-back the response-parameter table names the
short value `status`. The `StandardCheckout` sample response contradicts this by
showing a long `status` alongside a non-hex `checksum`; it is sample noise, and
the worked example is what we implement against.

Verification uses the amount **string exactly as received**, never a reparsed
float. Re-formatting `156.0` back to `156.00` to make a hash match would be
forging agreement with ourselves.

## Callback handling

`XoalaWebhookController` follows the MyFatoorah controller:

1. Parse the body as JSON or form-encoded — the docs commit to neither, and the
   sample payload's nested `result`/`card`/`customer` objects suggest JSON while
   the platform's request format section specifies
   `application/x-www-form-urlencoded`. Both are accepted.
2. Try each `xoala` connection's secure key in turn; a match identifies the
   sending account. No match → `WebhookRejected`, 403.
3. `ReplayGuard::claim()` — duplicate delivery ACKs without processing.
4. Dispatch `ProcessPaymentProviderWebhook` and `WebhookReceived`.

Signature verification honours `signatureVerificationEnabled()` like every other
controller.

### Status mapping

Long `status` first; `transactionStatus` when it is absent.

| Xoala | `PaymentStatus` |
|---|---|
| `capturesuccess`, `settled`, `authsuccessful` | `Succeeded` |
| `begun`, `authstarted`, `capturestarted`, `cancelstarted`, `markedforreversal` | `Pending` |
| `authfailed`, `capturefailed`, `failed` | `Failed` |
| `cancelled`, `authcancelled` | `Canceled` |
| `reversed`, `chargeback` | `Canceled` |
| *(fallback)* `Y` / `N` / `P`, `3D` / `C` | `Succeeded` / `Failed` / `Pending` / `Canceled` |

`reversed` and `chargeback` map to `Canceled` on the package's own authority:
`WebhookProcessor` documents that "a settled deposit may only move to Canceled
(refund/chargeback)", and any other status arriving after `Succeeded` is dropped
as out-of-order. Mapping them anywhere else would make a chargeback a silent
no-op.

The payout statuses (`payoutsuccessful`, `payoutstarted`, `payoutfailed`) are
not mapped. They cannot reach a deposit transaction, and mapping them would
imply a payout scope this driver does not have.

## Inquiry and sync

`retrieve()` powers `PaymentService::syncTransaction()`, which is how a dropped
callback gets recovered — and unlike the callback, it re-enters through
`WebhookProcessor`, so a recovered success still credits the ledger.

`XoalaClient::authToken()` POSTs to `/transactionServices/REST/v1/authToken`
with `authentication.sKey`, and caches the token per connection for 55 minutes
against its documented 1-hour life. `inquiry()` then POSTs to
`/transactionServices/REST/v1/inquiry` with the token in the `authtoken` header:

```
paymentType=IN, idType=MID, merchantTransactionId=<ours>,
authentication.memberId, authentication.checksum
```

A lookup that cannot be completed returns `null`, and `syncTransaction()` logs
`transactionNotFoundAtProvider` — it does not throw, and it never invents a
status.

## Routing

The bridge is a browser `GET` returning HTML, so it does not belong in the
`api/webhooks` group. A second route group is registered alongside it, under the
same `Cashier::$registersRoutes` / `routes.enabled` guard:

```php
'routes' => [
    // ... existing webhook block unchanged ...
    'checkout' => [
        'prefix' => env('CASHIER_CHECKOUT_PREFIX', 'cashier'),
        'middleware' => ['signed', 'throttle:cashier-checkout'],
        'name_prefix' => 'cashier.checkout.',
    ],
],
```

No `web` middleware: the page holds no session and no CSRF token, and requiring
the host's `web` group would be a surprising coupling for a page whose only job
is to submit to an external host.

The Blade view is publishable under the existing publish tags so a host can
brand the brief "redirecting to Xoala" interstitial. It includes a plain submit
button in a `<noscript>` block, so the flow still completes without JavaScript.

## Testing

Pest, under `tests/Unit/Drivers/` and `tests/Feature/Webhooks/`, following the
existing driver tests.

- `XoalaSignatureServiceTest` — each of the four rules, including the docs'
  worked example `77251|011E1D8A5C034|156.00|N|…` verbatim; a tampered amount is
  rejected; a `156.0` amount does not verify against a `156.00` signature.
- `XoalaAdapterTest` — every status-table row; the short-status fallback; the
  `reversed` → `Canceled` mapping; `paymentId` captured into metadata.
- `XoalaProviderTest` — `charge()` performs no HTTP; the returned
  `redirect_url` is a valid signed URL; `requiresAction()` is true;
  `prepareChargeData()` declares the connection currency; `refund()`,
  `capture()`, `authorize()` and `void()` throw.
- `XoalaCheckoutControllerTest` — an unsigned URL is 403; an expired one is 403;
  an unknown id is 404; a `Succeeded` transaction is refused; a valid request
  renders the form with a checksum matching the signature service, and the
  amount rendered is the charge leg on a converted deposit.
- `XoalaWebhookControllerTest` — valid callback dispatches the job; invalid
  checksum is 403 and dispatches nothing; the second of two connections is
  matched by its own key; a replayed body ACKs without dispatching; both JSON
  and form-encoded bodies are accepted.

`WebhookSimulator` gains an `xoala` recipe so host applications can sign test
callbacks.

## Assumptions to confirm in the sandbox

These are decided in code, and each is written so confirming or correcting it is
a one-line change. None blocks implementation.

1. **The inquiry checksum's third field.** The spec gives
   `memberId|secureKey|paymentId`, but an `idType=MID` inquiry sends no
   `paymentId`. We sign the id actually sent — the `merchantTransactionId`.
2. **`totype`'s value.** Documented only as "Merchant's Partner name", and it is
   inside the request checksum. It comes from the account, hence config with no
   default.
3. **Callback content type.** Both JSON and form-encoded are parsed, so this is
   already answered either way; confirming it lets the loser be deleted.
4. **Whether `/transaction/Checkout` accepts GET.** If it does, the bridge
   collapses into a plain URL and the route, controller and view all go away.
   Nothing else in the design depends on the answer.

## Out of scope

Refunds, reversal, capture, preauthorization as a standing mode, payouts,
tokenization, recurring, the QR flows, the Invoice API, and the marketplace and
KYC APIs on the same platform.
