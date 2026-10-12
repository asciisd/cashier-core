# PayCrossBorder payouts — design

*2026-10-12*

Send approved withdrawals through PayCrossBorder (PCB) on all three of its
rails — crypto (stablecoin to a wallet), fiat (bank account) and WPT (mobile
wallet) — and close them by polling PCB's transaction enquiry.

PCB is payout-only for us: its API is for Financial Institution customers and
has no collection, deposit or callback endpoints. The driver therefore
implements `SendsPayouts` and nothing on the deposit side. It is the first
payout driver that needs the beneficiary's identity (name, gender,
nationality, address, sometimes ID and tax numbers), so this design adds a
rail-neutral beneficiary object to `PayoutRequest`.

Contract references: https://docs.paycrossborder.com/#/ (docsify; the pages
are `AUTH.md`, `QUOTA.md`, `PAYOUT-CRYPTO.md`, `PAYOUT-FIAT.md`,
`PAYOUT-WPT.md`, `ENQUIRY.md`, `responseCodes.md`, `master.md`,
`CURRENCYVALIDATIONS.md`, `COUNTRYVALIDATIONS.md`). Implementation vendors
the relevant parts into `.claude/skills/paycrossborder/references/`.

## Scope

In:

- A `paycrossborder` driver implementing `SendsPayouts` for the crypto, bank
  and wallet rails.
- `PayoutBeneficiary` (with `BeneficiaryAddress`, `BeneficiaryBank`,
  `BeneficiaryWallet`) and an optional `beneficiary` on `PayoutRequest`.
- `WithdrawalWorkflow` reading `details['beneficiary']` into the request.
- Balance preflight against PCB's wallet endpoint.
- Wiring: bundled driver map, config docblock example, `Cashier::fakeConnection`
  defaults, README, CHANGELOG, a `paycrossborder` skill.

Out:

- Deposits, refunds, captures — PCB offers none to us.
- Webhooks — PCB sends none. No controller, route or `WebhookSimulator`
  recipe.
- PCB customer/beneficiary registration, document upload, and the master
  (lookup-list) APIs. Codes such as `remittancePurpose` come from config;
  beneficiaries are sent inline on each payout.
- B2B / C2C / C2B service types. Every payout is B2C from our pre-registered
  business.
- The Bank List API. The host supplies bank name, code and SWIFT directly.
- A scheduled reconcile command. `syncPayout()` is the building block.

## Decisions

| Question | Decision |
|---|---|
| Rails | Crypto, bank and wallet, in one driver. |
| Sender | Our company, registered with PCB once. Sent as `{isAutoRegistered: false, declaration: false, customerCode, docReferenceNumber}`; `serviceTypeCode = B2C`. |
| Beneficiary transport | Typed, optional `PayoutBeneficiary` on `PayoutRequest`, populated from `details['beneficiary']`. |
| Amount fixed by the quote | `payinAmount` — what we send. FX and fees come out of what the beneficiary receives, so PCB's wallet debit equals the ledger debit. |
| Charges | `chargeTypeCode` from connection config `charge_type` (BEN/SHA/OUR), default `OUR`. |
| Structure | One driver; the shared flow lives in the payout service, the per-rail `receiver` block in a builder per rail. |
| Technical Failure | `Unknown`, not `Failed` — the docs do not say whether money moved, and `Failed` refunds the customer. |

## Components

All under `src/Drivers/PayCrossBorder/` unless noted.

### `PayCrossBorderProvider implements PaymentProcessorInterface, SendsPayouts`

- Constructor refuses to resolve when `base_url`, `username`, `password`,
  `sender_code` or `customer_code` is missing.
