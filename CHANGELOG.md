## 3.7.0

- New `TransactionType::IbPayout` (`ib_payout`, label "IB Payout", MT5 prefix `IBP`, a credit). Commission paid to an introducing broker is earned money; hosts were recording it as `Bonus`, which put it among promotional credits in every report. Hosts with an exhaustive `match` on `TransactionType` must add the case.

## 3.6.0

- Repeat payments get a transaction of their own. A Heropayment deposit address is static per customer, so a customer can pay to it again without opening a deposit; Heropayment records that as a new payment and reports it under the last order's `externalOrderId`. Until now the pipeline matched it to the original transaction and dropped every callback as a duplicate or out-of-order update, so the customer was never credited. Production txn 2525 lost 398.50 USDT this way.
- New optional `IdentifiesRepeatPayments` contract: `repeatPaymentId(array $payload, Transaction $original): ?string`. When it returns an id, `WebhookProcessor` finds or creates a transaction under that id by cloning the original's invoice (customer, account, connection, fee terms; a reconciled original is restored to the invoice it was charged at), then settles it through `applyUpdate()` like any deposit. The repeat is measured against the original invoice: its `settlement_expected_payment` is the original's. A repeat that cannot be measured (no recorded expectation, or a converted charge leg) is put `OnHold` instead of credited. Creation is serialized on the original's row lock.
- Heropayment implements it: a callback is a repeat when its `sequence` is not `original`, or when its payment `id` differs from the `heropayment_payment_id` the original recorded. The repeat is tracked as `{externalOrderId}#{paymentId}` (`HeropaymentProvider::REPEAT_SEPARATOR`), and `retrieve()` looks it up by payment id.
- New `RepeatPaymentReceived` event (the new transaction and the original), dispatched after commit.
- `WebhookProcessor::process()` takes an optional repeat resolver and `source`; `applyUpdate()` takes an optional forced hold reason. Existing callers are unaffected.
- New `HeropaymentClient::listPayments()` and `cashier:heropayment-repeats {--connection=} {--since=} {--dry-run}`, which lists every deposit on the account and applies any repeat payment through the same pipeline. Safe to re-run.

## 3.5.0

- Reported PSP fees. A fee configuration can now implement the new optional `UsesReportedFee` contract and return `FeeSource::Reported`: the configured fee is charged at checkout as an estimate and is the ceiling, and on success the deposit is settled against the fee the PSP actually took. Whatever part of the estimate the PSP did not take is credited on top of the deposit (`amount` only ever grows); a fee above the estimate is absorbed, the deposit is credited as quoted and the real fee is recorded. A missing or unusable figure keeps the estimate. Refused with `SettlementMode::Added`. Configurations that do not implement the contract behave exactly as before.
- Drivers report the fee through two standard metadata keys, `settlement_reported_fee` and `settlement_reported_fee_currency`. A fee in the account currency is used as is; one in the charge currency is converted by the charge leg's own ratio (`requested_amount / charge_amount`); any other currency, or none, is ignored. MyFatoorah reports `ServiceCharge` in the account's `BaseCurrency`.
- `FeeBreakdown` carries `feeSource` and `feeIsEstimate()`; `toArray()` adds `fee_source` and `fee_is_estimate`, so a quote can show the fee as a maximum.
- New transaction columns `fee_source` and `estimated_psp_fee_amount` (migration `2026_11_03_000001`). `psp_fee_amount` is rewritten to the reported fee on settlement and the estimate is kept beside it. Hosts that own their transactions table must add both columns.

## 3.4.0

- Xoala prefills the hosted checkout. The checkout bridge now resolves the deposit's customer (`cashier-core.models.customer`, by the transaction's `user_id`) and, when it implements `ProvidesBillingDetails`, sends `firstName`, `lastName`, `email`, `telnocc`, `phone`, `country`, `street`, `city`, `state`, `postcode` and `dateOfBirth`, plus the browser's IPv4 as `ip`. The details are read when the page renders and are never written to the transaction's metadata. Every value is normalised to Xoala's field pattern or dropped; none is invented. A customer without billing details still gets its name and email. Turn it off with `send_customer_details => false` on the connection.
- `ProvidesBillingDetails` documents an optional `phone_country_code` key (the calling code `phone` starts with). Xoala uses it to split `telnocc` from the number; without it the full number is sent as `phone`.
- `XoalaProvider::checkoutForm()` takes optional `?CustomerContract $customer` and `?string $ip` arguments. Existing callers are unaffected.

