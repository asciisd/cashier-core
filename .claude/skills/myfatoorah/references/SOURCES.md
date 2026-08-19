# Sources

Vendored from the MyFatoorah documentation site, which runs ReadMe. Do not
hand-edit any file except `pitfalls.md` and this one — everything else is
overwritten on regeneration.

| File | Contents | Pages | Fetched |
|---|---|---|---|
| `intro.md` | accounts, API keys and hosts, test/live tokens, test cards, payment-method names, ISO lookups, Postman | 12 | 2026-08-19 |
| `payment-flows.md` | embedded, hosted page, invoicing, direct/PCI, and every wallet — Apple, Google, Samsung, STC | 36 | 2026-08-19 |
| `features.md` | payment status, tokenization, refunds, auth & capture, recurring, MIT, reporting, response model, idempotency | 38 | 2026-08-19 |
| `webhooks.md` | V1 and V2 mechanics, the signature recipe, and all thirteen data models — six V1, seven V2 | 19 | 2026-08-19 |
| `api-v3.md` | OpenAPI for `/v3/payments`, `/v3/sessions`, `/v3/invoices`, `/v3/customers` | 8 | 2026-08-19 |
| `api-v2.md` | OpenAPI for the `/v2/*` payment, refund, list, report and webhook endpoints | 19 | 2026-08-19 |
| `suppliers.md` | multi-vendor guides, incl. the KYC rejection-reason table | 15 | 2026-08-19 |
| `api-suppliers.md` | OpenAPI for the supplier endpoints | 11 | 2026-08-19 |
| `shipping.md` | DHL/ARAMEX shipping module guides | 9 | 2026-08-19 |
| `api-shipping.md` | OpenAPI for the shipping endpoints | 6 | 2026-08-19 |
| `toolkit.md` | Postman and the official PHP library | 2 | 2026-08-19 |
| `mobile-sdk.md` | iOS, Android, Flutter, React Native, Cordova | 7 | 2026-08-19 |
| `plugins.md` | 37 e-commerce plugin guides (WooCommerce, Magento, Shopify, …) | 37 | 2026-08-19 |

219 pages in total. Human-readable site: <https://docs.myfatoorah.com>.

## Why this mirror is not just llms.txt

ReadMe serves every page as markdown at `<page-url>.md` and indexes the site at
<https://docs.myfatoorah.com/llms.txt>, which is what makes a clean mirror
cheap — no pandoc, no HTML scraping, standard library only. Two things the
index does not give you:

**It is incomplete.** It lists 167 pages. 52 more are live, linked from listed
pages, and hold content that appears nowhere else — Webhook V1 and its four V1
data models, every Google Pay and STC Pay guide, the KYC rejection-reason
table, and the V2-era original of most `v3-` pages. Each was fetched and
compared against the indexed corpus; none is a redirect or a duplicate. They
are named in the generator's `UNLISTED` and fetched directly. See
`pitfalls.md` entry 13.

**Nothing announces a change.** There is no changelog and no API versioning
within a generation; a page's `updatedAt`, which the mirror records under each
heading, is the only signal. The generator's `drift()` check closes half the
gap: it scans every internal link in the output and fails the run on a link to
a page nothing placed, which is how the second batch of 16 unlisted pages was
found. The other half is reading `git diff` after each refresh.

## What is mirrored, and what is not

Everything the site publishes under `/docs` and `/reference` is here, including
the areas this package will never call — `suppliers.md`, `shipping.md`,
`mobile-sdk.md` and `plugins.md`. They are grouped into their own files rather
than dropped, so that a refresh diff stays complete and so `features.md` is not
half multi-vendor material. `SKILL.md` says which files to open.

Two internal links resolve to nothing and sit in the generator's
`KNOWN_BROKEN`, so the drift check does not flag them every run:
`get-countriesthe` (typo for `get-countries`, in the shipping guide) and
`execute-paymentt` (typo for `execute-payment`, in the V2 gateway guide).

Images are left as links to `files.readme.io`. Several are flow diagrams whose
content is not in the surrounding prose, so a mirror read offline is missing
them; none of the facts in `pitfalls.md` rests on one.

## Two sources outside the docs site

