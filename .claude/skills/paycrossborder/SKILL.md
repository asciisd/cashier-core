---
name: paycrossborder
description: Use when working with the PayCrossBorder payout gateway (docs.paycrossborder.com) — the PayCrossBorder driver, PayCrossBorderClient/PayCrossBorderProvider/PayCrossBorderPayoutService/PayCrossBorderAdapter, the Crypto/Bank/Wallet receiver builders, PayoutBeneficiary, or PCB login, quotation, payout, transaction enquiry and balance calls. Covers the FI-customer payout API (crypto, FIAT and WPT rails).
---

# PayCrossBorder

PayCrossBorder (PCB) is a cross-border payout network. Its API is for
**Financial Institution customers** and is **payouts only**: no collections,
no refunds, **no callbacks**. Docs: https://docs.paycrossborder.com/#/
(docsify; raw pages at `https://docs.paycrossborder.com/<PAGE>.md`).

## Driver map

| File | Role |
|---|---|
| `src/Drivers/PayCrossBorder/PayCrossBorderProvider.php` | Connection entry. Deposit side unsupported. |
| `PayCrossBorderClient.php` | Login (OAuth password grant, cached token), quote, payout, enquiry, balance. |
| `PayCrossBorderPayoutService.php` | Guard → balance preflight → fresh quote → payout → lookup. |
| `Rail.php` + `Receivers/*` | Rail choice and the per-rail `receiver` block. |
| `PayCrossBorderAdapter.php` | `statusName` → `PayoutState`. |
| `src/DataObjects/PayoutBeneficiary.php` | Payee identity from `withdrawal_details.beneficiary`. |

Design: `docs/superpowers/specs/2026-10-12-paycrossborder-payouts-design.md`.
API details and quirks: `references/api.md`.

## Rules that are easy to break

- `payout()` is never retried — no idempotency key.
- `lookup()` never returns null; without PCB's `transId` it throws.
- Success is HTTP 2xx **and** `resultCode === "0"`.
- `Technical Failure` → `Unknown`, never `Failed`.
- Names, account numbers, wallet addresses, phones, ID numbers: refuse, never alter. Only address text is sanitised.
- PCB field names have typos that are load-bearing: `receiverAddresssCity`, `businessAddresssCity`.
- The payout always sends our own amount as `payinAmount`; the quote must equal it exactly, and amounts with more than 2 decimals are refused.
