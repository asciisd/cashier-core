# Digiblox — Consolidated Integration Reference

Everything needed to build a Digiblox driver in `cashier-core`, distilled from the
four documents in `digiblox_docs/`. Where the sources disagree, the conflict is
recorded rather than silently resolved — see [Contradictions](#12-contradictions-between-the-source-documents)
and [Open questions](#13-open-questions--missing-endpoints).

**Sources**

| File | What it covers | Version / date |
|---|---|---|
| `Digiblox-PayWithCrypto-Integration-Guide-v1.1.pdf` | Deposits: auth, guest, payment link, deposit webhook, status polling | 1.0/1.1, "Crymbo Gateway v3" |
| `Digiblox-Centralized-Transfer-API-Guide.pdf` | Withdrawals: centralized transfer create + status | 2.0, 2026-08-24 |
| `Digiblox_Widget_Integration_Flow.pdf` | Older/lower-level deposit flow: wallet address, currencies, quotes | undated, earlier |
| `API integration guide.docx.md` | Support thread clarifying link-inspection vs. deposit-status | after 2026-08-13 |
| `Currencies and networks.csv` | Enabled asset/network pairs | — |

> Digiblox is a white-label of the **Crymbo** gateway. Crymbo naming leaks through
> in paths (`Crymbo-API` JWT issuer), hosts (`pay.crymbo.com`) and the public
> ReDoc reference (`crymbo.redoc.ly`).

---

## 1. Platform basics

| Item | Value |
|---|---|
| API host | `https://app.digiblox.io` |
| Deposit API base | `https://app.digiblox.io/gateway/api/v1/v3` |
| Widget host | `https://widget.digiblox.io` |
| Public API reference | `https://crymbo.redoc.ly` |
| Sandbox host | **not documented in the PDFs — see §14** |

### There is no fiat payment API — "fiat" here means settlement

Digiblox markets "FIAT deposits and withdrawals", which reads like a second
payment rail. It is not one. Confirmed against every reachable public source
(§14):

- **Crypto is the only customer-facing rail.** `payment_method` accepts exactly
  one value, `CRYPTO_DEPOSIT`. Withdrawals broadcast to a blockchain. Nothing in
  any document mentions cards, bank transfer, SEPA, SWIFT, ACH, IBAN, 3DS or an
  acquirer.
- **`fiat_currency` / `fiat_amount` only *denominate* the order.** "Charge the
  USDT equivalent of $150.00." The pricing engine converts fiat → crypto; the
  customer still pays on-chain. That is why sending both `fiat_amount` and
  `crypto_amount` is a 400 — they are two ways of expressing one crypto amount.
- **`initial_rate` / `fiat_rate` are bookkeeping**, explicitly "used for internal
  fiat-value reporting and not re-derived by the system."
- **The actual fiat feature is merchant treasury, not payments.** Every business
  has two settlement currencies, one crypto and one fiat. Fiat top-up is a module
  under **Billing in the Dashboard** — you wire money from a bank account matching
  your settlement currency. Fiat withdrawal settles your balance out to your bank
  in EUR, USD or GBP. Both are dashboard operations for your own funds; neither
  is a way for a customer to pay you, and no API endpoint for either is
  documented.

**Confirmed by both of our account's exports.** They are two different ledgers,
and the distinction is the whole answer:

- **§15 `transaction-history-2.csv` is the *company* ledger** — Digiblox's own
  treasury movements. Its `Deposit / Fiat` rows are *our* top-ups, money we wire
  in to fund the account. Not customer payments.
- **§16 `guest.crypto.deposits.csv` is the *customer* ledger** — what guests
  actually paid. **Every row is crypto.** There is no fiat column, no fiat
  instrument, no fiat status: only `Expected`/`Credited`/`Total` amounts in ETH
  and USDT, a sender address, a network and a chain hash. Fiat appears solely as
  a *valuation* of those crypto amounts.

Fiat funds the company balance; crypto moves customer money. A customer cannot
pay us in fiat.

**So the split is not fiat vs. crypto — it is deposits vs. withdrawals**
(§3–§5 and §6), and separately **two API generations** (below).

### Platform lineage — and the two API generations

Digiblox is a white-label. The lineage shows through in three names, which is
worth knowing because it decides which contract applies:

| Name | Where it surfaces |
|---|---|
| **Finrax** | `backoffice.digiblox.io` is titled "Finrax Backoffice"; `docs.finrax.com` |
| **Crymbo** | JWT issuer `Crymbo-API`; `crymbo.redoc.ly`; `pay.crymbo.com` in the older widget PDF |
| **Digiblox** | The merchant-facing brand: `app.digiblox.io`, `widget.digiblox.io` |

Finrax rebranded toward Crymbo, and **two API generations are in the wild**:

1. **Finrax-generation** — `docs.finrax.com`, signature-based auth, a documented
   sandbox, endpoints like `request-crypto-payment`, `fetch-business-balance`.
2. **Crymbo Gateway v3** — `app.digiblox.io/gateway/api/v1/v3/…`, JWT auth. **This
   is what our PDFs document and what we build against.**

The two are not interchangeable: different auth, different paths, different
payload shapes. Do not port a Finrax contract into this driver without
confirmation — but do use the Finrax docs as evidence that a capability *exists
on the platform* when asking Digiblox about a gap (§13, §14).

This may also be the real source of the "two APIs" question — it is two
generations, not two rails.

**Headers on every authenticated call**

```
Authorization: Bearer <jwt>
Accept: application/json
Content-Type: application/json
```

### Two path forms exist

- `/gateway/api/v1/v3/…` — **JWT-authenticated**. This is the merchant form and
  the one to implement.
- `/gateway/api/v3/…` — the same routes under *browser session* auth.

A valid JWT rejected with `401` on every call almost always means the wrong path
form. The Widget Integration Flow PDF uses the session form and `{version}`
placeholders throughout; treat its paths as illustrative only.

### IDs are opaque encrypted tokens

`merchant_id`, `user_id`, `currency_id`, transfer `id` and every `*_id` field are
encrypted strings (e.g. `bkE0RmNjbEhCUmc9`), **not integers**. Pass back exactly
what Digiblox gave you. A numeric `merchant_id` fails to decrypt and surfaces as
`merchant_id must be a string`.

They are also **not stable join keys**. Match only on `external_transaction_id`
and `tx_hash`.

The single exception: `external_id` / `external_transaction_id` is *your*
reference and travels as plain text.

---

## 2. Authentication (JWT)

```
POST https://app.digiblox.io/gateway/api/v1/auth/login/jwt
```

**Request**

```json
{
  "username": "merchant_alpha",
  "api_key": "<your_api_key>",
  "api_secret": "<your_api_secret>"
}
```

**Response — 200**

```json
{
  "iss": "Crymbo-API",
  "iat": 1785926400,
  "exp": 1785930000,
  "type": "Bearer",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

- TTL is **1 hour** (`exp = iat + 3600`). Cache the token and refresh on `exp`,
  not on failure.
- The Transfer guide adds: **only the most recently issued token for a merchant
  is valid** — minting a new one invalidates the previous. That makes naive
  per-request minting actively harmful across concurrent workers. Cache in a
  shared store (Redis), keyed per connection, with a single-flight refresh.

**Errors**

| HTTP | Body | Meaning |
|---|---|---|
| 401 | `Invalid Credentials` (plain string) | Any of username / api_key / api_secret wrong. Deliberately identical for all three. |
| 401 | `Unauthorized` | Missing header, or token superseded by a newer one. |
| 401 | `Invalid access token` | Malformed, expired, or bad signature → re-authenticate. |
| 403 | `{"code":"ACCOUNT_BLOCKED","message":"Your account has been blocked."}` | Stop. Retrying will not help. |
| 500 | `Failed encoding JWT token` | Platform-side. Retry with backoff. |

> **On 401 and 500 this endpoint returns a plain string, not JSON.** Do not
> blindly `json_decode()` a non-2xx login response.

---

## 3. Deposit flow

Two variants. Both end with a `paymentLink` handed to the customer; everything
after that (currency choice, address, quote, QR, chain monitoring) happens inside
the Digiblox widget.

**Flow A — anonymous guest (2 calls):** authenticate → create payment link. The
widget collects and verifies the customer's details itself.

**Flow B — known guest (3–4 calls):** authenticate → check guest exists → create
guest with PII *(only if `exists: false`)* → create payment link with `username`.
The link then carries a pre-issued guest token and skips the widget's
identification step.

Flow A skips the guest lookup entirely — there is no guest to look up and none to
create.

### 3.1 Check guest exists (Flow B only)

```
POST /gateway/api/v1/v3/auth/check-guest-exists
{ "username": "user@example.com" }
```

**200 in both cases** — there is deliberately no 404:

```json
{ "exists": false, "id": null }
{ "exists": true,  "id": "V0FrTk5FR3NUeE9wL2lqcE9Rc2h2Zz09" }
```

`id` is the guest's permanent `user_id`. Worth storing against the customer
record; no call in the payment-link flow requires it (the older wallet-address
flow does — see §8).

> `exists: true` is returned **only for users whose type is `guest`**. A full
> (non-guest) Digiblox account with that email also returns `exists: false`, and
> registering it as a guest will be rejected later. A surprising `false` for a
> known customer means "check the account type", not "create a duplicate".

Errors: `400 username is required`, `401 Unauthorized`, `403 ACCOUNT_BLOCKED`.

### 3.2 Create guest with PII (Flow B only)

```
POST /gateway/api/v1/v3/auth/create-guest-with-pii
```

```json
{
  "email": "user@example.com",
  "pii": {
    "firstName": "John",
    "lastName": "Doe",
    "dob": "1990-01-01",
    "phone": "+15551234567",
    "address": "123 Main Street",
    "city": "New York",
    "country": "USA",
    "zipCode": "10001",
    "state": "NY"
  }
}
```

| Field | Required | Notes |
|---|---|---|
| `email` | yes | Valid email; becomes the guest's username. Sits **beside** `pii`, not inside it. |
| `pii.firstName` / `lastName` | yes | Non-empty. |
| `pii.dob` | yes | `YYYY-MM-DD`. Age validated against platform min/max (error text cites 18–90). |
| `pii.phone` | yes | E.164, validated as really dialable. |
| `pii.address`, `city`, `zipCode` | yes | — |
| `pii.country` | yes | **ISO 3166-1 alpha-3** (`USA`, `ISR`, `GBR`) — not alpha-2. |
| `pii.state` | optional | — |
| `pii.nationality` | optional | alpha-3. |
| `pii.idDocType` | paired | `PASSPORT` \| `NATIONAL_ID` \| `DRIVERS_LICENCE` \| `WORK_PERMIT`. |
| `pii.idDocNumber` | paired | Send both ID fields or neither. |

**200** → `{ "message": "user create/updated successfully" }`

- **Idempotent-ish:** re-sending for an existing guest updates the PII record. No
  "already exists" failure to code around.
- It does **not** return `user_id` — call §3.1 afterwards if you need it.
- **Error bodies have two shapes:** a single-rule failure returns `message` as a
  *string*; field validation returns `message` as an *array of strings*
  (`["firstName string is required", …]`). Handle both; surface all entries.

### 3.3 Create the payment link — the call that matters

```
POST /gateway/api/v1/v3/payments/guests
```

**Flow A**

```json
{
  "payload": {
    "external_id": "your-unique-transaction-id",
    "merchant_id": "bkE0RmNjbEhCUmc9",
    "fiat_currency": "USD",
    "fiat_amount": "150.00",
    "crypto_currency": "USDT",
    "network": "ETHEREUM",
    "payment_method": "CRYPTO_DEPOSIT"
  },
  "notification": {
    "success_url": "https://app.digiblox.io/success",
    "fail_url": "https://app.digiblox.io/fail"
  }
}
```

**Flow B** — identical plus `"username": "user@example.com"` inside `payload`.

| Field | Required | Notes |
|---|---|---|
| `external_id` | yes | Your reference. **Must be unique** — the key that ties the webhook to the order. Plain text. |
| `merchant_id` | yes | The encrypted merchant identifier, verbatim. |
| `payment_method` | yes | Exactly `CRYPTO_DEPOSIT` — the only accepted value. |
| `username` | Flow B | Registered guest email. Omit for Flow A. |
| `fiat_currency` | paired | ISO code. Required *with* `fiat_amount` and only with it. |
| `fiat_amount` | either/or | String, **exactly 2 decimals**, positive. `"150.00"` — `"150"` and `"150.5"` are rejected. |
| `crypto_currency` | optional | `USDT`, `BTC`, `ETH`. Required if you send `crypto_amount` or `network`. |
| `crypto_amount` | either/or | Positive number string. Fixes the crypto amount and skips pricing. |
| `network` | optional | `ETHEREUM`, `BITCOIN`, `TRON`, `POLYGON`… Uppercase enum. **Strongly recommended** — with `crypto_currency` + `network` the widget opens straight on the QR code. |
| `notification.success_url` | optional | Redirect after confirmed payment. |
| `notification.fail_url` | optional | Redirect after failure/cancellation. |

> The `notification` object is **required even when empty** — send
> `"notification": {}` rather than omitting it. Both URLs must be absolute.

**Amount rules**

| You send | Result |
|---|---|
| `fiat_currency` + `fiat_amount` + `crypto_currency` + `network` | **Recommended.** Pricing engine converts; widget opens on QR. |
| `crypto_currency` + `crypto_amount` + `network` | No pricing step. Straight to Wallet Connect / QR. |
| `crypto_currency` + `crypto_amount`, no `network` | Widget prompts the customer to pick a network. |
| Both `fiat_amount` and `crypto_amount` | `400` — mixing unsupported. |
| Neither amount | `400` — one is mandatory. |

**Response — `201 Created`, not 200**

```json
{ "paymentLink": "https://widget.digiblox.io/widget/auth?paymentId=R1N0bjVCN0Y2alk9" }
```

Flow B returns the token form:

```json
{ "paymentLink": "https://widget.digiblox.io/widget/token?paymentId=R1N0bjVCN0Y2alk9&token=eyJhbGciOi..." }
```

> A strict `=== 200` check treats a perfectly good payment link as a failure.

#### Errors — validation runs in three passes; you see the first that fails

**Pass 1 — shape and types (400, `message` is an array)**

| Message | Trigger |
|---|---|
| `external_id must be a string` | Sent as a number, or missing. |
| `merchant_id must be a string` | Usually a raw numeric merchant id — use the encrypted token. |
| `payment_method must be one of the following values: CRYPTO_DEPOSIT` | Typo/case/missing. |
| `username must be an email` | Malformed email in Flow B. |
| `fiat_amount must be a positive number string` | Zero, negative, unparseable. |
| `fiat_amount must have 2 decimals` | `"150"` / `"150.5"`. |
| `crypto_amount must be a positive number string` | Zero, negative, unparseable. |
| `success_url must be a URL address` | Not absolute. Same for `fail_url`. |

**Pass 2 — payload consistency (400, `message` is a string)**

`Invalid body - payload property is missing or invalid` ·
`Invalid body - merchant_id is missing in payload` ·
`Invalid body - payload property missing external_id` ·
`Invalid body - notification property is missing or invalid` ·
`payload must contain crypto_currency when network is specified` ·
`payload can't contain fiat_currency when crypto_amount is specified` ·
`payload must contain fiat_amount when fiat_currency is specified` ·
`payload must contain fiat_currency when fiat_amount is specified` ·
`payload [crypto_amount, fiat_amount] mixing is not supported` ·
`payload [crypto_amount OR fiat_amount] should be provided`

**Pass 3 — business validation (400, `message` is a string)**

| Message | Meaning & fix |
|---|---|
| `external_id: <id> is already used` | The link already exists. **Do not retry with the same id** — look the existing link up, or mint a fresh `external_id`. Checked *before* the merchant check, so it fires even when other fields are also wrong. |
| `merchant <id> is not found` | `merchant_id` doesn't resolve — usually truncation or a cross-environment value. |
| `invalid merchant_id` | The id resolves to a *guest* account. You passed the customer's id in the merchant field. |
| `user <username> is not found` | Flow B with an unregistered email → run §3.1, §3.2, retry. |
| `Can only generate token for guest user` | **403.** The email is a full account, not a guest. |
| `invalid <network> network` | Unknown network. Uppercase enums (`ETHEREUM`, not `ethereum`). |
| `invalid <currency> crypto_currency for <network> network` | Asset exists but isn't enabled on that network for your account. |
| `invalid <currency> crypto_currency` | Unknown symbol, or a fiat code in the crypto field. |
| `invalid <currency> fiat_currency` | Unknown fiat code, or a crypto symbol in the fiat field. |
| `500 INTERNAL_SERVER_ERROR` | Retry **with the same `external_id`** — if the link was in fact created you get `external_id already used`, which tells you to look it up rather than duplicate. |

### 3.4 Inspect a payment link (not a status check)

```
GET /gateway/api/v1/v3/payments/guests/{id}     → 200
```

Returns the **link definition as submitted** — network, fiat amount and currency,
merchant, guest, redirect URLs. Written once at creation and never updated.

**It has no `status` field and never will.** Use it only to verify a link was
created correctly. For "did the customer pay?", use §5.

---

## 4. Deposit webhook (authoritative)

### Setup

The callback URL is registered against the merchant account by the Digiblox team
(`DEPOSIT_NOTIFICATION_URL`). You send them the HTTPS URL **and any static
headers you need for authentication** — those headers are replayed on every
delivery.

```
POST https://your-server.com/webhooks/digiblox?external_transaction_id=your-unique-transaction-id
```

`external_transaction_id` is appended as a **query parameter as well as** being in
the body, so the request can be routed before parsing.

### Payload

```json
{
  "tx_hash": "<redacted-tx-hash>",
  "from_address": "0x742d35Cc6634C0532925a3b844Bc454e4438f44e",
  "to_address": "0x0e8091C125FFc084cf4546218b1fB3700F4C6AE0",
  "currency": "USDT",
  "network": "ETHEREUM",
  "amount": "149.700000",
  "confirmations": 12,
  "confirmed": true,
  "block": 21458399,
  "mined_at": "2026-08-05 09:14:22",
  "external_transaction_id": "your-unique-transaction-id",
  "expected_amount": "150.000000",
  "total_amount": "150.000000",
  "status": "COMPLETED"
}
```

| Field | Meaning |
|---|---|
| `external_transaction_id` | Your `external_id`. How you find the order. |
| `status` | `COMPLETED` \| `PARTIALLY_PAID` \| `OVERPAID`. |
| `expected_amount` | What the widget asked for, **in crypto units**. |
| `total_amount` | What actually arrived, **gross** (credited + platform fee). **Reconcile on this.** |
| `amount` | Credited to the account = `total_amount` − platform fee. For balance display only. |
| `tx_hash` | On-chain hash. **Your idempotency key.** |
| `from_address` / `to_address` | Payer's wallet / the deposit address issued. |
| `currency` / `network` | What actually arrived — compare against what you requested. |
| `confirmations` / `confirmed` / `block` / `mined_at` | Chain confirmation detail. |

> **`amount` ≠ `total_amount`.** Reconciling against `amount` makes every correct
> payment look short by exactly the fee.

### How the status is decided

```
band = expected_amount × (tolerance % / 100)

total_amount > expected_amount + band  → OVERPAID
total_amount < expected_amount − band  → PARTIALLY_PAID
otherwise                              → COMPLETED
```

The tolerance band is a **platform setting defaulting to 0%** — strict equality.
A single unit of dust below the expected amount is reported `PARTIALLY_PAID`.
Some wallets deduct the network fee from the send amount, so a customer who
"sends 150" delivers 149.9994 → `PARTIALLY_PAID`. **Ask Digiblox to configure a
small tolerance.**

### The three statuses

All three mean money arrived. None of them means "nothing happened".

| Status | Received | Fulfil? | Follow-up |
|---|---|---|---|
| `COMPLETED` | In full | Yes | None. |
| `PARTIALLY_PAID` | Short | **No — hold** | Check currency/network first, then request the balance or refund. The funds are real and already credited. |
| `OVERPAID` | Excess | Yes | Refund, credit, or flag `total_amount − expected_amount`. Full amount already credited. |

Three different situations produce `PARTIALLY_PAID`:

1. **Genuine underpayment** — `total_amount < expected_amount`. The ordinary case.
2. **Wrong currency or network** — the link pinned an asset and something else
   arrived. `currency`/`network` won't match the request and the amounts aren't
   comparable. Manual review.
3. **No expected amount on record** — a deposit reached the address outside the
   widget flow; `expected_amount` is `null` or `0`. Digiblox deliberately never
   claims `COMPLETED` when it cannot verify the amount.

### Delivery contract

| Property | Value |
|---|---|
| Method | `POST`, JSON body |
| Success | **Any 2xx.** Anything else is a failure. |
| Connect timeout | 5 s |
| Total timeout | **10 s** |
| Retries | Platform-configured max, **default 3 attempts total** |
| Auth | Only the static headers you registered, replayed verbatim |

**Acknowledge fast, process later.** Attempts are finite; slow work inside the
request burns a retry. Persist the payload, return 200, process asynchronously.

**Reference handler**

```
1. Verify the registered auth header      → mismatch → 401, stop
2. Read tx_hash + external_transaction_id → already processed? → 200, stop
3. Find the order by external_transaction_id → not found → 200 + alert
4. Persist the raw payload, keyed on tx_hash
5. RETURN 200                             ← before any business logic
6. Asynchronously:
     COMPLETED      → mark paid, fulfil
     OVERPAID       → mark paid, fulfil, queue refund of the excess
     PARTIALLY_PAID → hold, verify currency/network, notify customer
```

> **Return 200 even for an order you don't recognise.** A non-2xx burns one of a
> small number of retries and the delivery is then abandoned. Store, alert,
> answer 200 — a 404 loses the data.

### Idempotency

The same webhook can arrive more than once (retry after timeout, manual re-send).
A customer can also make **several on-chain payments against one link**, each
producing its own webhook with the **same `external_transaction_id` but a
different `tx_hash`**.

**Key on `tx_hash`, never on `external_transaction_id`.** Keying on the order id
makes a second genuine payment look like a duplicate and silently drops it.

### Pre-launch checklist

- [ ] Endpoint is HTTPS, publicly reachable, not behind IP allow-listing that excludes Digiblox
- [ ] Returns 200 in well under 10 s, before business logic
- [ ] Deduplicates on `tx_hash`
- [ ] Handles all three statuses explicitly — unknown status → review queue, never "success"
- [ ] Reconciles on `total_amount`, not `amount`
- [ ] Compares `currency` and `network` against what was requested
- [ ] Fulfilment is driven by the webhook, **never** by `success_url`
- [ ] Unmatched `external_transaction_id` is stored, alerted, and still answered 200

> **`success_url` is not proof of payment.** It fires from the customer's browser
> and can be skipped, replayed, or reached by someone who closed the tab before
> sending anything. Use it for UI only.

---

## 5. Deposit status polling (reconciliation)

For orders with no webhook yet, webhooks dropped during a deploy, or end-of-day
reconciliation. Answers "what happened to this payment?" at any point in its
life, including before the customer has sent anything.

```
GET /gateway/api/v1/v3/deposits/merchant
      ?limit=25&offset=0&sB=created_at&sD=desc
      &fB=external_transaction_id&fV=<your-external-id>&fO=EQ&fT=S
```

Same `Authorization: Bearer <jwt>`. No additional credentials.

| Param | Value | Meaning |
|---|---|---|
| `limit` | `25` | Rows per page |
| `offset` | `0` | Pagination cursor |
| `sB` | `created_at` | Sort By |
| `sD` | `desc` | Sort Direction |
| `fB` | `external_transaction_id` | Filter By |
| `fV` | your `external_id` | **The only parameter you change** |
| `fO` | `EQ` | Filter Operator — `EQ` equals, `C` contains |
| `fT` | `S` | Filter Type — string |

Treat everything but `fV` as a constant in the client.

> ⚠️ The support thread (`API integration guide.docx.md`) gives the path as
> `/v3/deposits` with `fO=C`. The v1.1 PDF gives `/v3/deposits/merchant` with
> `fO=EQ`. See [Contradictions](#12-contradictions-between-the-source-documents).

### Status values

| Status | Meaning | Action |
|---|---|---|
| `SENT_DEPOSIT_ADDRESS` | Address issued; no funds yet | Keep polling |
| `SENT_TOKEN` | Same stage — awaiting funds on-chain | Keep polling |
| `PENDING` | Pre-on-chain intermediate state | Keep polling |
| `PUBLISHED` | Funds seen on-chain, awaiting confirmations | Keep polling |
| `CONFIRMED` | Settled and credited — **final success** | Release the order |
| `REJECTED` / `REJECTED_BY_ADMIN` | Declined — **final failure** | Fail the order |

> **Implement as a three-way branch, not a string match.** `CONFIRMED` → success;
> `REJECTED`/`REJECTED_BY_ADMIN` → failure; **everything else → still pending**,
> so any status added later lands safely in "pending".

> **The webhook says `COMPLETED`; this endpoint says `CONFIRMED`.** They are
> different axes: the webhook status is a *reconciliation verdict about the
> amount*; this status is the *lifecycle state of the deposit*. A
> `PARTIALLY_PAID` webhook and a `CONFIRMED` deposit are both possible for the
> same payment. **Never compare the two strings.**

### Responses

**Nothing yet** — HTTP 200, not an error. The correct "not yet" signal.

```json
{ "totalItems": 0, "result": [] }
```

**Awaiting payment** — `amount` is `"0"`, every on-chain field `null`.
`to_address` is the address issued to the customer.

```json
{
  "totalItems": 1,
  "result": [{
    "external_transaction_id": "ORDER-88213",
    "status": "SENT_DEPOSIT_ADDRESS",
    "amount": "0",
    "system_fee": "0",
    "currency": "USDT",
    "expected_amount": "150.00",
    "expected_currency": "USD",
    "to_address": "0xA3C7Bbf329adB6814e0f455A17e777b6EbFe5482",
    "from_address": null,
    "tx_hash": null,
    "block": null,
    "mined_at": null,
    "created_at": "2026-08-05T09:12:10.000Z",
    "Currency": { "decimals": 18, "network": "ETHEREUM" }
  }]
}
```

**Success** — the only status to fulfil on. Gross = `amount` + `system_fee`.

```json
{
  "totalItems": 1,
  "result": [{
    "external_transaction_id": "ORDER-88213",
    "status": "CONFIRMED",
    "amount": "149.700000",
    "system_fee": "0.300000",
    "currency": "USDT",
    "expected_amount": "150.00",
    "expected_currency": "USD",
    "tx_hash": "0x9c2f4b81e0a7d3c5f6b2a9184e7d0c3b5a8f1e6d4c2b7a90",
    "block": 21458399,
    "mined_at": "2026-08-05T09:14:22.000Z",
    "from_address": "0x742d35Cc6634C0532925a3b844Bc454e4438f44e",
    "to_address": "0x0e8091C125FFc084cf4546218b1fB3700F4C6AE0",
    "created_at": "2026-08-05T09:12:10.000Z",
    "updated_at": "2026-08-05T09:16:04.000Z",
    "Currency": { "decimals": 18, "network": "ETHEREUM" },
    "User": { "username": "customer@example.com" },
    "Transaction": { "rate_without_fee": "0.99896342", "receive_currency": "USD" }
  }]
}
```

**Rejected** — note `tx_hash`, `block` and `mined_at` are populated:

```json
{
  "totalItems": 1,
  "result": [{
    "external_transaction_id": "ORDER-88216",
    "status": "REJECTED",
    "amount": "0.51740000",
    "system_fee": "0.00260000",
    "currency": "LTC",
    "tx_hash": "d4e21965ab9bef8286c46f57805337096304fa25809dc3fa1a",
    "block": 3159169,
    "mined_at": "2026-08-13T06:59:51.000Z",
    "Currency": { "decimals": 8, "network": "LITECOIN" }
  }]
}
```

> **A rejection can occur *after* the funds arrive**, following compliance
> screening. A populated `tx_hash` does not mean success. Always read `status`,
> and contact Digiblox for the disposition of the funds.

**Multiple rows** — a customer may pay more than once against one link, and
Digiblox also records an internal settlement row:

```json
{
  "totalItems": 2,
  "result": [
    { "external_transaction_id": "ORDER-88213", "status": "CONFIRMED", "amount": "149.700000", "tx_hash": "0x9c2f…7a90" },
    { "external_transaction_id": "ORDER-88213", "status": "CONFIRMED", "amount": "24.950000",  "tx_hash": "0x91cd…7a20" }
  ]
}
```

**Always iterate `result` — never assume `result[0]`.**

### Field notes

| Field | What to know |
|---|---|
| `status` | The lifecycle state. The field you branch on. |
| `amount` | Net of platform fee, already decimal-converted. Gross = `amount + system_fee`. |
| `system_fee` | Platform fee, already decimal-converted. |
| `expected_amount` / `expected_currency` | From the payment link. When priced in fiat this is the **fiat** amount (`"150.00"`, `"USD"`) — **not** the crypto `expected_amount` from the webhook. Do not compare the two. |
| `Currency.decimals` | Internal accounting scale, reference only — `amount` and `system_fee` are already converted. |
| `id`, `user_id`, any `*_id` | Encrypted. Not join keys. Match on `external_transaction_id` and `tx_hash`. |

### Polling guidance

| Situation | Recommendation |
|---|---|
| Normal operation | **Do not poll.** The webhook arrives within seconds of settlement. |
| Order with no webhook | Every 60 s for the first 30 min, then back off to every 15 min. |
| Daily reconciliation | One pass over unresolved orders; page with `limit`/`offset`. |
| After a deploy or outage | Replay unresolved orders through this endpoint rather than asking for webhook re-sends. |

---

## 6. Withdrawals — Centralized Transfer

Poll-based. No webhook is documented for withdrawals.

**Lifecycle:** merchant authenticates and posts the withdrawal → system creates a
transfer and returns an internal id immediately → policy, balance and (where
enabled) Travel Rule checks run → the transaction is approved, signed and
broadcast → the merchant polls until a terminal state. End-to-end completion can
take **several minutes**.

### 6.1 Create

```
POST https://app.digiblox.io/gateway/api/v1/v3/transfers/centralized
```

```json
{
  "amount": "25",
  "toAddress": "0x91bF3A2cE67D5F12B4C98aE45F8dA12C3eF98765",
  "network": "ETHEREUM",
  "asset": "USDC",
  "initial_rate": "1.00",
  "initial_rate_currency_id": "bkE0RmNjbEhCUmc9"
}
```

| Field | Required | Notes |
|---|---|---|
| `amount` | yes | Human-readable units. **Validate it is a well-formed positive number yourself — the API does not reject malformed strings.** |
| `toAddress` | yes | **Validate format/checksum client-side — the API does not.** |
| `network` | yes | Must match a network enabled on your account. |
| `asset` | yes | Must match a supported symbol. |
| `initial_rate` | no* | Reference fiat rate for reporting. **Always send `"1.00"`** unless told otherwise. |
| `initial_rate_currency_id` | no* | **Always send `"bkE0RmNjbEhCUmc9"`.** If supplied it must resolve to a real currency. |
| `user_note` | no | Free text, max 255 chars. |

\* Technically optional; the guide says always send the fixed values for
consistent reporting. They are used for internal fiat-value reporting and are
**not re-derived** by the system — do not compute or substitute your own rate.

**Response — `202 Accepted`**

```json
{ "id": "Qk1ZbFZkN2R3Z1E9", "status": "QUEUED" }
```

The `id` is an opaque encrypted token — use it as-is with the status endpoint.

> **No idempotency key.** This endpoint does not support `Idempotency-Key`. If a
> request times out or the response is inconclusive, **poll the status before
> retrying**. A second POST while the first is still `QUEUED` is safely rejected
> — but once the first advances past `QUEUED`, a repeated POST creates an
> **independent second withdrawal**.

**Errors**

| HTTP | Response | Cause |
|---|---|---|
| 400 | Field validation error | Missing/mistyped `amount`, `toAddress`, `network`, `asset`. |
| 400 | `Asset not found` | Unsupported asset symbol. |
| 400 | `network not found` | Unsupported network. |
| 400 | `network or asset not found ` | Combined lookup failed. **Note the trailing space — match as returned.** |
| 400 | `invalid receive_currency_id` | `initial_rate_currency_id` doesn't resolve. (The message names a *different* field.) |
| 400 | `Insufficent funds` | Exceeds available balance. **Misspelled in the live response.** |
| 400 | `Only {amount} {asset} is available for withdrawal. More funds unlock at {time}.` | Time-gated limit — only aged/unlocked funds are withdrawable. |
| 400 | `For your security, each withdrawal requires a unique destination address.` | Address reuse disabled for your account. |
| 400 | `This address was used recently. Available again in ~{n} hour(s).` | Reuse cooldown not yet elapsed. |
| 400 | `Amount is below the minimum fee. Minimum fee: {fee} {asset}.` | Amount doesn't cover the platform minimum fee. |
| 400 | `A withdrawal is already being processed for this currency. Please wait for it to complete.` | A previous withdrawal in the same asset is still `QUEUED`. **Serializes withdrawals per asset.** |
| 401 | `Unauthorized` / `Invalid access token` | Missing/invalid JWT. |
| 403 | `ACCOUNT_BLOCKED` | Account blocked. |
| 500 | Internal Server Error | The transfer may or may not have been created — **check status before retrying**. |

### 6.2 Check transfer status

```
GET https://app.digiblox.io/gateway/api/v1/transfers/{transfer_id}
```

Note: **no `/v3` segment** here, unlike the create call.

```json
{
  "api_message": "TRANSFER_GET_SHOW_SUCCESS",
  "api_data": {
    "transfer": {
      "username": "merchant_alpha@example.com",
      "id": "REZSQmtOSG9rYVU9",
      "status": "BLOCKCHAIN_PENDING",
      "amount": "15.000000000000000000",
      "system_fee": 0.2,
      "currency": "USDT",
      "recipient_address": "0x7FaB1234CdEf567890aBCdEf1234567890aBcDeF",
      "tx_hash": null,
      "currency_decimals": 18
    }
  }
}
```

| Field | Notes |
|---|---|
| `username` | Account associated with the transfer. |
| `id` | Opaque encrypted id, matches creation. |
| `status` | See lifecycle below. |
| `amount` / `system_fee` | Human-readable decimals, **already scaled**. |
| `currency` / `currency_id` | Symbol / opaque id. |
| `currency_decimals` | Decimal precision. *(The original draft called this `decimals`; the live API returns `currency_decimals`.)* |
| `recipient_address` | Destination. |
| `from_address` / `from_wallet_id` | Source wallet (nullable). |
| `tx_hash` | Populated once broadcast. |
| `initial_rate` / `initial_rate_currency` / `initial_rate_currency_id` | As supplied at creation. |
| `fiat_rate` / `fiat_currency_id` | Additional fiat valuation. |
| `travel_rule_status` / `travel_rule_txn_id` | Travel Rule screening result, when enabled. |
| `error_log` | Structured failure detail on terminal failure, else `null`. |
| `reason` / `user_note` | Withdrawal reason and merchant note. |
| `external_id` / `provider_id` / `ref_account_id` / `reviewed_by_id` / `created_by_id` / `user_id` | Opaque encrypted cross-references. |
| `ip` | Origin IP recorded at creation. |
| `created_at` / `updated_at` | Timestamps. |

**Lifecycle**

| Status | Category | Meaning |
|---|---|---|
| `QUEUED` | in progress | Created; waiting to enter internal processing. |
| `PENDING` | in progress | Policy/KYT checks, or awaiting manual admin approval above your auto-approve threshold. |
| `ADMIN_APPROVED` | in progress | Approved; queued for signing. |
| `SIGNATURE_PENDING` | in progress | Signing / provider-side approval. |
| `BLOCKCHAIN_PENDING` | in progress | Broadcast; awaiting mining/confirmation. |
| `FINALIZE` | in progress | Confirmed on-chain, finalizing internal bookkeeping. **Not yet terminal — keep polling.** |
| `CONFIRMED` | **terminal success** | Key "withdrawal succeeded" off this. |
| `FAILED` | terminal failure | Policy rejection or blockchain-level failure. |
| `REJECTED` | terminal failure | Canceled by the initiator or an admin. |
| `DROPPED` | terminal failure | Broadcast dropped from the network, not replaced. |
| `EXPIRED` | terminal failure | Expired without completion. |

> **Defensive polling.** `status` is **not a strict enum**. `CONFIRMED` →
> terminal success; `FAILED`/`REJECTED`/`DROPPED`/`EXPIRED` → terminal failure;
> **any other value, including unlisted ones, → still in progress.**

Errors: `401 Unauthorized`/`Invalid access token`, `404 Not Found` (id doesn't
exist or isn't visible to the caller), `500`.

---

## 7. Supported currencies and networks

From `Currencies and networks.csv` (whitespace in the source trimmed here):

| Currency | Network |
|---|---|
| ETH | ETHEREUM |
| USDC | ETHEREUM |
| USDC | BINANCE_SMART_CHAIN |
| USDT | ETHEREUM |
| USDT | TRON |
| USDT | BINANCE_SMART_CHAIN |
| BTC | BITCOIN |
| LTC | LITECOIN |
| LINK | ETHEREUM |

Network names are uppercase enums. Other values appear in the prose (`POLYGON`)
but are not in this list — enablement is per-account.

---

## 8. Legacy / lower-level widget flow

`Digiblox_Widget_Integration_Flow.pdf` documents a more granular flow where the
merchant generates the wallet address and fetches the quote itself instead of
handing off to the widget. **The PayWithCrypto v1.1 guide supersedes it** — under
v1.1 everything after the payment link happens inside the widget. Recorded here
because these endpoints may still be needed for a custom (non-widget) UI.

**Generate wallet address — two calls**

```
POST /gateway/api/{version}/Deposits/{currency}/{network}/address/guest
     ?user_id=<from check-guest-exists>&external_transaction_id=<external_id>
→ { "api_message": "DEPOSIT_POST_SUCCESS", "api_data": { "token": "R1N0bjVCN0Y2alk9" } }

GET  /gateway/api/{version}/Deposits/{currency}/address/guest
     ?token=<from above>&external_transaction_id=<same external_id>
→ { "address": "0xABC123...", "network": "ETHEREUM", "currency": "USDT" }
```

**Get currencies** (needed for the quote's currency ids)

```
GET /gateway/api/{version}/currencies
→ [ { "id": "currency_uuid_here", "symbol": "USDT", "network": "ETHEREUM" }, ... ]
```

**Price quote**

```
POST /gateway/api/{version}/orders/quotes
[
  {
    "side": "SELL",
    "spend_amount": "1",
    "spend_currency": "ETH",
    "spend_currency_id": "Z1ZaeEhJNUlNRH...",
    "receive_currency": "BTC",
    "receive_currency_id": "eGFCV203K3VS..."
  }
]
```

ReDoc references: `https://crymbo.redoc.ly/tag/Deposit`,
`https://crymbo.redoc.ly/tag/Currencies#operation/Get-currencies`,
`https://crymbo.redoc.ly/tag/Orders#paths/~1orders~1quotes/post`.

> This document also shows the payment-link notification object keyed **`system`**
> rather than `notification`, `fiat_amount` as a **number**, and the link host as
> `https://pay.crymbo.com/w/tx/...`. All three disagree with v1.1 — see §12.

---

## 9. Endpoint quick reference

| Method | Path | Success | Purpose |
|---|---|---|---|
| POST | `/gateway/api/v1/auth/login/jwt` | 200 | Bearer token (1 h) |
| POST | `/gateway/api/v1/v3/auth/check-guest-exists` | 200 | Look up guest, get `user_id` |
| POST | `/gateway/api/v1/v3/auth/create-guest-with-pii` | 200 | Pre-register guest (Flow B) |
| POST | `/gateway/api/v1/v3/payments/guests` | **201** | Create the payment link |
| GET | `/gateway/api/v1/v3/payments/guests/{id}` | 200 | Inspect a link definition (no status) |
| GET | `/gateway/api/v1/v3/deposits/merchant` | 200 | Deposit status / reconciliation |
| POST | `/gateway/api/v1/v3/transfers/centralized` | **202** | Create a withdrawal |
| GET | `/gateway/api/v1/transfers/{transfer_id}` | 200 | Withdrawal status |
| POST | *(your URL)* | 2xx | Deposit webhook, inbound |

---

## 10. Mapping onto the cashier-core driver

Following the existing `src/Drivers/{Heropayment,Xoala}` layout:

```
src/Drivers/Digiblox/
    DigibloxClient.php     — HTTP surface: JWT cache/refresh, link create,
                             guest check/create, deposit search, transfer create/status
    DigibloxAdapter.php    — payload assembly, status mapping, amount normalisation
    DigibloxProvider.php   — PaymentProcessorInterface implementation
src/Http/Controllers/Webhooks/DigibloxWebhookController.php
```

**`PaymentProcessorInterface` coverage**

| Method | Digiblox support |
|---|---|
| `charge()` | `POST /v3/payments/guests` → return the `paymentLink` as a redirect. No server-to-server settlement leg. |
| `retrieve()` / `getPaymentStatus()` | `GET /v3/deposits/merchant?fV={external_id}` — iterate `result`. |
| `parseWebhook()` | Deposit webhook; map `COMPLETED`/`PARTIALLY_PAID`/`OVERPAID`. |
| `verifyWebhookSignature()` | **No signature exists.** Only replayed static headers — see §13. |
| `refund()` | **No endpoint.** Must throw. |
| `capture()` / `authorize()` / `void()` | Not applicable to crypto deposits. Must throw. |
| Withdrawals | `WithdrawalWorkflow` over `POST /v3/transfers/centralized` + status polling. |

**Suggested connection config keys**

```php
'digiblox' => [
    'driver'       => 'digiblox',
    'host'         => env('DIGIBLOX_HOST', 'https://app.digiblox.io'),
    'username'     => env('DIGIBLOX_USERNAME'),
    'api_key'      => env('DIGIBLOX_API_KEY'),
    'api_secret'   => env('DIGIBLOX_API_SECRET'),
    'merchant_id'  => env('DIGIBLOX_MERCHANT_ID'),   // encrypted token, not an integer
    'success_url'  => env('DIGIBLOX_SUCCESS_URL'),
    'fail_url'     => env('DIGIBLOX_FAIL_URL'),
    'webhook_header_name'  => env('DIGIBLOX_WEBHOOK_HEADER_NAME'),
    'webhook_header_value' => env('DIGIBLOX_WEBHOOK_HEADER_VALUE'),
],
```

**Status mapping**

| Digiblox (deposit lifecycle) | Internal |
|---|---|
| `CONFIRMED` | success |
| `REJECTED`, `REJECTED_BY_ADMIN` | failed |
| everything else (incl. unknown) | pending |

| Digiblox (webhook reconciliation) | Internal |
|---|---|
| `COMPLETED` | success |
| `OVERPAID` | success + excess to review/refund queue |
| `PARTIALLY_PAID` | held / manual review — **not** failed |
| unknown | review queue — **never** success |

| Digiblox (transfer) | Internal |
|---|---|
| `CONFIRMED` | success |
| `FAILED`, `REJECTED`, `DROPPED`, `EXPIRED` | failed |
| everything else (incl. unknown) | pending |

---

## 11. Implementation traps — the short list

1. **`201`, not `200`** on payment-link creation; **`202`** on transfer creation.
2. **`merchant_id` is an encrypted string.** A numeric one fails with a generic
   type error.
3. **`fiat_amount` must be a string with exactly 2 decimals.** `"150"` fails.
4. **`notification: {}` is required even when empty.**
5. **Reconcile on `total_amount`, not `amount`** — `amount` is net of the fee.
6. **Deduplicate on `tx_hash`, not `external_transaction_id`** — one link can
   legitimately produce several payments.
7. **Always iterate `result`** in the deposit search; never `result[0]`.
8. **Webhook `COMPLETED` ≠ deposit `CONFIRMED`** — different axes, never compare
   the strings.
9. **`expected_amount` means different things** in the two surfaces: crypto units
   in the webhook, the fiat link amount in the deposit search.
10. **A populated `tx_hash` is not success** — compliance rejection happens after
    funds land.
11. **Return 200 to unrecognised webhooks**, or you burn one of three retries and
    lose the delivery.
12. **Default tolerance is 0%** — wallet-deducted network fees produce spurious
    `PARTIALLY_PAID`. Request a tolerance band.
13. **Never retry a payment link with the same `external_id`** after a 500 — poll
    for `external_id is already used` instead.
14. **Withdrawals have no idempotency key** and are serialized per asset; a retry
    past `QUEUED` creates a second real withdrawal.
15. **The withdrawal API validates neither the amount format nor the address** —
    validate both client-side.
16. **Error `message` is sometimes a string, sometimes an array**; the login
    endpoint returns a **plain string** on 401/500.
17. **A newly minted JWT invalidates the previous one** — cache per connection
    with single-flight refresh, or concurrent workers will 401 each other.

---

## 12. Contradictions between the source documents

Each needs confirming with Digiblox before the driver is finalised.

| # | Topic | PayWithCrypto v1.1 | Other source |
|---|---|---|---|
| 1 | **Auth endpoint** | `POST /gateway/api/v1/auth/login/jwt` | Transfer guide v2.0: `POST https://app.digiblox.io/jwt/generate` |
| 2 | **Token reuse** | Valid 1 h; cache and refresh on expiry | Transfer guide: only the *most recent* token per merchant is valid; a new one invalidates the old |
| 3 | **Deposit status path** | `GET /v3/deposits/merchant`, `fO=EQ` | Support thread: `GET /v3/deposits`, `fO=C` |
| 4 | **Notification key** | `"notification": { success_url, fail_url }` | Widget flow PDF: `"system": { ... }` |
| 5 | **`fiat_amount` type** | String, exactly 2 decimals — `"150.00"` | Widget flow PDF: numeric `150.00` |
| 6 | **Payment link host** | `https://widget.digiblox.io/widget/auth?paymentId=…` | Widget flow PDF: `https://pay.crymbo.com/w/tx/crymbo_tx_XXXX` |
| 7 | **Path form** | `/gateway/api/v1/v3/…` | Widget flow PDF: `/gateway/api/v3/…` and `{version}` placeholders |
| 8 | **Transfer paths** | — | Create is `/gateway/api/v1/v3/transfers/centralized`; status is `/gateway/api/v1/transfers/{id}` — inconsistent `/v3` segment |
| 9 | **`initial_rate_currency_id`** | — | Its fixed value `bkE0RmNjbEhCUmc9` is byte-identical to the example `merchant_id` in the deposit guide. One of the two is likely a copy-paste error |

---

## 13. Open questions / missing endpoints

Nothing in the supplied documents covers the following. Each is needed for a
complete driver.

**Blocking — cannot ship without**

1. **Webhook authentication.** The only mechanism documented is "static headers
   you register with us, replayed on every delivery." No HMAC, no signature, no
   shared secret, no timestamp. **Lead:** the Finrax generation documents
   `/authorization/signature`, so the platform *has* a signing scheme — ask
   whether it is available on Gateway v3. If genuinely not, we need the list of
   **source IPs** to allow-list.
2. **Sandbox / staging environment.** No test host, credentials or testnet
   support appears in any PDF. **Lead:** Finrax documents
   `/environments/sandbox-environment` alongside production, so a sandbox exists
   on the platform — ask for the Gateway v3 equivalent and test credentials.
3. **Balance enquiry endpoint.** Required before initiating withdrawals (and by
   our reconciliation). `Insufficent funds` is currently only discoverable by
   attempting a withdrawal. **Lead:** Finrax has `fetch-business-balance` /
   `get-business-balances` — ask for the Gateway v3 path. Note a business holds
   **two** balances, crypto and fiat.
4. **Refunds.** No refund endpoint exists, yet the guide instructs us to refund
   `OVERPAID` excess and `PARTIALLY_PAID` amounts. Is a refund an outbound
   Centralized Transfer, or is it manual via support?

**Important — affects correctness or UX**

5. **Withdrawal webhook.** Deposits get one; transfers are poll-only. Is there a
   `WITHDRAWAL_NOTIFICATION_URL` equivalent?
6. **Withdrawal fee, in advance.** The minimum fee only surfaces as a 400 error
   string. Is there an endpoint (or a static table) for the fee and the minimum
   withdrawable amount per asset/network?
7. **Minimum / maximum deposit amounts** per asset and network.
8. **Concrete paths for the currencies and quotes endpoints.** The widget PDF
   gives `/gateway/api/{version}/currencies` and
   `/gateway/api/{version}/orders/quotes` with a literal `{version}` placeholder.
   Are these `v1/v3` under JWT, and may merchants call them server-side?
9. **`GET /v3/payments/guests/{id}` — what is `{id}`?** The `paymentId` from the
   returned link, or something else? No sample response is given.
10. **Payment link lifetime.** Does a link expire? Can it be cancelled or
    voided? What happens to funds sent to an expired link's address?
11. **Rate limits** on any endpoint, and the correct back-off.
12. **Webhook retry schedule.** "Default 3 attempts" — at what intervals, and can
    a delivery be re-requested via API rather than via support?
13. **Tolerance band.** What value is configured on our account, and what do you
    recommend given wallet-deducted network fees?

**Clarifications**

14. **Credentials per environment** — confirm the exact `username`, `api_key`,
    `api_secret` and `merchant_id` for each environment, and whether deposits and
    withdrawals share one merchant account.
15. **Address-reuse policy** on our account: is reuse disabled outright, or on a
    cooldown, and how long?
16. **Auto-approve threshold** for withdrawals, above which `PENDING` means
    "awaiting a human".
17. **Travel Rule**: is it enabled for us, what data must be supplied, and which
    `travel_rule_status` values block a transfer?
18. **The per-asset withdrawal serialization** (`A withdrawal is already being
    processed for this currency`) — is this per merchant account? It caps
    withdrawal throughput to one in-flight transfer per asset.

---

## 14. Public sources checked (2026-09-21)

Searched to answer "is there a separate fiat API?" and to close the §13 gaps.
Recorded so nobody repeats the hunt.

### Reachability

| Source | Status | Notes |
|---|---|---|
| `https://docs.digiblox.io` | ❌ **Broken** | Cloudflare **error 1014** (CNAME Cross-User Banned). DNS: `docs.digiblox.io → 865b7e856c-hosting.gitbook.io`. Their GitBook custom domain is misconfigured, so the *official* API docs are down for everyone — not a block on us. **Worth telling Digiblox: their public API documentation is offline.** Search engines still index it as "Introduction \| Digiblox API Docs". |
| `https://crymbo.redoc.ly` | 🔒 Private | Returns an Auth0 login page, not a spec. The ReDoc links in the widget PDF are unusable without credentials. **Ask Digiblox for access** — this is the authoritative reference for the gateway we integrate with. |
| `https://docs.finrax.com` | ⚠️ Partial | Root page and `sitemap.xml` are public; every `/api/*` and `/references/*` sub-page returns 403 to non-browser clients. The sitemap still reveals the full endpoint inventory. |
| `https://blog.finrax.com/guides/*` | 🔒 Private | 307-redirects into `app.gitbook.com`, which needs a login. |
| `https://digiblox.io/en/*` | ⚠️ JS-only | Marketing pages are client-rendered shells; `curl` returns no text. Content only via search snippets. |
| `https://backoffice.digiblox.io` | — | Live, titled **"Finrax Backoffice"** — the white-label evidence. |

### Finrax endpoint inventory (from its sitemap)

Evidence of what the platform can do, **not** a contract for our driver. Note
there is no fiat-payment page anywhere in this list — only fiat *rates*.

```
/authorization  /authorization/management  /authorization/signature
/environments/production-environment  /environments/sandbox-environment
/errors
/api/request-crypto-payment      /api/submit-crypto-payment
/api/fetch-payment               /api/fetch-payments
/api/fetch-deposit-metadata      /api/fetch-deposit-rates
/api/request-crypto-withdrawal   /api/fetch-withdrawals
/api/fetch-withdrawal-metadata   /api/fetch-crypto-withdrawal-approval
/api/fetch-crypto-withdrawal-routes
/api/fetch-business-balance      /api/fetch-currencies
/api/fetch-fiat-rates            /api/fetch-market-rates
/api/validate-address            /api/validate-address-and-destination-tag
/references/callbacks/deposit-received
/references/callbacks/withdrawal-completed
```

Capabilities visible here that our PDFs never mention — each a lead for §13:
**signature auth**, **a sandbox**, **business balance**, **address validation**
(our transfer API validates neither amount nor address), **withdrawal
metadata/routes/approvals** (fees and minimums in advance), and a
**withdrawal-completed callback** (our transfer flow is poll-only).

### What this did and did not settle

**Settled:** there is no fiat customer-payment API, on Digiblox or on the
platform underneath it. "Fiat deposits and withdrawals" is merchant treasury via
the Dashboard's Billing module — fiat top-up in, settlement to your bank in EUR
/ USD / GBP out. See §1.

**Not settled:** every §13 question still needs Digiblox to answer it for
**Gateway v3 specifically**. The Finrax docs prove these capabilities exist on
the platform; they do not prove they are exposed on the API generation we are
building against, and their contracts differ.

---

## 15. Company ledger (from `transaction-history-2.csv`)

A 28-row dashboard export covering 2026-05-28 → 2026-07-29, **mostly** the
company/treasury ledger. Customer-facing detail is §16.

The source file is **`transaction-history-2.csv`** (20 columns). An 8-column
`transaction-history.csv` was also supplied; it was verified to be a strict
subset — same 28 rows, no column the wide export lacks — and deleted as
redundant. Everything below is from the wide export.

### `Account` — the column that separates company money from customer money

`Own` (25 rows) vs `Guest` (3 rows). This is the two-ledger distinction made
explicit in one field, and it confirms §1:

| `Account` | Rows | What |
|---|---|---|
| `Guest` | 3 | **All `Deposit / Crypto`.** The customer deposits detailed in §16 — same three `cid`s. Never fiat. |
| `Own` | 25 | Our treasury: fiat top-ups, conversions, outbound transfers, **and one `Deposit / Crypto`** (995 USDT, ref `<cid-4>`). |

> That last row explains a §16 gap: the company export has **four** crypto
> deposits but the guest export has only three. The fourth is `Account = Own` —
> a company crypto deposit, not a customer payment. **Filter on
> `Account = Guest` before treating a deposit as a customer payment.**

**Every fiat row is `Account = Own`, `Network = Fiat`, `Payment method = Wire
transfer`.** Bank wires funding our own balance — the definitive answer to §1.

**Columns:** `Created at, Type, Asset class, Amount, Fee, Net / received, Status, Reference`

### Three transaction types × two asset classes

| Type | Asset class | Count | What it is |
|---|---|---|---|
| `Deposit` | `Crypto` | 4 (2 Completed, 2 Pending) | A customer paying us on-chain — the §3–§5 flow. |
| `Deposit` | `Fiat` | 3 (1 Completed, 2 **Declined**) | **Our own fiat top-up.** Money we wire in to fund the account — never a customer payment. |
| `Conversion` | `Fiat` | 6 (all Completed) | Fiat balance → crypto balance (USD → USDT). |
| `Conversion` | `Crypto` | 4 (all Completed) | Crypto → crypto; `Net / received` equals `Amount` exactly, so this looks like an internal rebalance/settlement allocation, not a trade. |
| `Transfer` | `Crypto` | 11 (all Completed) | **Outbound withdrawal** — the §6 Centralized Transfer. |

`Status` values seen: `Completed`, `Pending`, `Declined`. Note these are
**dashboard labels and match neither API vocabulary** — not the deposit
lifecycle (`CONFIRMED`/`REJECTED`), not the webhook verdicts
(`COMPLETED`/`PARTIALLY_PAID`/`OVERPAID`), not the transfer lifecycle. A third
vocabulary. Do not map CSV statuses onto API statuses.

### The dominant flow on this account is a fiat-funded crypto payout

The rows pair up tightly in time:

```
22/07 21:01:46   Conversion  Fiat    150 USD      → 149.967007 USDT
22/07 21:02:53   Transfer    Crypto  149.25 USDT  + 0.75 fee  = 150.00 out
```

Same shape on 22/07, 27/07, 28/07, 29/07 and 30/06 — convert fiat to USDT,
then immediately send USDT on-chain, usually within about a minute. **We are
using Digiblox mainly to pay people out in crypto against a fiat balance,** not
to accept crypto from customers. Worth confirming that matches the intent for
the driver, because it puts §6 (transfers) on the critical path ahead of §3.

### Fees observed

| Flow | Fee |
|---|---|
| `Deposit / Crypto` | **0.5% of gross** (995 + 5 = 1000; 14948.71 + 75.12 = 15023.83) |
| `Deposit / Fiat` | **1.0% of gross** (4950 + 50 = 5000) |
| `Conversion` (both classes) | **0** in every row — the spread is in the rate, not a fee line |
| `Transfer / Crypto` | **0.5% of gross** in 8 of 11 rows |
| `Transfer / Crypto` (3 Tron rows) | **≈ 3.10 USDT floor** — 2.33%, 1.55%, 1.24% of gross |

**The fee is `max(0.5%, a per-network minimum)`.** The wide export's `Network`
column resolves this: all three outliers are **Tron**, where 0.5% of gross
(0.665, 1.25, 1.00) fell below a floor of ≈3.10 USDT. A fourth Tron transfer at
9,350 gross paid exactly 0.5% (46.75) because the percentage cleared the floor.
Ethereum and Binance Smart Chain show no floor at comparable sizes — 150 gross on
Ethereum paid 0.75.

The floor is not a constant: `3.101672388` twice, `3.102590422` once. It is a
**fiat-denominated fee (≈ $3.10) converted at the prevailing rate**, so it drifts.

Do not hard-code any of this — read `system_fee` from the API.

Conversion rates sit just above parity (150 USD → 149.967 USDT; 130 USD →
130.055 USDT), i.e. roughly ±0.03%, so the USD/USDT spread is thin but signed
in both directions.

### Reference formats — four different identifier schemes

| Type / class | Reference format |
|---|---|
| `Transfer / Crypto` | `0x` + 64 hex (EVM tx hash), or bare 64 hex (non-EVM chain) |
| `Deposit / Crypto` | **13- or 16-digit numeric** |
| `Deposit / Fiat` | 12-char lowercase alphanumeric (`<redacted-ref>`) |
| `Conversion` (both) | 32 hex, i.e. a dashless UUID |

> **A crypto deposit's `Reference` here is *not* the `tx_hash`** — it is the
> **`cid`**, the platform's own deposit id, and it is the join key to the guest
> export in §16. All three `cid` values there match a `Deposit / Crypto`
> `Reference` here exactly. Resolved; no longer an open question.

### The merchant reference: a column exists, but it is empty

**There is still no `external_transaction_id` column.** The wide export does add
two fields that bear on this:

| Column | Fill rate | Verdict |
|---|---|---|
| `Note` | **0 / 28 — empty in every row** | Almost certainly the export surface of the transfer API's **`user_note`** (free text, max 255 chars, §6.1). We have never sent it, so it is blank. |
| `Reference` | 28 / 28 | Platform-side ids only — `cid`, tx hash, or conversion UUID. Never ours. |

So: **no, this export does not contain a merchant reference today** — but `Note`
is a carrier we control.

**Recommendation: populate `user_note` with our internal reference on every
withdrawal.** It costs nothing, it is the one field on the transfer API that
accepts arbitrary text, and it would make treasury exports joinable to our
ledger. Confirm with Digiblox that `user_note` is what surfaces as `Note`
(§13) before relying on it.

There is **no equivalent on the deposit side** — the payment link's
`external_id` does not appear in either export. For deposit reconciliation use
`GET /v3/deposits/merchant` (§5), which does carry `external_transaction_id`.

### `Updated at` can lag `Created at` by weeks

`Created at` and `Updated at` are identical on every conversion and transfer, but
diverge sharply elsewhere:

| Row | Created | Updated | Lag |
|---|---|---|---|
| Guest USDT deposit (the settled USDT one) | 28/05 05:04 | 02/06 11:18 | **5 days** |
| Fiat top-up, Completed | 16/06 13:10 | 22/06 15:20 | **6 days** |
| Fiat top-up, Declined | 04/06 14:04 | 11/08 08:45 | **68 days** |

A deposit that settled on-chain in minutes stayed open for five days, and a
declined wire was not resolved for over two months. **Do not assume a
transaction reaches a terminal state quickly, and do not time out reconciliation
on a short window** — §5's "poll for 30 minutes then back off" is right for the
on-chain leg, but the ledger entry can move long afterwards.

### Added to the questions for Digiblox

- **How is a `Deposit / Fiat` initiated — API or dashboard only?** If there is an
  endpoint, it is the fiat API we went looking for in §14.
- **Why did two fiat deposits show `Declined` at 0 USD?** A bank wire does not
  normally decline at zero; that pattern suggests an interactive or
  card-authorization step. Knowing which matters for whether we can automate it.
- **Does the transfer API's `user_note` surface as the export's `Note` column?**
  If yes, we can carry our own reference on every withdrawal and make treasury
  exports joinable. Is there any equivalent for deposits?
- **Can these exports be produced via an API** rather than a dashboard download?
- **`Conversion / Crypto` with identical in and out** — what operation is that?

---

## 16. Customer deposits (from `guest.crypto.deposits.csv`)

Three rows — the guest-side view of the §3–§5 flow, and **the only export that
shows what customers actually did.** Every row is crypto. This is the file that
settles the fiat question (§1).

**Columns (20):** `cid, Guest Email, Expected amount, Expected Currency, Expected
Fiat Value, Expected Fiat Currency, Credited amount, Received Currency, Received
Fiat Value, Received Fiat Currency, Total Amount, Total Currency, Total Amount
Fiat Value, Total Amount Fiat Currency, Sender Address, Transfer To, network,
Status, hash, Execution time`

Note what is *absent*: no fiat instrument, no card, no bank field. Fiat appears
only as a **valuation** of a crypto amount — `Expected Fiat Value`, `Received
Fiat Value`, `Total Amount Fiat Value` — exactly the denomination pattern of §1.

### `cid` is the join key

All three `cid` values match a `Deposit / Crypto` `Reference` in the company
ledger (§15) exactly:

| `cid` | §16 status | §15 row |
|---|---|---|
| `<cid-3>` | Deposit Completed | 28/05 `Deposit Crypto 14948.711446005 USDT`, fee `75.119152995`, Completed |
| `<cid-1>` | Deposit Initiated | 28/06 `Deposit Crypto 0 ETH`, Pending |
| `<cid-2>` | Deposit Initiated | 01/06 `Deposit Crypto 0 ETH`, Pending |

**`cid` is the platform's deposit id** — the customer ledger and the company
ledger join on it. Still no `external_transaction_id` in either export, so
neither joins to *our* orders (§15).

### The amount semantics of §4, confirmed against real money

The one settled row, `cid <cid-3>`:

| Column | Value | §4 / §5 equivalent |
|---|---|---|
| `Expected amount` | `15023.830599907475 USDT` | webhook `expected_amount` |
| `Total Amount` | `15023.830598999999 USDT` | webhook `total_amount` — gross received |
| `Credited amount` | `14948.711446005 USDT` | webhook `amount` — net of fee |
| `Expected Fiat Value` | `15000 USD` | deposits-search `expected_amount`/`expected_currency` |
| `Total Amount Fiat Value` | `14999.7 USD` | — |
| `Received Fiat Value` | `14924.7 USD` | — |

`Total − Credited = 75.119153`, which is exactly the fee booked in §15, and 0.5%
of gross. And `Total ≈ Expected` to nine decimal places → this deposit is
`COMPLETED` / `CONFIRMED`.

**This is the §11 trap #5 demonstrated on live data:** reconciling against
`Credited amount` would make a perfectly-paid 15,023.83 USDT deposit look 75.12
short and book it as `PARTIALLY_PAID`. Reconcile on `Total Amount`.

It also shows the two `expected_*` quantities are genuinely different numbers and
must never be compared: `15023.830599907475 USDT` (crypto) vs `15000 USD` (fiat).

### A fourth status vocabulary

`Deposit Initiated` and `Deposit Completed`. That is now four disjoint
vocabularies for the same lifecycle:

| Surface | Values |
|---|---|
| Deposits API (§5) | `SENT_DEPOSIT_ADDRESS`, `SENT_TOKEN`, `PENDING`, `PUBLISHED`, `CONFIRMED`, `REJECTED`, `REJECTED_BY_ADMIN` |
| Webhook (§4) | `COMPLETED`, `PARTIALLY_PAID`, `OVERPAID` |
| Company ledger (§15) | `Completed`, `Pending`, `Declined` |
| Guest export (§16) | `Deposit Initiated`, `Deposit Completed` |

Branch on the API values only. Treat the two CSV vocabularies as display labels.

### Unpaid links look exactly like §5 Case 2

Both `Deposit Initiated` rows: `Credited amount` `0`, `Total Amount` `0`,
`Sender Address` empty, `hash` empty — but `Transfer To` **is** populated with
the issued deposit address and `Expected amount` is set. The customer opened the
link, got an address, and never sent anything. Matches the
`SENT_DEPOSIT_ADDRESS` shape in §5 field for field, and confirms that an issued
address is not a payment.

Both are the same guest (`guest1@redacted.example`), both 5000 USD in ETH,
abandoned 27 days apart.

### Other confirmations

- **`Guest Email` is populated on every row** — the widget captures the email
  even when the customer abandons, so Flow A's email-verification step runs
  before an address is issued.
- **Networks in use: `ETHEREUM` and `TRON`.** The settled row is TRON/USDT, and
  its `hash` is bare 64-hex with no `0x` prefix
  (`<redacted-tx-hash>`) —
  confirming §15's inference that bare-64-hex references are non-EVM chains and
  `0x`-prefixed ones are EVM. **Do not assume a `0x` prefix when parsing hashes.**
- **`Transfer To` = the API's `to_address`; `Sender Address` = `from_address`**,
  null until funds arrive.