## 3.3.1

- Xoala: new optional `partner_id` connection setting, sent as `authentication.partnerId` when generating the REST auth token. An account boarded through a partner is refused a token without it ("Invalid partner ID OR partner ID is misconfigured", code 30055), so every `retrieve()` / sync failed and the deposit was logged as not found at the provider. Left off the request when unset.

## 3.3.0

- Xoala implements `ResolvesReturnOutcome`. Xoala returns the customer to `merchantRedirectUrl` with a form POST carrying the short status under `status` and the callback checksum; pass the POST body to `returnOutcome()`. `Y` is `Succeeded`, `N` and `C` are `Failed`, anything else is `Unknown`, and so is any body whose checksum the connection's secure key did not produce.
- Xoala: the checkout bridge (`cashier.checkout.xoala`) no longer 404s on hosts whose transaction model carries an owner scope. The route runs without a session, so the scope hid every row; the lookup now drops `cashierBypassedScopes()`, as the webhook path does. The signature and the Pending check still guard the page.

## 3.2.0

- New `ResolvesReturnOutcome` contract and `ReturnOutcome` enum (`Succeeded`, `Failed`, `Unknown`) for providers that send every outcome to one return URL. `returnOutcome(array $query)` is read-only and never throws.
- MyFatoorah implements it: the `paymentId` (or `Id`) MyFatoorah appends to the redirect is looked up with the new `MyfatoorahClient::getPayment()` (`GET /v3/payments/{id}`). A PAID invoice is `Succeeded`, a FAILED or CANCELED attempt is `Failed`, anything else (in progress, lookup failure, no id) is `Unknown`. Before this, a host pointing `redirect_url` at its success page showed success for a cancelled KNET payment.

## 3.1.1

- Payouts: `PayoutRequest::$customerId` now comes from `withdrawal_details.customer_id` when the host stores one, before `metadata.ledger_account` and the user id. A host that debits the funds system itself (and so never sets `ledger_account`) can now send its own customer identifier, such as the MT5 login, matching what its deposits send.

## 3.1.0

- APS: signed-request auth. A connection with `private_key` (ES256 / P-256 PEM — contents, `\n`-escaped env value, or a file path) signs every request with `X-App-Token`, `X-Sign-Timestamp` and `X-Signature`, APS's current scheme, and sends no `X-App-Secret`. `app_secret` is now optional: connections that set only it keep the deprecated header auth unchanged, and the provider requires one of the two. New APS accounts are issued no app secret, so they need `private_key`.
- APS: callback verification now rejects every callback when neither `callback_secret` nor `app_secret` is set, instead of checking an HMAC under an empty key.

## 3.0.0

- **Breaking:** Paytiko support is removed from the core. The Paytiko-specific `PaymentLogger` methods are gone: `paytikoTransactionNotFoundForSuccess`, `paytikoTransactionConfirmedSuccessful`, `noTradingAccountForPaytikoTransfer`, `paytikoFundsTransferred`, `paytikoFundsTransferFailed`, `paytikoFundsTransferException`, `paytikoTransactionNotFoundForFailure`, `paytikoTransactionUpdatedToFailed`, `paytikoProviderCreationFailed`, `webhookTransactionNotFound`, `transactionUpdatedFromWebhook`, `paymentMethodExtractionFailed`, `novaPaytikoResyncInitiated`, `novaPaytikoResyncFailed`, `rawPayloadTransactionNotFound` and `rawPayloadPersisted`. The `asciisd/cashier-paytiko` plugin is not supported on 3.x; stay on 2.x if you depend on it.
- Docs, config comments and examples no longer reference Paytiko.

## 2.10.0

