---
name: digiblox
description: Use when working with the Digiblox crypto payment gateway (app.digiblox.io, widget.digiblox.io) — the Digiblox driver, DigibloxClient/DigibloxProvider/DigibloxAdapter/DigibloxTransferService, Digiblox webhooks and the static-header check, or Digiblox hosted payment-link deposits, deposit reconciliation, centralized-transfer withdrawals and transfer status polling. Covers the Crymbo Gateway v3 API that Digiblox white-labels.
---

# Digiblox

Digiblox is a white-label of the **Crymbo** gateway (formerly Finrax) — the
lineage shows in the JWT issuer `Crymbo-API`, in `crymbo.redoc.ly`, and in
`backoffice.digiblox.io` still being titled "Finrax Backoffice".

**Their public documentation is not reachable.** `docs.digiblox.io` returns
Cloudflare error 1014 (their GitBook custom domain is misconfigured) and
`crymbo.redoc.ly` sits behind an Auth0 login. `references/contract.md` is a
consolidated reference built from the PDFs Digiblox supplied directly, and is
currently the only readable copy of the contract we have. Customer and
treasury identifiers from the account exports it was derived from are redacted.

**The driver is built.** It lives in `src/Drivers/Digiblox/`: `DigibloxClient`
(the HTTP surface — cached single-flight JWT, payment links, deposit search,
guest flow), `DigibloxAdapter` (payload assembly, the three status
vocabularies, reconciliation), `DigibloxProvider` (the engine-facing driver),
and `DigibloxTransferService` (withdrawals and status polling). Deposits are
hosted: `charge()` makes one call and returns a widget URL, so there is **no
server-to-server settlement leg** — the webhook is the only authoritative
notice that money arrived. Refunds, capture, authorize and void have no
Digiblox endpoint and throw.

## Before you touch the driver

Six things that will otherwise cost time:

1. **Four status vocabularies describe the same money**, and they are not
   interchangeable: the deposits API reports a *lifecycle*
   (`CONFIRMED`/`REJECTED`), the webhook reports an *amount verdict*
   (`COMPLETED`/`PARTIALLY_PAID`/`OVERPAID`), transfers have their own
   lifecycle, and dashboard exports use a fourth set. A `PARTIALLY_PAID`
   webhook and a `CONFIRMED` deposit describe the same payment. Never compare
   them; there are three separate mappers for this reason.
2. **Reconcile on `total_amount`, never `amount`** — `amount` is net of the
   platform fee, so reconciling on it makes every correct payment look short by
   exactly the fee.
3. **`fromWebhook()` deliberately reports no amount or currency.** The
   webhook's figures are crypto; the transaction is fiat. Reporting them makes
   `WebhookProcessor` flag a currency mismatch and hold every successful
   deposit instead of crediting it.
4. **Deduplicate on `tx_hash`, never `external_transaction_id`** — one payment
   link legitimately takes several payments.
5. **Withdrawals move real money and there is no sandbox.** The transfer
   endpoint has no idempotency key, so a retry past `QUEUED` sends the funds a
   second time — poll `status()` before any retry. `withdrawals_enabled`
   defaults to false behind `withdrawal_max_amount`.
6. **Digiblox signs nothing.** Webhook authenticity is a static header
   registered with them; it is mandatory in production and the endpoint 403s
   without it.

## References

- `references/contract.md` — the full contract: endpoints, payloads, every
  documented error string, the status tables, the webhook delivery rules, and
  a list of the open questions Digiblox has not yet answered.
