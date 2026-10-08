## 2.9.0

- Payouts: Digiblox sends crypto withdrawal payouts (`DigibloxProvider` implements `SendsPayouts`). Configure `payout_assets` per connection: `ticker => ['asset' => 'USDT', 'network' => 'TRON', 'peg' => 'USD']`. Digiblox has no withdrawal webhook — the host must poll `lookup()`.
- **Breaking for custom payout drivers:** `SendsPayouts::lookup()` takes a second argument, `?string $reference` (the stored `payout_reference`). A driver that can only look payouts up by its own id must throw `PayoutOutcomeUnknownException` when it is null.
- Withdrawals: `WithdrawalPayoutOutcomeUnknown` event; `WithdrawalWorkflow::linkPayoutReference()` and `confirmPayoutNotSent()` resolve an unknown payout by hand.
- Withdrawals: `applyPayoutUpdate()` takes an optional `?PayoutState $expectedState`; when given, the receipt is applied only while the locked row is still in that state with no fresh send claim (used by `linkPayoutReference()`).
- Digiblox transfers: `create()` throws `PayoutRejectedException` (4xx) and `PayoutOutcomeUnknownException` (5xx, timeout, unexpected 2xx, 202 without id); `assertSendable()` exposes the local guard.

## 2.8.0

- Withdrawals: `sendPayout()` sends `withdrawal_details.payout_amount` when the host recorded one, so a host can deduct a payout fee from the customer. A present but invalid value refuses the send; it never falls back to the gross. Rows without the key are unchanged.
