## 2.8.0

- Withdrawals: `sendPayout()` sends `withdrawal_details.payout_amount` when the host recorded one, so a host can deduct a payout fee from the customer. A present but invalid value refuses the send; it never falls back to the gross. Rows without the key are unchanged.
