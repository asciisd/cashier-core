# Quirks

Where the live Heropayments contract departs from its documentation, and where
the docs are silent. Each entry cites both sides: a passage in a mirror in this
directory, and a line in this repo.

Seven entries are marked **Unverified**: they describe behaviour that may be
wrong in production, recorded rather than fixed because settling them needs
production callback logs rather than a document search. Each names what would
settle it.

## 1. Repeat deposits reuse `externalOrderId`

**Docs** (`overview.md`, "Multiple deposit processing" and "Static deposit
address per each customer"): a deposit address is generated per unique
`customerId` and reused forever. When a customer sends to a saved address
without creating an order, Heropayments raises a *new payment* carrying the
**same `externalOrderId`** with a fresh `sequence` value, and posts its callback
to the original payment's `callbackUrl`. "Automated mistaken deposits
processing" does the same with `sequence: original` and a changed `payCurrency`.

**Here:** `HeropaymentProvider.php:200-205` resolves our transaction from
`externalOrderId` alone, and `sequence` is read nowhere under
`src/Drivers/Heropayment/`. A second deposit therefore arrives addressed to the
first deposit's transaction. We pass the MT5 trading account login as
`customerId` (`HeropaymentProvider.php:109-114`), so every account holds one
permanent deposit address for as long as it exists.

**Unverified:** whether any repeat deposit has actually arrived. Search
production callback logs for two payloads sharing an `externalOrderId` with
differing `sequence`.

## 2. `actuallyPaid` is not a Heropayments field

**Docs** (`callbacks.md`, both V2 callback examples — the Custody deposit
callback uses `amount`/`clientAmount` instead; `v2.md`, "Payment status check
by id (V2)"): the amount actually received is `paidAmount`. The string
`actuallyPaid` does not occur anywhere in the collection.

**Here:** `HeropaymentAdapter.php:78` and `HeropaymentAdapter.php:137` both read `actuallyPaid`, so
`settlement_actually_paid` and `heropayment_actually_paid` are never populated —
the reconcile-to-what-arrived path has no figure to work from.

How it got there: the docs still link `support@nowpayments.io` from the
callbacks section (`callbacks.md`, step 6). Heropayments' documentation is a
NOWPayments fork, and `actually_paid` is NOWPayments' spelling.

**Unverified:** whether Heropayments also sends an undocumented `actuallyPaid`
alongside `paidAmount`. Grep a production callback payload for both.

## 3. `partially_paid` is not a documented status

**Docs** (`v2.md`, "Payment status check by id (V2)"): the V2 status vocabulary
is `waiting`, `confirming`, `exchanging`, `sending`, `finished`, `failed`,
`refunded`, `hold`, `expired`. Nine values, no `partially_paid`.

**Here:** `HeropaymentAdapter.php:111` maps `partially_paid` to `Succeeded`, and
`HeropaymentAdapter.php:81-83` flags it for admin attention. Another
NOWPayments name.

**Unverified:** whether the status is emitted at all. Search production
callbacks for `"status":"partially_paid"`. If it never appears, the mapping is
dead code; if it does, the docs are incomplete and this entry stays.

## 4. Signature payload encoding is only specified for JavaScript

**Docs** (`overview.md`, "API Request signing"): normalize the JSON before
signing — no spaces, no newlines, no zero-padded numbers — with
`JSON.stringify(JSON.parse(x))`. What that implies outside JavaScript is left
unsaid: Node leaves `/` and non-ASCII unescaped, while PHP's `json_encode`
escapes both by default. Sign PHP's default output and every request returns
`401 Invalid signature`.

**Here:** `HeropaymentClient.php:17` pins
`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, and the same encoding is
reused to verify callbacks (`HeropaymentProvider.php:187-198`).

## 5. `networkfee` is documented in USDT and delivered in native units

**Docs** (`v2.md`, "Network fees (V2)"): the value is described as a USDT
equivalent.

**Here:** live values read as native currency units — `btc` returns `0.000007`,
which is about $0.75 as BTC and meaningless as USDT.
`HeropaymentQuoteService.php:156-171` treats them as native units and
`HeropaymentClient.php:88-94` records the discrepancy at the call site. Confirm
with Heropayments before showing a converted figure to customers.

## 6. Invoice id is not payment id

**Docs** (`callbacks.md`, V2 deposit callback): of `invoice.id` — "not
recommended to use it as payment identificator, as it does not appear in the
report". The create-invoice response returns that invoice id as its top-level
`id`; callbacks carry a different payment id.

**Here:** `HeropaymentProvider.php:80-92` returns `externalOrderId` as the
transaction id and keeps the invoice id in metadata, so correlation runs on the
one identifier both sides agree on.

## 7. No endpoint exposes the contracted `feePercent` ahead of a payment

**Docs:** `feePercent` appears repeatedly in `v2.md` and `custody.md`, but only
ever as a field on a payment that already exists — a created payment, a status
check, or a callback (`callbacks.md`). No endpoint returns the merchant's
contracted rate on its own, ahead of any payment.

**Here:** `HeropaymentQuoteService.php:173-185` reads it from connection config,
because a quote must be shown before any payment exists. That configured value
is an assertion about a commercial agreement, not a fact from the API —
reconcile it against `feePercent` on incoming callbacks.

## 8. The widget takes no currency allow-list

**Docs** (`v2.md`, "Create an invoice (widget)" and "Create an invoice (widget -
chosen currency)"): `payCurrency` is optional and singular. There is no
parameter for offering a subset.

**Here:** `HeropaymentProvider.php:116-135` pins exactly one currency, chosen by
the customer from the connection's `currencies` list in our own deposit modal.
Left null, the widget lists everything Heropayments supports — including
currencies we never quoted a minimum for.

## 9. `/v2/payments-address` exists in prose only

**Docs:** named in `overview.md` under "Static deposit address per each
customer" as one of the V2 deposit-creating methods, and again in the V2 error
table (`errors.md`). The collection defines no such request — no parameters, no
body, no response example.

**Here:** nothing. Recorded so the next person to find the name in the error
table does not go looking for a contract that was never published.

## 10. Custody's status vocabulary is disjoint from V2's

**Docs** (`custody.md`, "Payment status check by id (Custody)";
`overview.md`, "Payments statuses - (Custody flow)"): Custody uses `new`,
`pending`, `processing`, `finished`, `failed`, `refunded`, `hold`, `expired`.
Three of those — `new`, `pending`, `processing` — do not exist in V2.

**Here:** `HeropaymentAdapter::mapStatus` (`HeropaymentAdapter.php:110-117`)
knows only the V2 names, so all three Custody in-progress statuses fall through
`default => Pending`.
`processing` in particular would report as Pending rather than Processing.

Latent, not live: nothing in this package calls `/custody`. Recorded because the
Custody mirror now ships beside it, and the first person to switch flows will
not otherwise see this.

## 11. A failed payout still reserves its `externalOrderId` — **Unverified**

`WithdrawalWorkflow::sendPayout()` gives every resend after a failure a new
order id (`WD-<ULID>-2`, `-3`, …) on the assumption that Heropayments keeps a
failed or refunded payment's `externalOrderId` taken. The docs only say the id
"must be unique to create a transaction". Confirm on the first live failure;
if failed ids are released, the suffix is harmless but unnecessary.

## 12. A duplicate `externalOrderId` is a 4xx whose message contains "not unique" — **Unverified**

`HeropaymentPayoutService::send()` treats that response as "an earlier attempt
landed" and looks the payout up instead of failing. `errors.md` lists the
message (`Field externalOrderId for this user is not unique`, 400) but not the
body shape; the service reads `message`, then `error`, then the raw body.
This is the idempotency key that makes resending an unknown payout safe —
confirm it before relying on it at volume.

## 13. Which currency the withdrawal deduction is quoted in — **Unverified**

The payout preflight estimates the deduction as
`(amount × rate(currency → walletCurrency, withdrawal) × (1 + fee_percent) +
networkFee × rate(payoutCurrency → walletCurrency)) × (1 + buffer)` and
compares it with `v2/balance`. It assumes `merchantAmountUsdt` is denominated
in the balance's `walletCurrency` and that withdrawal network fees are native
payout-coin units (see `HeropaymentClient::getNetworkFees()`). Compare the
estimate with the first live payout's `merchantAmountUsdt`. A payout coin whose
withdrawal fee row is missing, null or non-numeric refuses the payout
(`quote_unavailable`); it is never estimated as a zero fee.

## 14. An unknown order id is answered with HTTP 404 — **Unverified**

`HeropaymentPayoutService::lookup()` (via
`HeropaymentClient::getPaymentByOrderIdResponse()`) reads only a 404 from
`GET /v2/payments/order/{id}` as "no such payout". Any other answer — a 5xx,
another 4xx, a timeout, a 2xx without an `id` — is "lookup failed" and throws
`PayoutOutcomeUnknownException`. The docs do not say what the endpoint returns
for an order it has never seen. Until that is confirmed, a payout whose send
was unknown stays Unknown (fail-safe: never resent) unless the lookup returns
404; `WithdrawalWorkflow::syncPayout()` only moves an unknown payout to Failed
on that 404, and only once its send claim has expired. Confirm with a lookup of
a made-up order id against production.