- `charge`, `refund`, `capture`, `authorize`, `void`, `parseWebhook`,
  `parsePayoutWebhook` throw `PaymentProcessingException` ("not supported by
  PayCrossBorder"). `retrieve()` returns `null`. `verifyWebhookSignature()`
  returns `false`.
- `supports('payouts')` is true; everything else false.
- `preflight`, `send`, `lookup` delegate to `PayCrossBorderPayoutService`.

### `PayCrossBorderClient`

HTTP only; knows endpoints and auth, not business rules.

- `token(): string` — `POST {base_url}/ewallet/oauth/token`, form body
  `grant_type=password&scope=read write&username=…&password=…`, headers
  `channel: WEB`, `source: AGENT`, `Accept-Language: en`. Cached per
  connection for `expires_in − 60` seconds. With `password_hash = 'md5'` the
  configured password is sent as `strtoupper(md5($password))`; with `null`
  (default) it is sent verbatim.
- `forgetToken(): void`.
- `quote(array $body): array` — `POST /ewallet/api/v1/fxratequotation/api`.
- `payout(array $body): array` — `POST /ewallet/api/v1/payoutProcess/api`,
  body wrapped as `{"payout": {...}}`.
- `transaction(string $transId): array` —
  `GET /ewallet/api/v1/transactionInfo/api?types=all&status=all&transId=…`.
- `balance(): array` — `GET /ewallet/api/v1/wallet/walletOwner/{wallet_owner_code}`.

Every call sends `Authorization: Bearer {token}` plus the three headers above
and treats a response as successful only when HTTP is 2xx **and**
`resultCode === "0"`. A 401 calls `forgetToken()`. Only `transaction()` and
`balance()` (read-only) retry once with a fresh token after a 401; `quote()`
and `payout()` never retry.

Failures surface as a `PayCrossBorderApiException` (driver-local, extends
`PaymentProcessingException`) carrying HTTP status, `resultCode`,
`resultDescription` and whether the failure was a transport error (timeout,
connection refused) — the service decides what each means.

### `PayCrossBorderPayoutService implements SendsPayouts`

Owns the flow; builds `transactionInfo`, `sender` and `compliance`, delegates
`receiver` to the rail's builder. Detailed in *Flow*.

### `Receivers\ReceiverBuilder` (interface) and three implementations

```php
interface ReceiverBuilder
{
    public function rail(): Rail;

    /** @throws PaymentProcessingException naming the first missing/invalid field */
    public function assertComplete(PayoutRequest $request): void;

    /** @return array{customer: array<string, mixed>} */
    public function build(PayoutRequest $request): array;
}
```

All three emit `receiver.customer` with the shared identity fields:
`receiverFirstName`, `receiverMiddleName`, `receiverLastName`,
`receiverGender`, `receiverNationality`, `receiverDOB`, `receiverIdType`,
`receiverIdNumber`, `receiverIdExpiration`, `receiverIdCountry`,
`receiverOccupation`, `dialCode`, `receiverMsisdn`, `receiverAddressLineOne`,
`receiverAddressLineTwo`, `receiverCountry`, `receiverAddressState`,
`receiverAddresssCity` (PCB's spelling, three s), `receiverPinCode`. Null
fields are omitted.

- **`CryptoReceiver`** — adds `walletAddress` (`payoutAddress`) and
  `cryptoNetwork` (`payoutExtraId`, required). Also requires `dateOfBirth`
  (mandatory for crypto receivers). Wallet address 26–50 characters.
- **`BankReceiver`** — requires `bank`; adds `receiverAccountNumber`,
  `receiverAccountHolderName`, `receiverBankName`, `receiverBankCode`,
  `receiverSwiftCode`, `receiverAccountType`, `receiverBankCountry`,
  `receiverBankBranchName`, `taxIdNumber`. Country rules from the docs:
  `taxId` required for ARG, THA, BRA, ECU, GTM, HND, KAZ, MEX, PER, CHL, PHL,
  JPN, URY, COL, CRI, RUS, BLR, PAK; `accountType` required for BRA, PHL,
  DOM, URY; `phone` required for BRA, PHL, CHN, UGA, URY, COL, MEX, HKG, MYS
  when `swiftCode` is set.
- **`WalletReceiver`** — requires `wallet` and `phone`; adds
  `receiverServiceProviderName`, `receiverServiceProviderCode`,
  `receiverServiceProviderMobile`.

The rule lists are constants on the builders, each with a docblock pointing
at the docs page it came from. They cover the rules the payout pages state
inline; the per-country "Local RAIL" tables in `COUNTRYVALIDATIONS.md` are
not encoded. A payout that breaks one of those is rejected by PCB with a
`resultCode ≠ "0"` and no reference, which is a definite rejection (nothing
sent).

### `Rail` (enum)

`Crypto`, `Bank`, `Wallet`. `Rail::for(PayoutBeneficiary)`: `bank` set →
`Bank`; else `wallet` set → `Wallet`; else `Crypto`. Setting both `bank` and
`wallet` is refused.

### `PayCrossBorderAdapter`

- `mapPayoutStatus(?string $statusName, ?string $shortCode): PayoutState` —
  see *Status mapping*.
- `payoutReceipt(array $enquiryRow): PayoutReceipt`.

### Shared data objects (`src/DataObjects/`)

All `readonly`, with `fromArray(array): self` (throws
`InvalidArgumentException` on wrong types, unparseable dates, or a country
that is not three letters) and `toArray(): array`.

- `PayoutBeneficiary`: `firstName`, `lastName`, `gender`, `nationality`
  (ISO-3), `address` (`BeneficiaryAddress`); optional `middleName`,
  `dateOfBirth` (Y-m-d), `phone`, `dialCode`, `email`, `occupation`, `taxId`,
  `idType`, `idNumber`, `idCountry`, `idIssueDate`, `idExpiry`, `bank`
  (`BeneficiaryBank`), `wallet` (`BeneficiaryWallet`).
- `BeneficiaryAddress`: `line1`, `city`, `state`, `postalCode`, `country`
  (ISO-3); optional `line2`.
- `BeneficiaryBank`: `accountNumber`, `accountHolderName`, `bankName`;
  optional `bankCode`, `swiftCode`, `accountType`, `bankCountry`,
  `branchName`.
- `BeneficiaryWallet`: `providerName`, `providerCode`; optional
  `providerMobile`.

The objects carry no PSP rules; field names are rail-neutral so a later
driver can reuse them. `idType`, `occupation` and similar hold whatever code
the target PSP expects (for PCB, master-API codes such as `PAYD002`).

`PayoutRequest` gains `public ?PayoutBeneficiary $beneficiary = null` as its
last constructor parameter.

### `WithdrawalWorkflow`

Where it builds `PayoutRequest` (`src/Withdrawals/WithdrawalWorkflow.php`
around line 1085), it adds
`beneficiary: isset($details['beneficiary']) ? PayoutBeneficiary::fromArray($details['beneficiary']) : null`.
An `InvalidArgumentException` from `fromArray` is rethrown as
`PaymentProcessingException` (nothing sent). No other workflow change.

## Flow

### References

- `sendClientTrxReference`: `externalOrderId` uppercased, if it is already
  10–30 alphanumeric characters; otherwise `'PCB' . strtoupper(substr(hash('sha256', $externalOrderId), 0, 27))`
  (30 chars). Deterministic, so `lookup()` recomputes it.
- `descriptionText`: the same reference cut to 25 characters.
- `docReferenceNumber`: the same reference.

### `preflight(PayoutRequest): PayoutPreflight`

1. `withdrawals_enabled` false → `refused(message: 'PayCrossBorder withdrawals are disabled')`.
   Amount above `withdrawal_max_amount` (or that key unset) → refused.
2. No beneficiary → refused. Mixed `bank` + `wallet` → refused.
3. `payoutCurrency` and the configured `send_currency` must resolve through
   `currency_codes` (case-insensitive keys); unmapped → refused.
4. The rail's builder `assertComplete()`; a failure becomes `refused` with
   the builder's message (which names the field).
5. `balance()`; pick the wallet whose `walletTypeName` is `Main Wallet` and
   whose `currencyName` equals `settlement_currency`. None, or the call
   failing → `BALANCE_UNAVAILABLE`. `value < amount × (1 + balance_buffer_percent/100)`
   → `INSUFFICIENT_FUNDS`. Else `passed(value, required, settlement_currency)`.

### `send(PayoutRequest): PayoutReceipt`

Re-runs steps 2–4 (cheap, no I/O) so a direct `send()` cannot skip them.

1. **Quote.**
   ```json
   {
     "payinAmount": "<amount>", "payoutAmount": "",
     "sendCurrencyCode": "<send_currency>",
     "receiveCurrencyCode": "<currency_codes[payoutCurrency]>",
     "settlementCurrencyCode": "<settlement_currency>",
     "destinationCountryCode": "<beneficiary.address.country>",
     "sourceCountry": "<source_country>",
     "serviceTypeCode": "B2C",
     "paymentMode": "Bank|Cash",
     "senderCode": "<sender_code>", "customerCode": "<customer_code>",
     "chargeTypeCode": "<charge_type>"
   }
   ```
   `paymentMode` is `Bank` for the bank rail, `Cash` otherwise (as in PCB's
   crypto and WPT examples). Any failure → `PaymentProcessingException`
   (nothing sent). The response's `fxRateQuotation.payinAmount` must equal
   our amount to 2 decimal places, else `PaymentProcessingException`.
2. **Payout.**
   ```json
   {"payout": {
     "transactionInfo": {
       "payinAmount": "<quote.payinAmount>", "payinCurrency": "<send_currency>",
       "type": "B2C", "requestDate": "<d-m-Y>",
       "sendClientTrxReference": "<ref>", "descriptionText": "<ref[0..25]>",
       "paymentMode": "Bank|Cash", "sendClientCode": "<sender_code>",
       "payoutCurrency": "<receiveCurrencyCode>", "payoutAmount": "<quote.payoutAmount>",
       "destinationCountryCode": "<…>", "settlementCurrency": "<settlement_currency>",
       "sourceCountry": "<source_country>",
       "fxRateValue": "<quote.fxRateValue>", "senderMargin": "<quote.senderMargin>"
     },
     "sender": {"business": {"isAutoRegistered": false, "declaration": false,
                             "docReferenceNumber": "<ref>", "customerCode": "<customer_code>"}},
     "receiver": "<builder output>",
     "compliance": {"forexQuoteId": "<quote.code>", "remittancePurpose": "…",
                    "sourceOfFund": "…", "relationship": "…"}
   }}
   ```
   Outcomes:
   - 2xx, `resultCode "0"`, non-empty `payoutResponseBean.transReference` →
     `PayoutReceipt(reference: transReference, rawStatus: status, state: Sent, payload: response)`.
   - HTTP 400/401/403/422, or 2xx with `resultCode ≠ "0"` and no
     `transReference` → `PaymentProcessingException` (definite rejection).
   - Transport error, 5xx, any other status, or a 2xx body that cannot be
     read → `PayoutOutcomeUnknownException`.

### `lookup(string $externalOrderId, ?string $reference): ?PayoutReceipt`

- Empty reference → `PayoutOutcomeUnknownException` ("PayCrossBorder can
  only be searched by its transId; link the payout reference by hand").
- `transaction($reference)`. Exactly one row in `transactionInfoResponse`,
  with `transId == $reference` and `senderUniqueId ==` our recomputed
  `sendClientTrxReference`; anything else → `PayoutOutcomeUnknownException`.
- Return `PayCrossBorderAdapter::payoutReceipt($row)`.

### Status mapping

From `responseCodes.md` (`statusName`) and the enquiry example
(`transactionStatus`):

| PCB | `PayoutState` |
|---|---|
| `In Process`, `Payout Processing`, short code `IP` | `Sent` |
| `Payout Pass` | `Paid` |
| `Payout Fail`, `Reverse` | `Failed` |
| `Technical Failure` | `Unknown` |
| anything else, or missing | `Unknown` |

`statusName` is matched case-insensitively after trimming; the short code is
consulted only when `statusName` is absent.

## Errors and safety

- **Nothing sent** → `PaymentProcessingException`: preflight refusals,
  missing/invalid beneficiary field, unmapped currency, token failure, quote
  failure or amount mismatch, definite payout rejection.
- **Maybe sent** → `PayoutOutcomeUnknownException`: payout transport error,
  5xx or unreadable reply; lookup without reference or with a mismatched
  row. `WithdrawalWorkflow` already turns this into `Unknown`.
- `payout()` is never retried automatically; PCB has no idempotency key.
- A fresh quote is fetched inside every `send()`; quotes live 4–5 minutes
  and are never carried over from preflight.
- **Payload hygiene.** Address lines, city, state and postal code have
  characters outside `[A-Za-z0-9 ]` removed (postal code also loses spaces)
  and are cut to their documented maximum length (35 for address lines, 50
  for city/state, 10 for postal code). Names, account numbers, wallet
  addresses, phone numbers and ID numbers are never altered: if one violates
  PCB's documented format the builder refuses and names the field.
- **Logging.** Requests and responses go through `PaymentLogger` with
  `password`, `access_token`, `Authorization`, names, DOB, ID number, phone,
  account number and wallet address masked. `PayoutReceipt::payload` stores
  PCB's response minus `access_token`.

## Configuration

Documented in the `config/cashier-core.php` connection docblock:

```php
'paycrossborder' => [
    'driver' => 'paycrossborder',
    'base_url' => env('PCB_BASE_URL'),              // required; the docs only say "host"
    'username' => env('PCB_USERNAME'),
    'password' => env('PCB_PASSWORD'),
    // null: send `password` verbatim (PCB issued it pre-hashed).
    // 'md5': send strtoupper(md5(password)). Unconfirmed — see Open questions.
    'password_hash' => env('PCB_PASSWORD_HASH'),
    'sender_code' => env('PCB_SENDER_CODE'),        // senderCode / sendClientCode
    'customer_code' => env('PCB_CUSTOMER_CODE'),    // our pre-registered business
    'wallet_owner_code' => env('PCB_WALLET_OWNER_CODE'), // defaults to sender_code
    'source_country' => env('PCB_SOURCE_COUNTRY'),  // ISO-3, e.g. ARE
    'send_currency' => 'USD-USA',
    'settlement_currency' => 'USD-USA',
    // our currency code (case-insensitive) => PCB currency code
    'currency_codes' => ['usd' => 'USD-USA', 'usdt' => 'USDT', 'usdc' => 'USDC'],
    'charge_type' => 'OUR',                         // BEN | SHA | OUR
    'remittance_purpose' => 'PAYP002',
    'source_of_fund' => 'PAYS005',
    'relationship' => 'PAYR002',
    // Real money, no confirmed sandbox. Off until proven; every payout capped.
    'withdrawals_enabled' => env('PCB_WITHDRAWALS_ENABLED', false),
    'withdrawal_max_amount' => env('PCB_WITHDRAWAL_MAX_AMOUNT'),
    'balance_buffer_percent' => env('PCB_BALANCE_BUFFER_PERCENT', 2),
],
```

The host stores per withdrawal, in `details`: `payout_address` (wallet
address, IBAN or account number, or MSISDN — informational for bank and
wallet rails), `payout_currency`, `payout_extra_id` (crypto network such as
`TRC20`, crypto only), and `beneficiary` (the `PayoutBeneficiary` array).

## Testing

Pest + `Http::fake`, existing layout. Fixture `tests/Fixtures/PayCrossBorderApi.php`
holds canonical token, quote, payout, enquiry and balance responses built
from the docs' examples.

- `tests/Unit/Drivers/PayCrossBorderClientTest.php` — token request shape
  (form body, three headers), caching and expiry, `forgetToken` on 401,
  `password_hash` both ways, `resultCode ≠ "0"` handling, one retry on
  `transaction()` / `balance()`, none on `quote()` / `payout()`.
- `tests/Unit/Drivers/PayCrossBorderAdapterTest.php` — every status row,
  short-code fallback, unknown values.
- `tests/Unit/Drivers/PayCrossBorderPayoutServiceTest.php` — preflight
  (disabled, over cap, cap unset, no beneficiary, bank+wallet, unmapped
  currency, incomplete beneficiary, balance low / unavailable / no Main
  Wallet); send (exact quote and payout bodies per rail, quote amount
  mismatch, 400/422 rejection, 5xx/timeout → unknown, missing
  `transReference` → rejection); lookup (no reference, transId mismatch,
  `senderUniqueId` mismatch, happy path); reference derivation.
- `tests/Unit/Drivers/PayCrossBorder/{Crypto,Bank,Wallet}ReceiverTest.php` —
  required fields, country rules, sanitising, refusing rather than altering
  protected fields.
- `tests/Unit/DataObjects/PayoutBeneficiaryTest.php` — round trip, malformed
  input.
- `tests/Unit/Drivers/PayCrossBorderProviderTest.php` — missing config,
  unsupported methods, `supports()`.
- `tests/Feature/PayCrossBorderPayoutWorkflowTest.php` — through
  `WithdrawalWorkflow`: request → approve → `sendPayout` → `syncPayout` to
  `Paid`; to `Failed` (customer refunded); to `Technical Failure` (`Unknown`,
  no refund); a malformed `details['beneficiary']` refuses the send. Plus a
  regression check that a Heropayment withdrawal without a beneficiary still
  sends.

## Rollout

1. Ship with `withdrawals_enabled = false`.
2. When credentials arrive: confirm the open questions, set `base_url`,
   `password_hash` and the master codes, run one capped payout per rail.
3. Raise `withdrawal_max_amount` once each rail has completed end to end.

## Open questions

To settle when PCB credentials and contact arrive:

- **Password form.** Is the login password issued pre-hashed, or must we
  hash it (and with what)? The example (`21ED0D51…8ED2123B6`) looks like
  uppercase MD5. Covered by `password_hash`.
- **Base URL** for production, and whether a sandbox exists.
- **Error `resultCode` values.** The docs list none; the definite-rejection
  rule (HTTP 400/401/403/422, or `resultCode ≠ "0"` without a reference) is
  our assumption.
- **Short `transactionStatus` codes.** Only `IP` appears; `statusName` is the
  primary signal until the rest are known.
- **Crypto `paymentMode`.** The examples use `Cash`; confirm.
- **Whether `Technical Failure` can mean funds left the wallet.** Until
  answered it maps to `Unknown`.
