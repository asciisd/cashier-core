# Standard Checkout

Xoala is a white-label of the Paymentz platform (see `SOURCES.md`). Standard
Checkout is its hosted-page flow: the merchant posts an initial request, the
customer supplies card/account details on Xoala's own page, and the customer
is redirected back with a status.

## Hosts

- Sandbox: `https://secure-checkout-sandbox.xoala.com`
- Live: `https://secure-checkout.xoala.com`

These come from the brand's own host record — `preprod_url` / `live_url` in
`https://sandbox.paymentplug.com/transactionServices/REST/v2/getProperty/checkout-docs.xoala.com`
— not from the prose, which blanks the brand name throughout ("Merchant ID as
shared by ."). See `SOURCES.md`.

## Entry point is a form POST, not a URL

> Standard checkout request has to be sent to our endpoint i.e.
> `/transaction/Checkout` using POST method.

There is no GET-able "checkout URL" to redirect a browser to. The initial
payment request — authentication, mode/brand/type/amount, `merchantTransactionId`,
`merchantRedirectUrl`, and the request checksum below — is itself the payload
of a browser-originated form POST to `{host}/transaction/Checkout`. A
server-to-server call gets nothing usable back; the page has to receive the
POST directly, which is why the driver serves a bridge page that auto-submits
a hidden form with these fields instead of trying to redirect to a URL.

## Workflow

1. Merchant posts the initial payment (the form fields above) to
   `{host}/transaction/Checkout`.
2. Customer fills in card/account details on Xoala's hosted page.
3. Customer is redirected to `merchantRedirectUrl` with POST parameters
   carrying the outcome (the Response Parameters below). The merchant can
   also confirm the outcome out-of-band with the Backoffice Inquiry API
   (`api-rest.md`).

`merchantRedirectUrl` must be url-encoded in the initial request.

## The four checksum rules

All four are MD5, pipe-joined, lower-case hex. **Two of the four put the
secure key in a different position than the other two** — do not assume one
formula covers "the checksum."

### 1. Request checksum (signs the initial POST)

```
md5(memberId|totype|amount|merchantTransactionId|merchantRedirectUrl|secureKey)
```

`totype` — "Merchant's Partner name" — sits inside this hash. A wrong value
signs a valid-looking checksum that Xoala's hosted page simply rejects, with
no error surfaced back to the merchant; see `pitfalls.md`.

### 2. Response checksum (per the parameter-table description)

```
md5(PaymentId|merchantTransactionId|amount|status|secureKey)
```

The spec's own Response Parameters table (below) types `status` here as the
*short* form (`AN2`, `[Y|N|P|3D|C]`) — but see the Standard Notification rule
next, and `pitfalls.md`'s note on the sample response, before trusting that.

### 3. Standard Notification / Callback checksum

```
md5(paymentId|merchantTransactionId|amount|<short status of transaction>|secureKey)
```

The docs' own worked example:

```
77251|011E1D8A5C034|156.00|N|<merchant secret key>
```

`short status of transaction` is explicit here — this is the Y/N/P/3D/C form,
not the long `capturesuccess`/`authsuccessful`/... form. On the actual
callback/redirect-back payload, the short value arrives in the
`transactionStatus` field; a `status` field carrying the long form is also
present in every sample response Xoala publishes, and signing that instead
fails every callback silently (checksum mismatch, no diagnostic). See
`pitfalls.md` and `callbacks.md`.

### 4. Inquiry checksum (Backoffice `IN`)

```
md5(memberId|secureKey|<id>)
```

`<id>` is the `paymentId` or `merchantTransactionId` being inquired about.
Secure key is **second**, not last — the opposite order from rule 1. See
`api-rest.md` for the sibling Capture/Refund/Reversal/Payout formulas, which
each put the secure key in yet another position.

## Request parameters (Standard Checkout)

| Parameter | Description | Format | Required |
|---|---|---|---|
| `memberId` | Merchant's unique ID, assigned by Xoala. | `N10` `[0-9]` | Yes |
| `totype` | Merchant's Partner name. Part of the request checksum. | `AN30` `[a-zA-Z0-9]` | Yes |
| `amount` | Transaction amount, with a literal `.` decimal separator. | `N11` `[0-9]{1,8}\.[0-9]{2}` | Yes |
| `TMPL_AMOUNT` | Customer-facing display amount; may equal `amount`. | `N11` `[0-9]{1,8}\.[0-9]{2}` | No |
| `currency` | Transaction currency. | `AN3` `[a-zA-Z0-9]{3}` | Conditional |
| `TMPL_CURRENCY` | Display currency for `TMPL_AMOUNT`. | `AN3` | No |
| `merchantTransactionId` | Merchant-assigned, unique per transaction, shown on the customer's statement. | `AN100` | Yes |
| `checksum` | MD5 request checksum — see rule 1 above. | `AN32` `[a-zA-Z0-9]` | Yes |
| `merchantRedirectUrl` | Where the customer lands after payment, url-encoded. | `AN100` | Yes |
| `notificationUrl` | Server-to-server callback URL for status changes. | `AN100` | No |
| `orderDescription` | Free-text order description. | `AN255` | No |
| `ip` | Customer IP. | — | No |
| `firstName` / `lastName` | Customer name. | `A50` | No |
| `dateOfBirth` | `YYYYmmDD`. | `N8` | No |
| `country` / `city` / `state` / `postcode` / `street` | Shipping address fields. | various | No |
| `phone` / `telnocc` / `email` | Contact fields. | various | No |
| `terminalid` | Merchant's terminal ID. | `N6` | Conditional |
| `paymentMode` | e.g. `CC`. | `AN10` | Conditional |
| `paymentBrand` | e.g. `VISA`, `MC`. | `AN10` | Conditional |
| `customerId` / `customerBankId` | Merchant/bank-side customer identifiers. | `AN100` / `AN20` | Conditional |
| `lang` | Hosted-page language; defaults to English. | `A3` | No |
| `voucherNumber` / `voucherCode` | Voucher fields. | `AN20` | Conditional |
| `accountid` | Merchant's account ID. | `N10` | No |
| `transactionType` | `PA` (preauthorize) or `DB` (authorize + capture in one step). | `A2` `(PA\|DB)` | Yes |
| `attemptThreeD` | `only3D` \| `3D` \| `direct` — force or opt out of 3-D Secure. | `AN10` | No |

## Response parameters (redirect-back / Standard Notification)

| Parameter | Description | Format | Required |
|---|---|---|---|
| `paymentId` / `trackingid` | Xoala's transaction ID. | `N10` | Yes |
| `registrationId` / `token` | Tokenization reference, if enabled. | `AN255` | Conditional |
| `merchantTransactionId` / `desc` | Echoes the request's ID. | `AN100` | Yes |
| `amount` | Transaction amount. | `N10` `[0-9]{1,7}\.[0-9]{2}` | Yes |
| `checksum` | Response checksum. Table says `AN32` `[a-zA-Z]{1,32}` — see `pitfalls.md` for how the actual sample contradicts even this. | `AN32` | Yes |
| `descriptor` | Merchant display name. | `AN30` | No |
| `currency` | Transaction currency. | `A3` | Conditional |
| `status` | **Short** status per this table: `Y` success, `N` failed, `P` pending, `3D` pending 3-D auth, `C` cancelled. | `AN2` `[Y\|N\|P\|3D\|C]` | Yes |
| `transactionStatus` | The short status, as it actually arrives on notification payloads (see `callbacks.md`). | — | — |
| `resultCode` | Numeric result code — see `response-codes` in `SOURCES.md`. | `N10` | Yes |
| `resultDescription` | Human-readable result text. | `AN255` | Yes |
| `result.bankCode` / `result.bankDescription` | Bank-side result, when available. | — | No |
| `cardBin` / `cardLast4Digits` | Card fragment, when a card was used. | `N6` / `N4` | No |
