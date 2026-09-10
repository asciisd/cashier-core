---
name: xoala
description: Use when working with the Xoala payment gateway (checkout-docs.xoala.com, secure-checkout.xoala.com) — the Xoala driver, XoalaClient/XoalaProvider/XoalaAdapter/XoalaSignatureService, Xoala callbacks and checksums, or Xoala hosted Standard Checkout deposits, inquiry and status sync. Covers the Standard Checkout, REST and Invoice API flows of the underlying Paymentz platform.
---

# Xoala

Xoala is a white-label of the Paymentz platform. Its documentation site
renders every request/response sample from JavaScript and blanks the brand
name throughout the prose ("Merchant ID as shared by ."), so a plain `curl`
of a doc page returns markup with empty sample blocks — the contract is not
readable from the URL alone. This skill vendors the prose pages together
with the samples fetched from the platform's own sample-payload endpoints,
so the contract is available offline and reproducible from `SOURCES.md`.

**The driver is built.** It lives in `src/Drivers/Xoala/`: `XoalaClient` (the
REST HTTP surface — auth token, inquiry), `XoalaAdapter` (payload assembly
and status mapping, both long and short forms), `XoalaProvider` (the
engine-facing driver), and `XoalaSignatureService` (the four checksum
recipes). It covers hosted Standard Checkout deposits, the callback/webhook
pipeline, and status sync via Backoffice inquiry; refunds, capture, void and
authorize are not implemented (`XoalaProvider::refund()/capture()/void()`
throw). Standard Checkout has **no server-to-server leg** — `charge()`
performs no HTTP; it mints a transaction and hands the engine a signed bridge
route (`XoalaCheckoutController`) that renders a self-submitting HTML form
POSTing straight to `{host}/transaction/Checkout`.

`references/pitfalls.md` is written against the vendored docs and their own
sample payloads, not against our code; each entry is marked Verified where
the source was fetched and read directly.

## References

Read the one you need.

- `references/pitfalls.md` — **start here.** The traps: the checkout entry
  point is a form POST, not a URL; short vs. long status in the callback
  checksum; the amount is hashed as a formatted two-decimal string; `totype`
  sits inside the checksum and fails silently at the hosted page; the
  `StandardCheckout` sample response contradicts its own parameter table; the
  docs are JavaScript-rendered; checksum field order varies by endpoint.
- `references/standard-checkout.md` — the hosted flow, all four checksum
  rules with the docs' worked example, request and response parameter
  tables.
- `references/api-rest.md` — authToken, synchronous/asynchronous payments,
  backoffice (`IN`/`CP`/`RF`/`RV`), payout. Endpoints and checksum formulas,
  which differ per operation.
- `references/statuses.md` — the long status table (success/pending/failed/
  cancelled/reversed/chargeback) and the short `Y`/`N`/`P`/`3D`/`C` form, and
  how they relate.
- `references/callbacks.md` — notification payload shapes: success and
  failure, card and bank-transfer variants, and the one Standard Checkout
  sample that doesn't match its own spec.
- `references/SOURCES.md` — exactly which pages and endpoints were fetched,
  which flows appear in the sample-payload dictionaries, and how to refresh.

## Before you touch the driver

Four things that will otherwise cost time:

1. **Standard Checkout's entry point is a browser form POST**, not a
   redirectable URL — `POST {host}/transaction/Checkout` expects the signed
   field set as its body. There is no server-to-server call that returns a
   "checkout URL" for this flow. `pitfalls.md` entry 1.
2. **Two status vocabularies exist, and mixing them breaks the callback
   checksum.** The checksum signs the *short* code (`transactionStatus`:
   `Y`/`N`/`P`/`3D`/`C`); every real payload also carries a `status` field in
   the long form (`capturesuccess`, `payoutsuccessful`, ...). Sign the wrong
   one and every callback fails verification with no useful error.
   `pitfalls.md` entry 2, `statuses.md`.
3. **Amounts are hashed as formatted strings** (`[0-9]{1,8}\.[0-9]{2}`) —
   `50` and `50.00` hash differently. `XoalaSignatureService::amount()` is
   the single source of truth for that formatting; use it, don't reformat
   inline. `pitfalls.md` entry 3.
4. **Checksum field order is not one formula.** Standard Checkout's request
   checksum puts the secure key last; the callback checksum also puts it
   last but over different fields; Backoffice inquiry puts it second; REST
   payment/capture/refund put it second too but with a fourth field; Payout
   puts it last again with yet another field set. Check the specific flow in
   `standard-checkout.md` or `api-rest.md` before signing. `pitfalls.md`
   entry 7.

## Adding a Xoala connection

One Xoala merchant account is one connection. A second account — even on the
same underlying Paymentz relationship — is another connection on the same
`xoala` driver, exactly as a second APS account is (see the `aps-payments`
skill). Both post to the one `cashier.webhooks.xoala` URL; the payload names
no merchant, so the sender is identified by whose `secure_key` verifies the
checksum. See the `xoala` example in `config/cashier-core.php`.

`totype`, `member_id` and `secure_key` are account-specific and required —
the provider refuses to resolve without them, since a missing or wrong
`totype` fails a live payment at the hosted page with no diagnostic
(`pitfalls.md` entry 4).

## Refreshing

There is no changelog and no page index to diff against. Re-run the two curl
blocks in `references/SOURCES.md` — the prose pages and the two
sample-payload endpoints — and read `git diff` on the reference files; that
diff is the only changelog this integration gets. `pitfalls.md` is
hand-written and not regenerated automatically; when a diff touches
something an entry asserts, re-check that entry.
