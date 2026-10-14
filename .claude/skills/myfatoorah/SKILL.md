---
name: myfatoorah
description: Use when working with the MyFatoorah payment gateway (docs.myfatoorah.com, api.myfatoorah.com) — building or changing a MyFatoorah driver, MyFatoorah webhooks and signatures, or MyFatoorah invoices, payments, payment status, refunds, tokenization, recurring payments and auth-and-capture. Covers both the V2 and V3 API generations, and the GCC country-specific hosts.
---

# MyFatoorah

MyFatoorah publishes no changelog and no API versioning within a generation,
and its own site index omits 52 live pages. This skill vendors the whole
documentation site — 219 pages, including the OpenAPI fragment behind every
endpoint — so the contract is available offline and upstream changes show up as
diffs.

**The driver is built.** It shipped in 2.2.0 and lives in
`src/Drivers/Myfatoorah/`: `MyfatoorahClient` (the V3 HTTP surface and the
envelope guard), `MyfatoorahAdapter` (payload assembly and status mapping),
`MyfatoorahProvider` (the engine-facing driver), and
`MyfatoorahSignatureService` (webhook signatures). It covers deposits,
webhooks and status sync; refunds, tokenization, recurring and auth-and-capture
are documented below but not implemented.

`references/pitfalls.md` is written against the docs and against MyFatoorah's
own PHP library, not against our code; each entry says whether it is Verified
or Unverified. Where the driver already encodes a pitfall, its own comments
cite the entry number.

## References

Read the one you need; they are large.

- `references/pitfalls.md` — **start here.** Fourteen entries: where the live
  contract departs from the documentation, where the docs contradict
  themselves, and what they never say.
- `references/intro.md` — accounts, API keys, the per-country hosts, test and
  live tokens, test cards, payment-method names, ISO lookups.
- `references/payment-flows.md` — embedded, hosted page, invoicing, direct
  (PCI), and every wallet: Apple, Google, Samsung, STC Pay.
- `references/features.md` — payment status, tokenization, refunds, auth &
  capture, recurring, MIT, reporting, the response envelope, idempotency.
- `references/webhooks.md` — V1 and V2 mechanics, the signature recipe, and
  thirteen data models (six V1, seven V2). The file to open for webhook work.
- `references/api-v3.md` — OpenAPI for `/v3/payments`, `/v3/sessions`,
  `/v3/invoices`, `/v3/customers`.
- `references/api-v2.md` — OpenAPI for the `/v2/*` surface.
- `references/SOURCES.md` — provenance, the regeneration script, and the
  verification script.

Mirrored for completeness of the diff, not because this package calls them:
`suppliers.md`, `api-suppliers.md`, `shipping.md`, `api-shipping.md`,
`toolkit.md`, `mobile-sdk.md`, `plugins.md`.

## Before you touch the driver

Five things that will otherwise cost time:

1. **V2 spells success `Succss`.** Not a typo in the docs — MyFatoorah's own
   library compares against that string. V3 spells it `SUCCESS`. Never map the
   two generations through one case-insensitive table. `pitfalls.md` entry 1.
2. **Webhook signatures are HMAC over a canonical `key=value,key2=value2`
   string**, built from a named subset of fields in a prescribed order — not
   over the raw body. V1 sorts the whole `Data` object case-insensitively; V2
   uses a fixed per-event list. Which rule applies is carried in
   `MyFatoorah-Webhook-Version`, a header the docs never mention.
   `pitfalls.md` entries 3–5.
3. **`IsSuccess: false` arrives in five shapes**, including one with no
   `IsSuccess` field and one that is an HTML 403 page rather than JSON. Parse
   defensively and keep the raw body. `pitfalls.md` entry 6.
4. **An invoice holds an array of transactions.** Scan `InvoiceTransactions`
   for a successful one; `InvoiceStatus` alone does not tell you the outcome,
   and `Expired` is a status you compute against the account's timezone rather
   than one the API returns. `pitfalls.md` entries 7–8.
5. **A refund is a request a human approves.** `MakeRefund` returning 200 means
   filed, not refunded; it takes no currency, so the amount is in the account's
   base currency; and duplicate protection is explicitly the merchant's
   problem. `pitfalls.md` entry 10.

## Which generation to build on

Both are live on the same hosts under the same API key, and the site presents
them as peers rather than as old and new. V3 (`POST /v3/payments`,
`GET /v3/payments/{paymentId}`, `PUT /v3/payments/{paymentId}`) is the smaller
and better-shaped surface: REST verbs, uppercase statuses, `OperationType` for
authorize/capture/release, and a payment method named by string rather than by
a numeric id fetched per invoice value. V2 is what the official PHP library and
every e-commerce plugin still use, and it is the only generation whose live
behaviour `pitfalls.md` can cite second-source evidence for.

Build against V3, but write the status map so V2 values can be added without
restructuring — a merchant already integrated on V2, or a webhook still
configured as V1, will hand you the older vocabulary. `pitfalls.md` entry 2
tabulates the differences.

## Adding a MyFatoorah connection

MyFatoorah issues **one API key per country**, and the key must be sent to that
country's host — `api.myfatoorah.com` serves Kuwait, Bahrain, Oman and Jordan,
while Saudi Arabia, the UAE, Qatar and Egypt each have their own. Every country
shares one sandbox at `apitest.myfatoorah.com`. So a merchant trading in two
countries is two connections on one driver, exactly as a second APS account is
(see the `aps-payments` skill).

The authoritative host and timezone map is a public JSON file,
`https://portal.myfatoorah.com/Files/API/mf-config.json`, which is richer than
the table in `intro.md` and is what MyFatoorah's own library reads at runtime.
Treat it as configuration to be refreshed, not as constants.

## Refreshing

MyFatoorah changes the docs without announcement. Follow `## Regenerating` in
`references/SOURCES.md`, then read `git diff` — that diff is the changelog
MyFatoorah does not publish. The generator fails the run when it finds a link
to a page it did not place, which is the only notice you get that something new
was published. `pitfalls.md` is hand-written and is not regenerated; when a
diff touches something an entry asserts, re-check that entry.

`## Verifying` in `SOURCES.md` runs a check per fact `pitfalls.md` rests on,
each tagged with its entry number.
