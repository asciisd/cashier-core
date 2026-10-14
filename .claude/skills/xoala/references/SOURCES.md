# Sources

Xoala is a white-label of the Paymentz platform. Its documentation site,
`checkout-docs.xoala.com`, renders every "Sample Request" / "Sample Response"
block from JavaScript at view time — a plain `curl` of a doc page returns
full prose with empty sample blocks — and the site's own brand-name
interpolation is blank throughout ("Merchant ID as shared by ."), so neither
the samples nor a clean brand identity are recoverable from the page source
alone. Two things had to be fetched separately from the prose pages.

## Prose pages

Fetched 2026-09-09 with:

```bash
for p in overview standard-checkout standard-checkout-specifications \
         rest-api-specifications transaction-AuthToken backoffice \
         asynchronous-workflow synchronous-workflow status \
         response-codes standard-checkout-response-codes payout; do
  curl -sL "https://checkout-docs.xoala.com/integration/$p.php" -o "$p.html"
done
```

All twelve returned HTTP 200 with substantial (34–155 KB) HTML.

| Page | Used in |
|---|---|
| `overview.php` | REST API security/auth model, `api-rest.md` |
| `standard-checkout.php` | Workflow narrative, the four checksum rules, the worked callback example, `standard-checkout.md` |
| `standard-checkout-specifications.php` | Request/response parameter tables, `standard-checkout.md` |
| `rest-api-specifications.php` | REST auth model, `api-rest.md` |
| `transaction-AuthToken.php` | `authToken` / `partnerAuthToken` endpoints, 1-hour validity, `api-rest.md` |
| `backoffice.php` | `CP`/`RF`/`RV`/`IN` endpoints and checksum formulas, `api-rest.md` |
| `asynchronous-workflow.php` | Async payment flow, redirect-and-poll mechanics, `api-rest.md` |
| `synchronous-workflow.php` | Sync payment flow and its checksum, `api-rest.md` |
| `status.php` | The long status table, `statuses.md` |
| `response-codes.php` | Numeric result codes, `statuses.md` |
| `standard-checkout-response-codes.php` | Numeric result codes (Standard Checkout variant), `statuses.md` |
| `payout.php` | Payout endpoint and checksum, `api-rest.md` |

Pages fetched as HTML and read with tags stripped; no page was hand-edited
into a reference file — every quoted sentence in `standard-checkout.md`,
`api-rest.md`, `statuses.md` and `pitfalls.md` traces back to one of these.

## Sample payloads

The doc site's samples are served from a JSON endpoint on the underlying
platform's own sandbox host, keyed by flow name (`VISA_CC`, `IN`, `CP`,
`RF`, `RV`, `PO`, `AUTH_TOKEN`, `GENERATE`, `StandardCheckout`, `NOTI_CARD`,
`FAIL_NOTI_CARD`, `NOTI_BANKTRANS`, and many more flows this driver does not
use):

```bash
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/sampleRequest
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/sampleResponse
```

Both returned HTTP 200 (~180 KB each) — large dictionaries of
`{flow: {parameters, workflow, host}}`. `parameters` in a *request* sample is
itself a JSON-encoded string (double-encoded); `parameters` in a *response*
sample is a plain nested object. Every JSON snippet quoted in
`standard-checkout.md`, `api-rest.md`, and `callbacks.md` was extracted
directly from one of these two payloads, keyed exactly as shown.

Flows this driver's ground truth relies on, and their presence:

| Flow | In `sampleRequest` | In `sampleResponse` |
|---|---|---|
| `VISA_CC` | yes | no |
| `IN` | yes | yes |
| `CP` | yes | yes |
| `RF` | yes | yes |
| `RV` | yes | yes |
| `PO` | yes | yes |
| `AUTH_TOKEN` | yes | yes |
| `StandardCheckout` | no | yes |
| `NOTI_CARD` | no | yes |
| `FAIL_NOTI_CARD` | no | yes |
| `NOTI_BANKTRANS` | no | yes |

## The brand's own host record

```bash
curl -s https://sandbox.paymentplug.com/transactionServices/REST/v2/getProperty/checkout-docs.xoala.com
```

Returned HTTP 200, a small (~1.2 KB) JSON object of white-label theming and
routing properties for the `checkout-docs.xoala.com` brand, including:

```json
{
  "company": "Xoala",
  "companyId": "xl",
  "preprod_url": "https://secure-checkout-sandbox.xoala.com/",
  "live_url": "https://secure-checkout.xoala.com/",
  "support_email": "support@xoala.com"
}
```

This is where the sandbox/live hosts documented in `config/cashier-core.php`
and `standard-checkout.md` are confirmed from — the prose pages themselves
render the host fields blank client-side.

## What is not mirrored

The Invoice API pages (`GENERATE`/`CANCEL`/`REGENERATE`/... under
`invoice/REST/v1/*`) and everything QR-, mobile-SDK- and marketplace-related
were left unfetched: they surfaced only as neighboring nav-menu links and
sample-payload keys, and this driver does not call them. `api-rest.md` notes
the Invoice API's existence only because some sample-payload keys are tagged
`workflow: "invoice"`.

## Regenerating

There is no index or llms.txt on this site to diff against, and no
changelog. A refresh means re-running the two curl blocks above and reading
`git diff` on the reference files — that diff is the only changelog this
integration gets. `pitfalls.md` is hand-written against this fetch and is not
regenerated automatically; when a diff touches something an entry asserts,
re-check that entry.