- Payouts: new `paycrossborder` driver (PayCrossBorder, docs.paycrossborder.com), payouts only, on three rails chosen per withdrawal: crypto (wallet address + `payout_extra_id` network), bank and mobile wallet. Every payout is B2C from the business registered with PCB (`customer_code`), quoted by the exact amount sent (`payinAmount`). Amounts with more than 2 decimal places are refused, and a quote that differs from the requested amount by any fraction is refused before anything is sent. PCB sends no callbacks: the host polls `syncPayout()`, and `lookup()` needs the stored `payout_reference` (PCB's `transId`). `Technical Failure` maps to `Unknown`, not `Failed`. Ships disabled: set `withdrawals_enabled` and `withdrawal_max_amount`.
- Payouts: `PayoutRequest` has an optional `?PayoutBeneficiary $beneficiary` (with `BeneficiaryAddress`, `BeneficiaryBank`, `BeneficiaryWallet`), populated from `withdrawal_details.beneficiary`. Absent is `null` and changes nothing for other drivers; a malformed beneficiary now refuses the send for every driver.
- PayCrossBorder: on the bank and wallet rails `payout_address` must equal the beneficiary's account number or wallet phone, and any payout response that carries a PCB transaction reference is treated as possibly sent (Unknown), never as rejected; the reference is kept in the error so it can be linked.
- PayCrossBorder open items before going live: whether the login password is issued pre-hashed (`password_hash`), whether the token call needs OAuth client credentials (`oauth_client_id`), and whether enquiry `senderUniqueId` equals our `sendClientTrxReference` (lookup relies on it).

## 2.9.1

- Heropayment: deposit callbacks now reconcile against what actually arrived. The adapter read `actuallyPaid`, a key Heropayment never sends; it reports the amount as `paidAmount`. With the key missing, `settlement_actually_paid` was never set, so an underpaid crypto deposit was credited the full invoice. `actuallyPaid` remains a fallback.
- Heropayment: callbacks now report `settlement_reported_amount` (`merchantAmountUsdt`), so `FeeDriftDetected` fires for this driver.
- Note: shortfalls inside `settlement.tolerance_percent` (default 2%) are still credited in full. A host that must never fund an underpayment sets it to 0.

## 2.9.0

- Payouts: Digiblox sends crypto withdrawal payouts (`DigibloxProvider` implements `SendsPayouts`). Configure `payout_assets` per connection: `ticker => ['asset' => 'USDT', 'network' => 'TRON', 'peg' => 'USD']`. Digiblox has no withdrawal webhook — the host must poll `lookup()` passing the stored `payout_reference` (or use `WithdrawalWorkflow::syncPayout()`). Payouts also need the connection keys `withdrawals_enabled` (default false) and `withdrawal_max_amount` (required cap).
- **Breaking for custom payout drivers:** `SendsPayouts::lookup()` takes a second argument, `?string $reference` (the stored `payout_reference`). A driver that can only look payouts up by its own id must throw `PayoutOutcomeUnknownException` when it is null.
- Withdrawals: `WithdrawalPayoutOutcomeUnknown` event; `WithdrawalWorkflow::linkPayoutReference()` and `confirmPayoutNotSent()` resolve an unknown payout by hand.
- Withdrawals: `applyPayoutUpdate()` takes an optional `?PayoutState $expectedState`; when given, the receipt is applied only while the locked row is still in that state with no fresh send claim (used by `linkPayoutReference()`).
- Digiblox transfers: `create()` throws `PayoutRejectedException` (4xx) and `PayoutOutcomeUnknownException` (5xx, timeout, unexpected 2xx, 202 without id); `assertSendable()` exposes the local guard.
- Digiblox transfers: only HTTP 400, 401, 403 and 422 on create are `PayoutRejectedException`; every other 4xx (409, 429 …) is `PayoutOutcomeUnknownException`. A 401 on create or status clears the cached token.
- Digiblox lookup: an unreadable status, or a returned transfer id that differs from the requested one, throws `PayoutOutcomeUnknownException`; linking also requires `user_note` to be present and equal to the order id.
- Withdrawals: `linkPayoutReference()` stores the PSP's own reference and refuses (writing nothing) when it differs from the one typed; lookup failure messages now reach the admin from `sendPayout()`, `syncPayout()` and `linkPayoutReference()`.

## 2.8.0

- Withdrawals: `sendPayout()` sends `withdrawal_details.payout_amount` when the host recorded one, so a host can deduct a payout fee from the customer. A present but invalid value refuses the send; it never falls back to the gross. Rows without the key are unchanged.
