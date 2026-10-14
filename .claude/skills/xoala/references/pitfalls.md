# Pitfalls

Each entry cost real time during the driver's design. Verified means fetched
and confirmed against the sources in `SOURCES.md` on 2026-09-09; Unverified
means asserted by the plan/ground truth but not independently reproduced
here.

## 1. The checkout entry point is a form POST, not a URL (Verified)

`/transaction/Checkout` is not a page you can build a link to and redirect a
browser at — the initial request's fields (including the signed checksum)
*are* the POST body the hosted page expects. There is no server-to-server
call that hands back a "checkout URL" for Standard Checkout the way the
asynchronous REST flow does. Treating it like a redirect target produces a
page that rejects the request with no useful diagnostic, because the
required POST fields never arrived. This is why the driver serves a bridge
page that auto-submits a hidden form, rather than issuing an HTTP redirect.
See `standard-checkout.md`.

## 2. Short vs. long status in the callback checksum (Verified)

The callback/notification checksum signs the **short** status
(`transactionStatus`: `Y`/`N`/`P`/`3D`/`C`), per the docs' own worked example
`77251|011E1D8A5C034|156.00|N|<secret>`. Every sample notification payload
Xoala publishes *also* carries a `status` field in the long form
(`capturesuccess`, `payoutsuccessful`, ...). Signing `status` instead of
`transactionStatus` produces a checksum that never verifies, and does so
silently — the payload looks complete and plausible either way. See
`callbacks.md`.

## 3. Amount is hashed as a formatted string (Verified — the format string is
in the spec; the specific case `50` vs `50.00` is the plan's own framing)

Every checksum that includes `amount` includes it as the exact string sent on
the wire, formatted `[0-9]{1,8}\.[0-9]{2}` (i.e. always two decimal places).
`50` and `50.00` are different strings and hash differently, so signing an
unformatted numeric amount produces a checksum that never matches what Xoala
computes on its side. This is why `XoalaSignatureService::amount()` exists as
a single static used everywhere a checksum touches an amount — see the plan's
"Deviations from the spec" note 1.

## 4. `totype` sits inside the checksum (Verified)

`totype` ("Merchant's Partner name") is one of the six fields hashed into the
Standard Checkout request checksum (`standard-checkout.md` rule 1). A wrong
value still produces *a* checksum — just one that doesn't match Xoala's
server-side computation — so the failure surfaces only at the hosted page,
as a generic rejection, with nothing pointing back at `totype` specifically.
There is no client-side way to validate it in advance; it has to be
confirmed against the account.

## 5. The `StandardCheckout` sample response contradicts the spec's own
   parameter table (Verified)

The Standard Checkout Response Parameters table types `status` as `AN2`
`[Y|N|P|3D|C]` — the short form — and `checksum` as `AN32` (implicitly
hex-like, since it's an MD5 digest). The one live sample response Xoala
publishes for this exact flow (`sampleResponse.StandardCheckout`, workflow
`stdkit`) shows:

- `"status": "authsuccessful"` — the long form, not `Y`/`N`/`P`/`3D`/`C`.
- `"checksum": "7OsiGNIhrJVPHPhzk49E6WcjE6tFJYOE"` — 32 characters, but mixed
  upper/lowercase letters and no digits at all, which is not a valid MD5 hex
  digest (always lowercase `0-9a-f`).

Do not trust either the field's documented type or a lone sample over the
actual driver logic tested against the sandbox: this sample is internally
inconsistent with the spec that sits on the same page as it.

## 6. The docs are JavaScript-rendered and blank the brand name (Verified)

`curl` on any `checkout-docs.xoala.com/integration/*.php` page returns full
prose but every "Sample Request" / "Sample Response" block is empty — the
samples are injected by JavaScript at view time from a separate JSON
endpoint. The prose itself is a white-label template: every occurrence of the
brand name is an empty interpolation, producing sentences like "Merchant ID
as shared by ." This is why the samples in this skill were fetched from
`sandbox.paymentplug.com/transactionServices/REST/v2/sampleRequest` and
`.../sampleResponse` rather than from the doc pages — see `SOURCES.md`.

## 7. Checksum field order is not consistent across endpoints (Verified)

Four different orderings of the same secure key exist across the flows this
driver touches:

| Flow | Formula |
|---|---|
| Standard Checkout request | `memberId\|totype\|amount\|merchantTransactionId\|merchantRedirectUrl\|secureKey` |
| Standard Checkout callback | `paymentId\|merchantTransactionId\|amount\|<short status>\|secureKey` |
| Backoffice inquiry | `memberId\|secureKey\|paymentId` |
| REST payment / Backoffice capture/refund | `memberId\|secureKey\|paymentId or merchantTransactionId\|amount` |
| Payout | `memberId\|merchantTransactionId\|amount\|secureKey` |

Copying one flow's formula to another silently produces a checksum that
never verifies. Always check the specific flow's rule before signing.
