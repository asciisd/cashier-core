---
name: aps-payments
description: Use when working with the APS payment gateway (merchant.aps.money) — the Aps driver, ApsClient/ApsProvider/ApsAdapter, APS webhooks and callbacks, or APS deposits, remits, refunds, balance and payment-method lookups. Covers the Host-to-Host and Fast Payment Flow v3 API contracts.
---

# APS Payments

APS publishes no OpenAPI spec, no changelog and no API versioning. This skill
vendors its two integration guides so the contract is available offline and
upstream changes show up as diffs.

## References

Read the one you need; they are large.

- `references/quirks.md` — **start here.** Where the live API departs from its
  own docs, and where the docs are silent. Twelve entries, each citing the code
  in this package that handles it.
- `references/h2h.md` — Host-to-Host guide. The `/api/v3` surface this package
  actually uses: `/info`, `POST /transactions`, status lookup, refunds, balance,
  transaction history, and the card error table with `retryable` flags.
- `references/fpf-v3.md` — Fast Payment Flow v3 guide, APS's hosted checkout UI.
  Relevant when touching the `how` URL or `checkout_host_map`.
- `references/SOURCES.md` — source URLs, fetch date, and the commands to
  regenerate and verify the mirrors.

## Before you change anything

Five things that have already cost time:

1. **Callbacks are signed with the callback secret, not the app secret.**
   HMAC-SHA256 over the raw body, in `X-Signature`. Conflating the two rejects
   every callback.
2. **Callbacks carry no merchant identifier.** With several APS accounts on one
   URL, the sender is found by trying each account's callback secret.
3. **A rejected payment arrives as `canceled`, not `failed`,** with the reason in
   `external_message`.
4. **There is no `/transactions/{id}` route.** It is
   `/api/v3/{merchantGuid}/{transactionId}`; the wrong path returns a plain-text
   `404 page not found`.
5. **There are three status vocabularies, not two, and `status` means a
   different one depending on the path.** Fiscal (`done`, `canceled`,
   `expired`, …) and sep31 (`completed`, `pending_external`, …) appear on
   retrieve; callbacks put a third under the same `status` field (`payed`,
   `refund_pending`, `refunded`, …). All are mapped, but `fiscal_status` — the
   one field meaning the same thing everywhere — is still never read.

## Adding another APS account

**No driver code is involved.** APS issues a separate account per product — own
merchant guid, app key, app secret and callback secret — and `ApsProvider` is
built to serve exactly one of them per instance. A new account is a new entry in
`cashier-core.connections` on the same `aps` driver, and nothing else:

1. Get the account's credentials from APS.
2. Read its payment method guid from `GET /api/v3/{merchantGuid}/info` on that
   account. `ApsClient::info()` implements the call, though nothing in the
   package invokes it yet.
3. Add the connection. `deposit_method` is that guid; `redirect_url` and
   `webhook_url` can be omitted, because every APS account posts to the one
   `webhooks.aps` route and `ApsWebhookController` identifies the sender by
   whose callback secret verifies the signature.
4. Run `cashier:check`. It resolves every connection, and `ApsProvider`'s
   constructor throws without a merchant guid, app token or app secret — so a
   half-filled account fails there rather than at the first charge.

`checkout_host_map` is only for the card gateway's JSON API host; unlisted hosts
pass through untouched, so leave it off other methods unless a checkout URL
actually arrives on a mapped host.

## The code

- `src/Drivers/Aps/ApsClient.php` — HTTP surface, auth headers, signature check
- `src/Drivers/Aps/ApsProvider.php` — charge, refund, retrieve, per-account config
- `src/Drivers/Aps/ApsAdapter.php` — response/callback → cashier-core objects,
  status mapping
- `src/Http/Controllers/Webhooks/ApsWebhookController.php` — callback entry point,
  account matching, replay guard

## Refreshing

APS changes the docs without announcement. Follow `## Regenerating` in
`references/SOURCES.md`, then read `git diff` — that diff is the changelog APS
does not publish. Regeneration restores the plain image links, so the diagram
transcriptions must be re-applied; they are marked `<!-- source: … -->`.
