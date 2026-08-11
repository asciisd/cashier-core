---
name: heropayments
description: Use when working with the Heropayments crypto gateway (api.heropayments.io) — the Heropayment driver, HeropaymentClient/HeropaymentProvider/HeropaymentAdapter/HeropaymentQuoteService, Heropayment callbacks and webhooks, or Heropayment crypto deposits, invoices, rate and minimum-amount quotes, withdrawals and balances. Covers both the V2 and Custody API flows.
---

# Heropayments

Heropayments publishes no OpenAPI spec, no changelog and no API versioning —
only a Postman documenter page. This skill vendors the collection so the
contract is available offline and upstream changes show up as diffs.

## References

Read the one you need; they are large.

- `references/quirks.md` — **start here.** Where the live API departs from its
  own docs, and where the docs are silent. Ten entries, each citing the code in
  this package that handles it. Three are marked **Unverified**: they may be
  live defects.
- `references/overview.md` — auth, HMAC-SHA512 request signing with worked
  samples in five languages, both integration flows, the two status
  vocabularies, multiple-deposit and mistaken-deposit processing, static
  deposit addresses.
- `references/callbacks.md` — callback mechanics and the three annotated
  payloads. The file to open for webhook work.
- `references/v2.md` — the 12 V2 requests. This is the flow the driver uses.
- `references/custody.md` — the 8 Custody requests. Nothing here calls them.
- `references/errors.md` — both error-code tables, V2 and Custody.
- `references/SOURCES.md` — source URLs, fetch date, and the commands to
  regenerate and verify the mirrors.

## Before you change anything

Five things that will otherwise cost time:

1. **A saved deposit address keeps working forever.** Addresses are static per
   `customerId` — we pass the MT5 login — and a repeat send raises a new payment
   with the **same `externalOrderId`** and a new `sequence`. We resolve
   transactions by `externalOrderId` alone and never read `sequence`, so the
   second deposit lands on the first one's transaction. `quirks.md` entry 1.
2. **The field is `paidAmount`, not `actuallyPaid`.** The adapter reads a name
   that appears nowhere in the contract. `quirks.md` entry 2.
3. **Sign the body exactly as Node's `JSON.stringify` would emit it.** PHP's
   default `json_encode` escapes slashes and unicode; both yield
   `401 Invalid signature`. `quirks.md` entry 4.
4. **The id in the create-invoice response is not the id in the callback.** One
   is an invoice, the other a payment. Correlate on `externalOrderId`.
   `quirks.md` entry 6.
5. **V2 and Custody have disjoint status vocabularies.** `waiting`/`confirming`/
   `exchanging`/`sending` against `new`/`pending`/`processing`. Our status map
   knows only the V2 half. `quirks.md` entry 10.

## The code

- `src/Drivers/Heropayment/HeropaymentClient.php` — HTTP surface, signing,
  signature verification
- `src/Drivers/Heropayment/HeropaymentProvider.php` — charge, retrieve,
  webhook correlation
- `src/Drivers/Heropayment/HeropaymentAdapter.php` — response/callback →
  cashier-core objects, status mapping
- `src/Drivers/Heropayment/HeropaymentQuoteService.php` — pre-redirect rate,
  network fee and minimum lookups, with caching
- `src/Drivers/Heropayment/HeropaymentQuote.php` — the quote value object
- `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php` — callback
  entry point, signature check, replay guard

Refunds, capture, authorize and void all throw: Heropayments has no
merchant-initiated refund.

## Refreshing

Follow `## Regenerating` in `references/SOURCES.md`, then read `git diff` — that
diff is the changelog Heropayments does not publish. `quirks.md` is hand-written
and is not regenerated; check whether any entry the diff touches still holds.
