# APS: docs vs. reality

Where the live API departs from the mirrored documentation, or where the docs
are silent and we learned it the hard way. Each entry cites where we handle it.

This file is hand-written. It is the only thing here the mirrors cannot tell
you, so keep it to genuine divergences — do not restate what `h2h.md` and
`fpf-v3.md` already say.

## 1. There is no `/transactions/{id}` lookup route

The transaction GUID sits directly under the merchant GUID:
`GET /api/v3/{merchantGuid}/{transactionId}`. The plausible-looking collection
path is not routed, and APS answers it with a plain-text `404 page not found` —
indistinguishable from an unknown transaction once the status is discarded. Any
lookup failure is therefore logged with its status rather than swallowed.

`src/Drivers/Aps/ApsClient.php:42-70`

## 2. Callbacks are signed with the callback secret, not the app secret

Outbound requests authenticate with `X-App-Token` and `X-App-Secret`. Callbacks
are HMAC-SHA256 over the **raw** body, delivered in `X-Signature`, signed with a
separately issued callback secret. The two are easy to conflate, and doing so
rejects every callback. Where no callback secret is configured we fall back to
the app secret, which fails closed in exactly that way — so set
`APS_CALLBACK_SECRET`.

Both guides ship a Go reference implementation of this check, including the
warning to use a constant-time comparison. Ours uses `hash_equals`.

`src/Drivers/Aps/ApsClient.php:96-111`

## 3. Callbacks carry no merchant identifier

Every APS account posts to the same callback URL, and nothing in the payload
says which account sent it. The sender is identified by trying each configured
account's callback secret until one verifies — the match is itself the proof.
The matched connection then rides along to the job, so the payload is parsed
under the credentials that signed it.

`src/Http/Controllers/Webhooks/ApsWebhookController.php:68-98`,
`src/Drivers/Aps/ApsProvider.php:218-231`

## 4. One APS account per product

APS issues a separate merchant GUID, app key and callback secret per product.
Binance Pay is not reachable with the card account's credentials. Each account
is its own entry in `config('cashier-core.connections')`, and the account is
chosen by which connection a payment method names.

`src/Drivers/Aps/ApsProvider.php:31-39`

## 5. The BANKCARD `how` URL points at the wrong host for humans

The `how` field returns the PCI gateway's JSON API host, which shows the
customer a raw JSON payment session instead of the card form. The form SPA
serves the identical path from a sibling host, so only the host is rewritten —
path and query are APS's and stay untouched. Hosts absent from
`checkout_host_map` pass through unchanged, making this inert for every other
method and for the day APS returns the form URL directly.

`src/Drivers/Aps/ApsProvider.php:118-142`

## 6. Two status vocabularies, one member unhandled, and `status` swaps meaning by path

Fiscal statuses: `pending`, `canceled`, `expired`, `done`, `failed` (the docs
qualify `failed` as "for Payouts only"). Deposit (sep31) statuses:
`pending_sender`, `pending_external`, `completed`, `error`, and a fifth the
docs say to treat as an error: `pending_transaction_info_update`. `mapStatus()`
has no arm for that fifth value, so it falls through to `default` and maps to
`PaymentStatus::Pending` — a transaction APS considers failed is silently held
as pending.

Callbacks don't just swap which field means what — they add a third
vocabulary. `fiscal_status` carries the fiscal enum on both retrieve and
callback payloads. But on callbacks `status` stops meaning sep31 and instead
carries APS's own PSP-transaction vocabulary — `canceled`, `expired`,
`payed`, `done`, `refund_pending`, `refunded`, `refund_rejected` — while
`sep31_status` sits alongside it holding `completed`/`error`. `mapStatus()`
has no arm for `payed`, `refund_pending`, `refunded` or `refund_rejected`
either, so all four also fall through to `default` → `PaymentStatus::Pending`:
a fully refunded transaction reads as pending. Counting
`pending_transaction_info_update`, that's five unhandled values across the
two paths. And `fiscal_status` — the one field that means the same thing
everywhere — is never read anywhere in the driver; only `status` and
`sep31_status` are captured, on both paths alike.

`src/Drivers/Aps/ApsAdapter.php:86-101`, `src/Drivers/Aps/ApsAdapter.php:43-46`,
`src/Drivers/Aps/ApsAdapter.php:64-67`, `src/Drivers/Aps/ApsAdapter.php:112-122`

## 7. A downstream rejection arrives as `canceled`, not `failed`

APS reports a PSP-side rejection as fiscal status `canceled`, with the real
reason in `external_message` — for example `"512: Desktop devices are not
supported"`. Treating only `failed` as carrying an error message left customers
and support staring at a bare "Canceled" while the reason sat unread in
metadata. The error message is carried on both statuses.

`src/Drivers/Aps/ApsAdapter.php:60-84`, commit `3fe3673`

## 8. Callback payloads nest under a top-level `payload` key

Status callbacks wrap everything in `payload`. Retrieve responses may or may
not, so both paths unwrap defensively with `$payload['payload'] ?? $payload`.

`src/Drivers/Aps/ApsAdapter.php:43-46`, `src/Drivers/Aps/ApsAdapter.php:64-67`

## 9. Money and checkout field names

`amount_in` is what the customer pays, `amount_out` what the merchant receives,
`customer_fee` the end-user's share. The hosted checkout URL is `how` — not
`url`, `link`, or `redirect_url`.

`src/Drivers/Aps/ApsAdapter.php:18-38`

## 10. Two environments, separate credentials

Staging is `https://fpf-api.armenotech.net`, production
`https://fpf-api.proc-gw.com`. Credentials are not shared between them, and both
are issued as one-time email links. Note the production host is FPF-flavoured
but serves the H2H `/api/v3` API too — it is the default `base_url`.

`src/Drivers/Aps/ApsProvider.php:49`

## 11. Documented `retryable` flag on card errors, unused here

The H2H guide's error table marks card-level failures (empty holder, expired
card, invalid CSC, bad check digit …) with `retryable: true` and a specific
`external_status`. Our charge path catches the transport failure and throws a
generic `PaymentProcessingException` without inspecting either field, so a
retryable card error is currently indistinguishable from a hard failure. Known
gap, not a deliberate decision — see the error table in `h2h.md`.

`src/Drivers/Aps/ApsProvider.php:86-96`

## 12. Connection keys the package's own config example omits

The driver reads `callback_secret`, `deposit_method`, `redirect_url`,
`webhook_url` and `checkout_host_map`. None appear in the commented example
block in `config/cashier-core.php:32-38`, which shows only `driver`, `base_url`,
`merchant_guid`, `app_token` and `app_secret`. Anyone configuring a connection
from that example alone gets a driver that throws on `charge()` for the missing
`deposit_method` and silently fails callback verification.

Closing that gap is a code change, out of scope for the skill; recorded here so
it is not lost.

`config/cashier-core.php:32-38`