`pitfalls.md` leans on both. Re-check them when refreshing.

**The official PHP library** — `myfatoorah/library`, 2.2.10 at the time of
writing. Packagist publishes no dist tarball, so read the git source:

    git clone --depth 1 https://dev.azure.com/myfatoorahsc/Public-Repo/_git/Library

It is the only place several live behaviours are written down at all: the
`Succss` status, the webhook-version header, the V1-vs-V2 signature ordering,
and the five error shapes. What it does is what MyFatoorah's own plugins do.

**The country configuration** — a public JSON file the library reads at
runtime, richer than the host table in `intro.md`:

    curl https://portal.myfatoorah.com/Files/API/mf-config.json

Per country: the `v1`, `v2`, `testv1`, `testv2`, `portal` and `testPortal`
hosts, plus the `timeZone` that invoice expiry is expressed in.

## Regenerating

Save the script below to `/tmp/mf-mirror.py`, then run it from anywhere — it
locates the references directory itself via `git rev-parse --show-toplevel`:

    python3 /tmp/mf-mirror.py

It fetches 219 pages and rewrites all thirteen mirror files. `pitfalls.md` and
this file are never touched: the script exits rather than run if `LAYOUT` would
name one of them.

`git diff` on the result is the changelog MyFatoorah does not publish. Read it
before committing — a changed field name, status value or signature field list
is exactly what this mirror exists to surface. When a diff touches something
`pitfalls.md` asserts, re-check that entry before trusting it again.

````python
#!/usr/bin/env python3
"""Mirror the MyFatoorah documentation to markdown references.

MyFatoorah runs ReadMe, which serves every page as markdown at `<page-url>.md`
and indexes the site at /llms.txt. Each /reference page embeds its own OpenAPI
3.0 fragment, so the mirror carries the machine-readable contract as well as
the prose.

Two things the index does not do, which this script compensates for:

  * It omits 52 live pages (UNLISTED below) — among them Webhook V1, the
    gateway rejection-reason table, and every Google Pay and STC Pay guide.
    They are fetched by name instead.
  * Nothing announces a new page. Every internal link in the generated corpus
    is therefore checked against what was placed, and an unrecognised one
    fails the run.

Writes into .claude/skills/myfatoorah/references, located via
`git rev-parse --show-toplevel` so the script runs from anywhere. Python 3
standard library only — the source is already markdown, so no pandoc.
"""
import os
import re
import subprocess
import sys
import textwrap
import urllib.error
import urllib.request

INDEX = "https://docs.myfatoorah.com/llms.txt"
SITE = "https://docs.myfatoorah.com"

# ReadMe injects this line into every .md page. It is chrome, not contract, and
# 200 copies of it would be 200 lines of noise in the mirror.
CHROME = re.compile(
    r"^Fetch the complete documentation index at: .*?before exploring further\.$\n?",
    re.M,
)

# Live /docs pages that llms.txt does not list. Every one is linked from a page
# that *is* listed, and none is a redirect — each was compared against the
# indexed corpus and holds unique content. Most are the V2-era originals of
# pages since rewritten under a `v3-` slug; that V2 text is still the only
# description of the older flow, which merchants integrated years ago are
# still running.
UNLISTED = [
    "apple-pay", "apple-pay-domain-verification", "apple-pay-native",
    "authorization-capture", "card-view-form",
    "canceltoken", "deposit-data-model", "direct-payment-endpoint",
    "direct-payment-sample-code", "dispute-data-model",
    "embedded-integration-steps", "embedded-payment",
    "embedded-payment-sample-code", "flutter-migration", "gateway-integration",
    "gateway-integration-sample-code", "getbanks", "google-pay-embedded",
    "google-pay-native", "invoice-link", "invoice-link-sample-code",
    "live-token",
    "otp-page-in-an-iframe", "payment-inquiry", "payment-methods", "postman",
    "recurring-data-model", "recurring-payment-embedded",
    "recurring-payment-redirection", "refund-data-model", "rejection-reasons",
    "saving-card-embedded-payment", "saving-card-myfatoorah-page",
    "saving-card-options", "stcpay", "supplier-data-model",
    "supplier-update-request-data-model", "technical-guide-overview",
    "test-token", "tokenization", "tokenized-embedded",
    "tokenized-embedded-payments", "unified-sample-code",
    "unified-session-customization", "update-payment-status-guidelines",
    "updatepaymentstatus", "v3-apple-pay-native", "v3-google-pay-native",
    "v3-native-wallets", "v3-samsung-pay-native", "vendor-managed-recurring",
    "webhook-v1",
]

