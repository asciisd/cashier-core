# REST API

Xoala's REST surface is the server-to-server alternative to Standard
Checkout: synchronous and asynchronous payments, backoffice payment
operations, and payout. All of it lives under `transactionServices/REST/v1/`
on the same sandbox/live hosts as Standard Checkout.

## Auth token

```
POST {host}/transactionServices/REST/v1/authToken        (merchant token)
POST {host}/transactionServices/REST/v1/partnerAuthToken  (partner token)
```

Send `authentication.sKey` as the merchant's (or partner's) secret key. The
returned `AuthToken` is a JWT, valid for **1 hour**, sent on every subsequent
REST call. Regeneration is a separate documented flow ("Regenerate Auth
Token") once the token expires.

Sample response (`sampleResponse.AUTH_TOKEN` — see `SOURCES.md`):

```json
{
  "partnerId": "489",
  "memberId": "11344",
  "result": { "code": "200", "description": "Token generated successfully" },
  "timestamp": "2018-06-09 12:47:43",
  "LoginName": "testdoc",
  "AuthToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...."
}
```

The sample request shows a `merchant.username` alongside
`authentication.sKey` — see the plan's "Deviations from the spec" note on the
`username` config key; the merchant-token page itself documents only the
sKey.

## Synchronous and asynchronous payments

```
POST {host}/transactionServices/REST/v1/payments
```

Both workflows use the same endpoint and the same `paymentType`:

- `PA` — Preauthorization. Capture separately via Backoffice `CP`.
- `DB` — Debit. Authorizes and captures in one step.

Synchronous returns the outcome directly. Asynchronous returns a redirect
target instead: the customer is redirected there (POSTing along any
parameters present in the initial response, or simply forwarding to
`redirect.url` if none), and — once the customer returns to
`merchantRedirectUrl` with a `resourcePath` parameter — the merchant fetches
the status with:

```
GET {baseUrl}{resourcePath}    e.g. resourcePath=/transactionServices/REST/v1/payments/{id}
```

Checksum for the initial payment request (both flows):

```
md5(memberId|secureKey|merchantTransactionId|amount)
```

Note the secure key is **second** here, matching the inquiry checksum's
position but not the Standard Checkout request checksum's (secure key last —
`standard-checkout.md` rule 1).

The asynchronous page separately documents a "Hashing Rule for Inquiry" using
the *same* four-field formula above (`memberId|secureKey|merchantTransactionId|amount`)
and a distinct "Hashing Rule for Get Status" using the three-field
`memberId|secureKey|paymentId` — i.e. the same shape as Backoffice `IN`
below. Match the formula to which fields you're actually sending
(`merchantTransactionId`+`amount` vs. bare `paymentId`), not to the field's
name alone.

## Backoffice (CP / RF / RV / IN)

```
POST {host}/transactionServices/REST/v1/payments/{id}   (CP, RF, RV)
POST {host}/transactionServices/REST/v1/inquiry          (IN)
POST {host}/transactionServices/REST/v1/getTransactionList
```

All four require the auth token in the header (see above).

| `paymentType` | Meaning | Constraint |
|---|---|---|
| `CP` | Capture | Against a preauthorized (`PA`) payment. Full or partial. |
| `RF` | Refund | Against a debited or captured payment. |
| `RV` | Reverse/Cancel | Against a preauthorized payment only. No partial reversal. |
| `IN` | Inquiry | By `paymentId` or `merchantTransactionId`. `idType=MID` selects the latter. |

Checksum formulas (secure key position differs by operation — do not reuse
one formula across all four):

```
Capture / Refund:     md5(memberId|secureKey|paymentId|amount)
Inquire / Cancel:     md5(memberId|secureKey|paymentId)
Get transaction list: md5(memberId|secureKey)
```

Sample `IN` request (`authentication.checksum` computed over
`memberId|secureKey|paymentId`, with `paymentType=IN`, `idType=MID`):

```json
{
  "authentication.memberId": "11344",
  "authentication.checksum": "c6e1268421b9b79fffe855b15f256538",
  "paymentType": "IN",
  "idType": "MID",
  "merchantTransactionId": "#081756894916609"
}
```

Sample `IN` response — note `status` (long form, `capturesuccess`) and
`transactionStatus` (short form, `Y`) both present, confirming the two-form
split documented in `statuses.md`:

```json
{
  "paymentId": "54289",
  "status": "capturesuccess",
  "transactionStatus": "Y",
  "amount": "1.00",
  "result": { "code": "00026", "description": "Your record found successfully" },
  ...
}
```

Sample `CP` response:

```json
{
  "paymentId": "54304",
  "result": { "code": "00004", "description": "Transaction captured successfully" },
  "timestamp": "2018-04-02 15:26:02"
}
```

## Payout

```
POST {host}/transactionServices/REST/v1/payout
```

Places a payout against a previously captured/settled transaction, referenced
by `paymentId` or by a supplied `customer` object. `paymentType=PO`. Auth
token required in the header.

Checksum — a *third* ordering, secure key last again but with a different
field set than Standard Checkout's request checksum:

```
md5(memberId|merchantTransactionId|amount|secureKey)
```

## Invoice API (not implemented by this driver)

The doc site also documents an Invoice workflow (`GENERATE`, `CANCEL`,
`REGENERATE`, `REMIND`, `INQUIRY`, ... under `invoice/REST/v1/*`, e.g.
`invoice/REST/v1/generate`). It is out of scope for the current driver — noted
here only because the sample-payload endpoints (`SOURCES.md`) key some
samples under `workflow: "invoice"`, which otherwise look unrelated to
Standard Checkout / REST payments.
