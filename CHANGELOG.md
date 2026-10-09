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