# Internal links that resolve to nothing. Listed so the drift check can tell
# "MyFatoorah published a page we are not mirroring" from "MyFatoorah has a
# typo in a link", and fail only on the first.
KNOWN_BROKEN = [
    "get-countriesthe",  # typo for get-countries, in the shipping guide
    "execute-paymentt",  # typo for execute-payment, in the V2 gateway guide
]

# Output files, in the order SKILL.md lists them: (filename, title, [slugs]).
# The "docs" and "reference" namespaces are flattened here; ALIASES below
# disambiguates the slugs that exist in both.
#
# Grouping follows the site's own sidebar with one deliberate change: the
# sidebar's "Features" category mixes the payment surface with the multi-vendor
# and shipping modules, which have nothing to do with taking a payment. Those
# are split out so features.md stays about payments.
LAYOUT = [
    ("intro.md", "Introduction", [
        "get-started", "technical-guide-overview", "live-account",
        "account-information", "orders-information", "api-key", "test-token",
        "live-token", "test-cards", "payment-methods", "iso-lookups", "postman",
    ]),
    ("payment-flows.md", "Payment flows", [
        "choose-your-payment-integration",
        "embedded-payment-v3", "embedded-integration-steps",
        "customizing-embedded-payment", "unified-session-customization",
        "v3-token-payments", "tokenized-embedded",
        "tokenized-embedded-payments",
        "embedded-payment-sample-code-1", "unified-sample-code",
        "embedded-payment", "embedded-payment-sample-code", "card-view-form",
        "v3-hosted-payment-page", "gateway-integration",
        "gateway-integration-sample-code",
        "v3-invoicing", "invoice-link", "invoice-link-sample-code",
        "v3-direct-payment", "direct-payment-endpoint",
        "direct-payment-sample-code", "card-direct-integration",
        "otp-page-in-an-iframe",
        "v3-native-wallets",
        "v3-apple-pay-direct-integration", "v3-apple-pay-native", "apple-pay",
        "apple-pay-native", "apple-pay-domain-verification",
        "v3-google-pay-native", "google-pay-embedded", "google-pay-native",
        "v3-samsung-pay-direct-integration", "v3-samsung-pay-native",
        "stcpay",
    ]),
    ("features.md", "Features", [
        "features",
        "get-payment-details", "payment-inquiry",
        "v3-updating-payment-status-guidelines",
        "update-payment-status-guidelines", "updatepaymentstatus",
        "v3-saving-card-options", "saving-card-options", "tokenization",
        "v3-saving-card-embedded-payment", "saving-card-embedded-payment",
        "saving-card-myfatoorah-page", "v3-direct-tokenization", "kfast",
        "canceltoken",
        "refund", "make-refund", "getrefundstatus", "refund-sample-code",
        "v3-auth-capture", "authorization-capture", "card-verification",
        "recurring-payment", "recurring-payment-embedded",
        "recurring-payment-redirection", "getrecurringpayment",
        "cancelrecurringpayment", "resumerecurringpayment",
        "recurring-payment-sample-code", "v3-vendor-managed-recurring",
        "vendor-managed-recurring",
        "merchant-initiated-transaction", "bypass3ds",
        "reporting", "getinvoicesbydepositreference", "getbanks",
        "response-model", "idempotency",
    ]),
    ("webhooks.md", "Webhooks", [
        "webhook", "webhook-information", "webhook-signature",
        # V1 and its six data models, then V2 and its seven. The unprefixed
        # slugs are the V1 models — they key off `EventType`, where the
        # `webhook-v2-` ones key off `Event.Code`.
        "webhook-v1",
        "refund-data-model", "deposit-data-model", "supplier-data-model",
        "recurring-data-model", "dispute-data-model",
        "supplier-update-request-data-model",
        "webhook-v2",
        "webhook-v2-payment-status-data-model", "webhook-v2-refund-data-model",
        "webhook-v2-balance-transferred-data-model",
        "webhook-v2-supplier-data-model", "webhook-v2-recurring-data-model",
        "webhook-v2-dispute-data-model",
        "webhook-v2-supplier-update-request-data-model",
        "getwebhooks",
    ]),
    ("api-v3.md", "API reference — V3", [
        "create-payment", "get-payment-details-v3", "update-payment",
        "create-session", "get-session-details", "get-invoice-by-invoiceid",
        "get-invoice-by-externalidentifier", "get-customer-details",
    ]),
    ("api-v2.md", "API reference — V2", [
        "send-payment", "initiate-payment", "initiate-session",
        "execute-payment", "get-payment-status", "update-payment-status",
        "update-session", "cancel-token", "direct-payment",
        "get-recurring-payment", "cancel-recurring-payment",
        "resume-recurring-payment", "register-apple-pay-domain",
        "make-refund-v2", "get-refund-status", "get-banks",
        "get-currencies-exchange-list", "get-deposited-invoices",
        "get-webhooks",
    ]),
    ("suppliers.md", "Multi-vendor (suppliers)", [
        "multiple-suppliers", "supplier-information", "rejection-reasons",
        "create-supplier",
        "edit-supplier", "customizesuppliercommissions", "amount-distribution",
        "get-suppliers", "getsupplierdetails", "get-supplier-deposits",
        "get-supplier-documents", "get-supplier-dashboard",
        "upload-supplier-document", "make-supplier-refund", "transferbalance",
    ]),
    ("api-suppliers.md", "API reference — suppliers", [
        "create-supplier-v2", "edit-supplier-v2",
        "customize-supplier-commissions", "transfer-balance",
        "upload-supplier-document-v2", "get-suppliers-v2",
        "get-supplier-details", "get-supplier-deposits-v2",
        "get-supplier-documents-v2", "get-supplier-dashboard-v2",
        "make-supplier-refund-v2",
    ]),
    ("shipping.md", "Shipping module", [
        "shipping", "shipping-information", "get-countries", "get-cities",
        "calculate-shipping-charge", "update-shipping-status",
        "request-pickup", "get-shipping-order-list", "shipping-sample-code",
    ]),
    ("api-shipping.md", "API reference — shipping", [
        "get-countries-v2", "get-cities-v2", "request-pickup-v2",
        "get-shipping-order-list-v2", "calculate-shipping-charge-v2",
        "update-shipping-status-v2",
    ]),
    ("toolkit.md", "Toolkit", ["toolkit-overview", "php-library"]),
    ("mobile-sdk.md", "Mobile SDKs", [
        "sdk-overview", "sdk-guide", "android-sdk", "flutter",
        "flutter-migration", "react-native", "cordova",
    ]),
    # Everything not claimed above. The plugin catalogue grows without notice,
    # so it is a catch-all rather than a list: a new plugin lands here instead
    # of failing the run.
    ("plugins.md", "E-commerce plugins", None),
]

