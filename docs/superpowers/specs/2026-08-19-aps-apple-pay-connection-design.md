# APS Apple Pay Connection — Design

**Date:** 2026-08-19
**Status:** Approved for planning
**Scope:** Document how a second APS merchant account — Apple Pay, with its own
credentials — is added, in the three places that are actually read. No driver
code changes.

## Problem

APS issues a separate merchant account per product: its own merchant guid, app
key, app secret, callback secret and deposit method. Adding Apple Pay therefore
means adding an account, not a payment integration.

The driver was already built for this. [`ApsProvider`](../../../src/Drivers/Aps/ApsProvider.php)
takes one account's config per instance and its constructor docblock says so.
[`ApsWebhookController`](../../../src/Http/Controllers/Webhooks/ApsWebhookController.php)
identifies which account sent a callback by trying each configured account's
callback secret in turn, because APS callbacks carry no merchant identifier.
[`tests/Feature/Webhooks/ApsWebhookTest.php:141`](../../../tests/Feature/Webhooks/ApsWebhookTest.php)
already proves a second APS account verifies its own callbacks. A third account
is a config entry and nothing else.

That fact is not written down anywhere. The README shows a second APS account
abbreviated to `// ...`, the published config stub shows only one, and the
`aps-payments` skill — the file an agent reads before touching APS — does not
mention accounts at all. So the next person to add an APS account starts by
reading the driver to find out whether the driver needs changing.

## Goals

1. Someone adding an APS account can copy a complete, correct connection block
   without reading `src/`.
2. The single most time-saving fact — that no driver code is involved — is
   stated where it will be found.
3. The two steps that are not obvious from the config keys are recorded: where
   the `deposit_method` guid comes from, and why the new account needs no
   webhook route of its own.

### Non-goals

- Changing driver behaviour. This spec adds documentation only.
- Adding the real Apple Pay credentials to any config. Credentials belong to the
  host application, not to this package.
- A `pending_url` config key. Neither the Host-to-Host guide nor Fast Payment
  Flow v3 defines such a field; a deposit accepts `redirect_url` and
  `status_callback_url` only. See "Rejected" below.
- An artisan command wrapping `ApsClient::info()`. Considered and deferred —
  see "Rejected".
- New tests. Multi-account callback matching is already covered.

## The connection

Every credential APS issues for the Apple Pay account maps onto a config key
that already exists:

| APS issues | Config key |
|---|---|
| App Key | `app_token` |
| App Secret | `app_secret` |
| Merchant Guid | `merchant_guid` |
| Deposit Method | `deposit_method` |
| Callback Secret Key | `callback_secret` |
| Endpoint Url | `base_url` |

Yielding:

```php
'aps_apple_pay' => [
    'driver'          => 'aps',                              // same driver, different account
    'base_url'        => env('APS_APPLEPAY_BASE_URL'),
    'merchant_guid'   => env('APS_APPLEPAY_MERCHANT_GUID'),
    'app_token'       => env('APS_APPLEPAY_APP_TOKEN'),
    'app_secret'      => env('APS_APPLEPAY_APP_SECRET'),
    'callback_secret' => env('APS_APPLEPAY_CALLBACK_SECRET'),
    'deposit_method'  => env('APS_APPLEPAY_DEPOSIT_METHOD'),
],
```

`redirect_url` and `webhook_url` are deliberately absent. They are optional and
fall back to the `payment.success` and `webhooks.aps` routes; every APS account
posts to that one callback URL by design, since the controller tells accounts
apart by signature rather than by endpoint.

`checkout_host_map` is also absent. That rewrite exists because the BANKCARD
`how` URL points at the PCI gateway's JSON API host; hosts absent from the map
pass through untouched, so it is inert for a wallet method unless testing shows
Apple Pay returns a URL on a mapped host.

## Changes

### 1. `config/cashier-core.php`

The commented example at line 32 shows a single `'aps'` connection. Append a
second entry, `'aps_apple_pay'`, inside the same comment block: the full key
list above, plus a one-line note that `redirect_url` and `webhook_url` are
omitted because all APS accounts share the `webhooks.aps` route.

This is the file a host application copies when it publishes config, so the
multi-account shape has to be visible there and not only in the README.

### 2. `README.md`

The connections block at line 66 shows `'aps_binance'` with its body elided to
`// ...`. Replace that entry with `'aps_apple_pay'`, spelled out in full as
above.

`aps_binance` is not otherwise load-bearing in the README — the two later
mentions at lines 222 and 225 are testing-helper examples and stay as they are,
so the file still demonstrates that connection names are arbitrary.

After the code block, add two sentences:

- A second APS account is configuration only; no driver code, no new route, and
  no webhook registration.
- Its `deposit_method` guid comes from `GET /api/v3/{merchantGuid}/info` on that
  account.

### 3. `.claude/skills/aps-payments/SKILL.md`

Add an *Adding another APS account* section between "Before you change anything"
(line 27) and "The code" (line 48), holding a four-step checklist:

1. Obtain the account's credentials from APS.
2. Read the method guid from `/info` on that account — `ApsClient::info()`
   implements the call, though nothing in the package currently invokes it.
3. Add the connection to `cashier-core.connections`.
4. Run `cashier:check`, which resolves every connection and so fails loudly on
   missing credentials — `ApsProvider`'s constructor throws without a merchant
   guid, app token or app secret.

The section leads with the fact that no driver code is involved, because that is
what a reader of this file most needs to know before starting.

## Rejected

**A `pending_url` config key.** Requested in the original credential list, but no
such field exists in either mirrored APS guide, and the requester confirmed they
were unsure it existed and asked to follow the documentation. Adding a key the
API ignores would be worse than omitting it: it reads as supported.

**An artisan `cashier:aps-methods` command** wrapping the unused
`ApsClient::info()`. It would make finding a deposit method guid a command
rather than a curl, and it is real code with real tests — out of scope for a
documentation change. Worth revisiting when a fourth APS account appears.

## Verification

Documentation only, so verification is by inspection plus the existing suite:

1. The connection block in the README, the config stub and the skill agree on
   the same key list — no key present in one and missing from another.
2. Every config key named appears in `ApsProvider` or `ApsClient`; nothing is
   documented that the driver does not read.
3. `vendor/bin/pest` passes unchanged — no `src/` or `tests/` file is touched.

## Risks

Low. The only way this misleads is by drifting from the driver: if `ApsProvider`
later gains or renames a config key, three files now state the old list. The
`aps-payments` skill already carries that maintenance burden for the API
contract, and this section sits beside it.
