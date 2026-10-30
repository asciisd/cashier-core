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