# Slugs that exist in both the /docs and /reference namespaces. LAYOUT is one
# flat namespace, so the /reference member of each pair carries a suffix here.
ALIASES = {
    "get-payment-details-v3": ("reference", "get-payment-details"),
    "make-refund-v2": ("reference", "make-refund"),
    "create-supplier-v2": ("reference", "create-supplier"),
    "edit-supplier-v2": ("reference", "edit-supplier"),
    "upload-supplier-document-v2": ("reference", "upload-supplier-document"),
    "get-suppliers-v2": ("reference", "get-suppliers"),
    "get-supplier-deposits-v2": ("reference", "get-supplier-deposits"),
    "get-supplier-documents-v2": ("reference", "get-supplier-documents"),
    "get-supplier-dashboard-v2": ("reference", "get-supplier-dashboard"),
    "make-supplier-refund-v2": ("reference", "make-supplier-refund"),
    "get-countries-v2": ("reference", "get-countries"),
    "get-cities-v2": ("reference", "get-cities"),
    "request-pickup-v2": ("reference", "request-pickup"),
    "get-shipping-order-list-v2": ("reference", "get-shipping-order-list"),
    "calculate-shipping-charge-v2": ("reference", "calculate-shipping-charge"),
    "update-shipping-status-v2": ("reference", "update-shipping-status"),
}

