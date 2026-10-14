# Statuses

Xoala (Paymentz) exposes transaction state in two incompatible shapes at
once: a long, human-readable `status` string, and a short Y/N/P/3D/C code.
Never map one generically to "success/fail" without knowing which shape you
are holding — see `pitfalls.md`.

## Long status → outcome

From the docs' `status.php` page, grouped by the site's own section headers
(`Intermediate` vs `Final`):

| Status | Outcome | Intermediate/Final |
|---|---|---|
| `authsuccessful` | success | Final |
| `capturesuccess` | success | Final |
| `settled` | success | Final |
| `payoutsuccessful` | success (payout) | Final |
| `begun` | pending | Intermediate |
| `authstarted` | pending | Intermediate |
| `cancelstarted` | pending | Intermediate |
| `capturestarted` | pending | Intermediate |
| `markedforreversal` | pending | Intermediate |
| `payoutstarted` | pending (payout) | Intermediate |
| `authfailed` | failed | Final |
| `capturefailed` | failed | Final |
| `failed` | failed | Final |
| `payoutfailed` | failed (payout) | Final |
| `authcancelled` | cancelled | Final |
| `cancelled` | cancelled | Final |
| `reversed` | reversed | Final |
| `chargeback` | chargeback | Final |

This matches the driver's status map exactly for the non-payout rows
(`capturesuccess`/`settled`/`authsuccessful` → success;
`begun`/`authstarted`/`capturestarted`/`cancelstarted`/`markedforreversal` →
pending; `authfailed`/`capturefailed`/`failed` → failed;
`cancelled`/`authcancelled` → cancelled; `reversed`; `chargeback`). The
`payout*` rows are additional and only apply to the Payout flow
(`api-rest.md`), which this driver does not call for deposits.

## Short status (Standard Checkout `status` / callback `transactionStatus`)

| Code | Meaning |
|---|---|
| `Y` | Successfully processed |
| `N` | Failed |
| `P` | Pending |
| `3D` | Pending 3-D Secure authentication |
| `C` | Cancelled |

This is the form the Standard Checkout response-parameter table types
`status` as (`AN2`, `[Y|N|P|3D|C]`), and the form the callback checksum signs
(`standard-checkout.md` rule 3). It is *not* what actually shows up in the
`status` field of a live payload — see `callbacks.md` and `pitfalls.md`.

## Response/result codes

`response-codes.php` and `standard-checkout-response-codes.php` document a
numeric `resultCode`/`result.code` per outcome (e.g. `00001` "Transaction
succeeded", `00004` "Transaction captured successfully", `00005` "Transaction
refunded successfully", `00006` "Transaction cancelled successfully",
`00026` "Your record found successfully", `10001` "Transaction failed").
These are informational; the driver's status mapping is built on the
long/short status strings above, not on this numeric code.