# Hand-authored; this script must never write them.
PRESERVED = {"SOURCES.md", "pitfalls.md"}


# ---------------------------------------------------------------------------
# MDX normalisation
#
# ReadMe's markdown export is mostly clean, but several MDX components survive
# it, and three of them carry contract. <Table> holds every parameter table
# (600+ <td> elements site-wide, each on its own line — the same data as a pipe
# table at a tenth of the size), <Callout> holds the "contact your account
# manager" warnings, and <Image> holds the flow diagrams. Normalising them
# keeps the mirror readable, and keeps a page that switches between MDX and
# markdown callouts — the source already does both — from reading as a rewrite
# in the diff.
#
# Code fences are left alone: the sample code contains <script>, <html> and
# <div>, none of which is MDX.
# ---------------------------------------------------------------------------

CELL = re.compile(r"<t[hd]([^>]*)>(.*?)</t[hd]>", re.S)
ROW = re.compile(r"<tr[^>]*>(.*?)</tr>", re.S)
MDX_COMMENT = re.compile(r"\{/\*.*?\*/\}", re.S)
SPAN = r'{}="(\d+)"'


def cell(text):
    """<td> inner markdown -> one pipe-table cell."""
    text = re.sub(r"<br\s*/?>", " ", text)
    text = re.sub(r"</?(?:b|strong)>", "**", text)
    text = re.sub(r'<a [^>]*href="([^"]*)"[^>]*>(.*?)</a>', r"[\2](\1)", text,
                  flags=re.S)
    # A cell may hold a list or several paragraphs; a pipe table row is one
    # line, so collapse. An unescaped pipe would end the cell early.
    return " ".join(text.split()).replace("|", r"\|")


def table(match):
    """<Table> or <table> -> GitHub pipe table.

    rowspan and colspan are expanded into repeated cells: the test-card table
    leans on rowspan for its card-type column, and dropping the spans silently
    shifts every card number one column to the left.
    """
    body = MDX_COMMENT.sub("", match.group(1))
    rows = []
    carry = {}  # column index -> [rows still to fill, text]

    for source in ROW.findall(body):
        pending, out, col = CELL.findall(source), [], 0
        while pending or any(c >= col and v[0] > 0 for c, v in carry.items()):
            if carry.get(col, (0,))[0] > 0:
                out.append(carry[col][1])
                carry[col][0] -= 1
                col += 1
                continue
            if not pending:
                break
            attrs, text = pending.pop(0)
            text = cell(text)
            down = int((re.search(SPAN.format("rowspan"), attrs) or [0, 1])[1])
            across = int((re.search(SPAN.format("colspan"), attrs) or [0, 1])[1])
            # A colspan cell keeps its text in the first column it covers and
            # leaves the rest blank; repeating it reads as duplicated data.
            for step in range(across):
                value = text if step == 0 else ""
                out.append(value)
                if down > 1:
                    carry[col] = [down - 1, value]
                col += 1
        if out:
            rows.append(out)

    if not rows:
        return ""
    width = max(len(row) for row in rows)
    head, *rest = rows

    def line(cells):
        return "| " + " | ".join(cells + [""] * (width - len(cells))) + " |"

    return "\n".join([line(head), "|" + "---|" * width]
                     + [line(row) for row in rest])


def callout(match):
    """<Callout icon="🚧"> -> blockquote, matching the markdown callouts the
    same docs use elsewhere."""
    icon = (re.search(r'icon="([^"]*)"', match.group(1)) or [None, ""])[1]
    body = textwrap.dedent(match.group(2)).strip()
    # A heading inside a blockquote renders oddly and would outrank the page's
    # own headings in an outline; callout titles become bold instead.
    body = re.sub(r"^#{1,6}\s+(.*)$", r"**\1**", body, flags=re.M)
    lines = body.splitlines() or [""]
    if icon:
        lines[0] = f"{icon} {lines[0]}".strip()
    return "\n".join(("> " + line).rstrip() for line in lines)


def image(match):
    attrs, inner = match.group(1), (match.group(2) or "")
    src = (re.search(r'src="([^"]*)"', attrs) or [None, ""])[1]
    alt = (re.search(r'(?:caption|alt|title)="([^"]*)"', attrs) or [None, ""])[1]
    alt = alt or " ".join(inner.split())
    return f"![{alt}]({src})" if src else ""


def card(match):
    attrs, body = match.group(1), " ".join(match.group(2).split())
    title = (re.search(r'title="([^"]*)"', attrs) or [None, ""])[1]
    href = (re.search(r'href="([^"]*)"', attrs) or [None, ""])[1]
    label = f"[{title}]({href})" if href else f"**{title}**"
    return f"- {label}" + (f" — {body}" if body else "")


def demdx(body):
    """Apply every conversion above, outside code fences only."""
    out = []
    for block in re.split(r"(^```.*?^```)", body, flags=re.M | re.S):
        if block.startswith("```"):
            out.append(block)
            continue
        block = re.sub(r"<(?:T|t)able[^>]*>(.*?)</(?:T|t)able>", table, block,
                       flags=re.S)
        block = re.sub(r"<Callout([^>]*)>(.*?)</Callout>", callout, block,
                       flags=re.S)
        # `(?=[\s>])` so <Cards> is not eaten as <Card> with a stray "s".
        block = re.sub(r"^[ \t]*<Card(?=[\s>])([^>]*)>(.*?)</Card>", card, block,
                       flags=re.S | re.M)
        block = re.sub(r"</?Cards[^>]*>", "", block)
        block = re.sub(r"<Image([^>]*?)/?>(?:(.*?)</Image>)?", image, block,
                       flags=re.S)
        block = re.sub(r"^<hr\s*/?>$", "***", block, flags=re.M)
        # Collapse the blank-line runs the removals leave behind.
        block = re.sub(r"\n{3,}", "\n\n", block)
        out.append(block)
    return "".join(out)


# ---------------------------------------------------------------------------
# Fetching and assembly
# ---------------------------------------------------------------------------

def fetch(url):
    request = urllib.request.Request(url, headers={"User-Agent": "curl/8.7.1"})
    return urllib.request.urlopen(request).read().decode("utf-8")


def index():
    """llms.txt + UNLISTED -> {(section, slug): (url, title, blurb)}."""
    pages = {}
    pattern = re.compile(
        r"^- \[(?P<title>[^\]]*)\]"
        r"\((?P<url>https://docs\.myfatoorah\.com/(?P<section>docs|reference)/"
        r"(?P<slug>[a-z0-9-]+)\.md)\)"
        r"(?::\s*(?P<blurb>.*))?$"
    )
    for line in fetch(INDEX).splitlines():
        match = pattern.match(line.strip())
        if match:
            pages[(match["section"], match["slug"])] = (
                match["url"], match["title"].strip(),
                (match["blurb"] or "").strip())
    if not pages:
        sys.exit(f"no pages parsed from {INDEX} — the index format changed")

    for slug in UNLISTED:
        key = ("docs", slug)
        if key in pages:
            sys.exit(f"{slug!r} is now in {INDEX}; drop it from UNLISTED")
        url = f"{SITE}/docs/{slug}.md"
        try:
            body = fetch(url)
        except urllib.error.HTTPError as error:
            sys.exit(f"UNLISTED page {slug!r} is gone ({error.code}) — remove it")
        # No index entry means no title; take the page's own H1.
        title = re.search(r"^# (.+)$", body, re.M)
        pages[key] = (url, title.group(1).strip() if title else slug, "")
    return pages


def resolve(name, pages):
    """A LAYOUT slug -> the (section, slug) key it names.

    Prefers /docs, falls back to /reference, and honours ALIASES for the slugs
    that live in both. Exits on an unknown name rather than quietly emitting a
    file with a page missing from it.
    """
    if name in ALIASES:
        key = ALIASES[name]
    elif ("docs", name) in pages:
        key = ("docs", name)
    elif ("reference", name) in pages:
        key = ("reference", name)
    else:
        sys.exit(f"LAYOUT names {name!r}, which is not a known page")
    if key not in pages:
        sys.exit(f"alias {name!r} points at {key!r}, which is not a known page")
    return key


def page(key, pages, depth):
    """One page -> markdown, under a heading at `depth`."""
    url, title, blurb = pages[key]
    body = CHROME.sub("", fetch(url)).strip()

    updated = ""
    front = re.match(r"---\n(.*?)\n---\n", body, re.S)
    if front:
        stamp = re.search(r"^updatedAt:\s*(\S+)", front.group(1), re.M)
        if stamp:
            updated = stamp.group(1).split("T")[0]
        body = body[front.end():].strip()

    # Every page opens with its own `# Title`, and indexed pages follow it with
    # the same blurb llms.txt carries. Both are re-emitted below from the
    # index, so drop the page's copies rather than printing each twice.
    body = re.sub(r"\A#\s+" + re.escape(title) + r"\s*\n+", "", body)
    if blurb:
        body = re.sub(r"\A" + re.escape(blurb) + r"\s*\n+", "", body)

    body = demdx(body)

    # Demote every heading so the page nests under its file's section heading
    # instead of competing with it. Capped at 6: markdown has no `#######`.
    body = re.sub(r"^(#{1,6}) ",
                  lambda m: "#" * min(len(m.group(1)) + depth, 6) + " ",
                  body, flags=re.M)

    out = [f"{'#' * depth} {title}", ""]
    meta = [f"`{url[:-3]}`"]
    if updated:
        meta.append(f"updated {updated}")
    out += ["*" + " — ".join(meta) + "*", ""]
    if blurb:
        out += [f"> {blurb}", ""]
    return out + [body, ""]


def drift(refs, pages):
    """Fail if the corpus links to a page nothing placed.

    This is the only notice MyFatoorah gives that it has published something:
    llms.txt already omits 52 live pages, so a mirror that trusts the index
    alone goes stale silently.
    """
    known = {slug for _, slug in pages} | set(KNOWN_BROKEN)
    linked = set()
    for filename in sorted(os.listdir(refs)):
        if not filename.endswith(".md") or filename in PRESERVED:
            continue
        with open(os.path.join(refs, filename)) as handle:
            linked |= set(re.findall(
                r"https://docs\.myfatoorah\.com/(?:docs|reference)/([a-z0-9-]+)",
                handle.read()))
    new = sorted(linked - known)
    if new:
        sys.exit(
            "\nlinked but not mirrored — MyFatoorah has published pages the\n"
            "index does not list. Check each, then add it to LAYOUT (with\n"
            "UNLISTED if llms.txt still omits it) or to KNOWN_BROKEN:\n  "
            + "\n  ".join(f"{SITE}/docs/{slug}" for slug in new))


def refs_dir():
    toplevel = subprocess.run(
        ["git", "rev-parse", "--show-toplevel"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    return os.path.join(toplevel, ".claude", "skills", "myfatoorah", "references")


def main():
    refs = refs_dir()
    pages = index()

    claimed = set()
    for _, _, names in LAYOUT:
        for name in names or []:
            claimed.add(resolve(name, pages))
    orphans = [key for key in pages if key not in claimed]

    for filename, title, names in LAYOUT:
        if filename in PRESERVED:
            sys.exit(f"LAYOUT would overwrite hand-authored {filename}")
        keys = ([resolve(name, pages) for name in names] if names is not None
                else orphans)
        lines = [f"# MyFatoorah — {title}", ""]
        for key in keys:
            lines += page(key, pages, depth=2)
        with open(os.path.join(refs, filename), "w") as handle:
            handle.write("\n".join(lines).rstrip() + "\n")
        print(f"wrote {filename} ({len(keys)} pages)")

    print(f"\n{len(pages)} pages ({len(UNLISTED)} of them unlisted by the "
          f"site), {len(claimed)} placed by name, {len(orphans)} caught by "
          f"plugins.md")
    drift(refs, pages)


if __name__ == "__main__":
    main()
````

## Verifying

Counts were measured on 2026-08-19. A failure after a refresh usually means the
source changed rather than the mirror breaking — read the diff, then update the
expected number. The `pitfalls anchors` block is the important one: each check
is tagged with the `pitfalls.md` entry that rests on it, so a failure names the
entry to re-read.

````bash
#!/usr/bin/env bash
# Verifies the MyFatoorah mirrors against counts measured on 2026-08-19.
# A failure after a refresh usually means the source changed, not that the
# mirror broke: read the diff, then update the expected number here.
set -u
cd "$(git rev-parse --show-toplevel)/.claude/skills/myfatoorah/references" || exit 1
status=0
check() { # name actual expected
  if [ "$2" = "$3" ]; then printf '  ok   %-26s %s\n' "$1" "$2"
  else printf '  FAIL %-26s got %s want %s\n' "$1" "$2" "$3"; status=1; fi
}

echo "files"
for f in intro payment-flows features webhooks api-v3 api-v2 suppliers \
         api-suppliers shipping api-shipping toolkit mobile-sdk plugins \
         pitfalls SOURCES; do
  [ -f "$f.md" ] || { echo "  FAIL missing $f.md"; status=1; }
done

# Page count per file. Each mirrored page contributes exactly one `## `.
echo "pages per file"
for spec in "intro 12" "payment-flows 36" "features 38" "webhooks 19" \
            "api-v3 8" "api-v2 19" "suppliers 15" "api-suppliers 11" \
            "shipping 9" "api-shipping 6" "toolkit 2" "mobile-sdk 7" \
            "plugins 37"; do
  set -- $spec
  check "$1" "$(grep -c '^## ' "$1.md")" "$2"
done

# Every /reference page carries its endpoint's OpenAPI fragment. A page that
# stops carrying one is a page the mirror can no longer answer schema
# questions from.
echo "openapi fragments"
for spec in "api-v3 8" "api-v2 19" "api-suppliers 11" "api-shipping 6"; do
  set -- $spec
  check "$1" "$(grep -c '^### OpenAPI definition' "$1.md")" "$2"
done

# Structural health of the conversion: balanced fences, and no MDX left
# outside code blocks. Both are silent corruption if they regress.
echo "structure"
for f in *.md; do
  case "$f" in pitfalls.md|SOURCES.md) continue ;; esac
  check "fences even in $f" "$(( $(grep -c '^```' "$f") % 2 ))" 0
done
check "residual MDX" "$(awk '
  /^```/ {fence = !fence; next}
  !fence && /^[ \t]*<(Table|table|Callout|Cards?|Image)[ >]/ {n++}
  END {print n+0}' ./*.md)" 0

# Content anchors for the facts pitfalls.md is built on. If one of these
# moves, the corresponding pitfalls entry needs re-reading before it is
# trusted again — the number in brackets is the entry.
echo "pitfalls anchors"
check "Succss in api-v2 [1]"    "$(grep -c 'Succss' api-v2.md)" 5
check "Succss in features [1]"  "$(grep -c 'Succss' features.md)" 6
check "no V2 'Success' [1]"     "$(grep -c '"TransactionStatus": *"Success"' api-v2.md)" 0
check "v2 paths [2]"            "$(grep -c '"/v2/[A-Za-z]' api-v2.md)" 19
check "v3 paths [2]"            "$(grep -c '"/v3/[a-z]' api-v3.md)" 8
check "signature recipes [3,4]" "$(grep -cE '^(Invoice|Refund|Deposit|Supplier|Recurring|Dispute)\.[A-Za-z]+=' webhooks.md)" 7
check "webhook versions [4]"    "$(grep -c '^## Webhook V[12]$' webhooks.md)" 2
check "ValidationErrors [6]"    "$(grep -lc 'ValidationErrors' features.md >/dev/null && echo ok)" ok
check "InvoiceTransactions [7]" "$(grep -c 'InvoiceTransactions' api-v2.md)" 6
check "refund statuses [10]"    "$(grep -c '"Refunded",' api-v2.md)" 2
check "idempotency 250 [11]"    "$(grep -c '250 minutes' features.md)" 1

# The unlisted pages are the mirror's reason to exist; losing one silently
# would undo entry 13.
echo "unlisted pages [13]"
for spec in "webhook-v1 webhooks" "google-pay-native payment-flows" \
            "stcpay payment-flows" "rejection-reasons suppliers" \
            "payment-inquiry features"; do
  set -- $spec
  check "$1" "$(grep -c "docs/$1\`" "$2.md")" 1
done

exit $status
````
