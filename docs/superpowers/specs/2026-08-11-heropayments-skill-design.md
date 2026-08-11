# Heropayments Skill — Design

**Date:** 2026-08-11
**Status:** Approved for planning
**Scope:** A project-local Claude skill that mirrors the Heropayments API documentation and records where the live contract departs from it.

## Problem

`cashier-core` ships a Heropayment driver —
[`HeropaymentClient`](../../../src/Drivers/Heropayment/HeropaymentClient.php),
[`HeropaymentProvider`](../../../src/Drivers/Heropayment/HeropaymentProvider.php),
[`HeropaymentAdapter`](../../../src/Drivers/Heropayment/HeropaymentAdapter.php),
[`HeropaymentQuoteService`](../../../src/Drivers/Heropayment/HeropaymentQuoteService.php),
[`HeropaymentQuote`](../../../src/Drivers/Heropayment/HeropaymentQuote.php),
[`HeropaymentWebhookController`](../../../src/Http/Controllers/Webhooks/HeropaymentWebhookController.php)
— built against the `/v2` surface of `api.heropayments.io`. The contract it
targets is published only as a Postman documenter page,
`https://documenter.getpostman.com/view/17469357/UVyvwv7a`: no OpenAPI spec, no
changelog, no versioning, and no server-rendered HTML.

This repeats the problem the [APS skill](2026-08-11-aps-payments-skill-design.md)
solved for `merchant.aps.money`. Anyone changing the driver either re-reads the
page or guesses at field names and status vocabularies, and when Heropayments
changes the contract nothing surfaces it.

Drafting this design already surfaced four divergences that had gone unnoticed —
including one where the driver reads a field name that does not exist in the
contract. That is the argument for the skill in miniature.

## Goals

1. The full Heropayments contract is available offline, in the repo, at full fidelity.
2. It loads automatically when someone works on Heropayment code, without being named.
3. Upstream doc changes become reviewable diffs.
4. Knowledge already earned in driver comments is collected in one place rather
   than scattered across six files.

### Non-goals

- Changing driver behaviour. This spec adds documentation only. The divergences
  found while writing it are recorded in `quirks.md`, not fixed; see
  [Findings that are not fixed here](#findings-that-are-not-fixed-here).
- Covering payment providers other than APS and Heropayments (Sticpay, Payport,
  Jenapay, Internal).
- Mirroring the merchant portal UI docs or any authenticated page.

## Source survey

Postman serves the collection as JSON, unauthenticated:

    https://documenter.gw.postman.com/api/collections/17469357/UVyvwv7a?segregateAuth=true&versionTag=latest

`200`, 248,594 bytes. This is the decisive difference from APS: the source is
already structured, so folders, methods, URLs, parameters and saved responses do
not have to be re-inferred from HTML.

| Section | Requests | Saved responses |
|---|---|---|
| V2 Flow / Common | 8 | 8 |
| V2 Flow / Deposits (API and widget) | 3 | 3 |
| V2 Flow / Withdrawals | 1 | 1 |
| Custody flow / Common | 6 | 4 |
| Custody flow / Create a deposit/withdrawal | 2 | 2 |
| **Total** | **20** | **18** |

Prose lives in two places: `info.description` (41,370 chars — the overview) and
per-request `description` fields (42,271 chars combined). Both are HTML.

The overview is self-contained. Every "more information" link in the collection
is an in-page anchor resolving to one of its own headings. Its top-level
structure, sub-headings elided:

> We are partnered with cashiers like · Authentication · Recommended integration
> flow · DEPOSIT FLOW · WITHDRAWAL FLOW (PAYOUTS TO USERS) · API Request signing
> · Examples · Callbacks · Callback notification examples · V2 deposit Callback
> · Fallbacks · CallbackUrl recommendations · Payment Statuses (V2 flow) ·
> Faster Top-Ups with the "Confirming" Status · Payments statuses (Custody flow)
> · Multiple deposit processing · Automated mistaken deposits processing ·
> Static deposit address per each customer · API errors code description

The exceptions are the API error tables, which the overview delegates to two
Google Sheets. Both export as CSV without authentication (`200`, ~4 KB each), so
they are mirrorable rather than merely citable.

**Secrets:** checked. All four credential variables hold placeholders
(`your_api_key`, `your_api_secret`, `your_api_key_custody`,
`your_api_secret_custody`), `auth` is null, and no saved request or response
carries a real key. Nothing sensitive gets committed.

### What the driver actually uses

The V2 flow only: `/v2/currencies`, `/v2/rate`, `/v2/min-amount`,
`/v2/network-fees`, `/v2/invoices`, `/v2/payments`, `/v2/payments/{id}`,
`/v2/payments/order/{orderId}`. It does not call `/v2/withdrawal`,
`/v2/balance`, the payment list, or anything under `/custody`.

Both flows are mirrored regardless. References load only when read, so Custody
costs nothing until someone needs it, keeping the generator scoped to the whole
collection means it does not need re-scoping the day payouts arrive, and
quirks entry 10 below already depends on holding the Custody vocabulary.

## Design

### Layout

```
.claude/skills/heropayments/
├── SKILL.md
└── references/
    ├── overview.md    auth, signing, flows, status vocabularies,
    │                  multiple-deposit and mistaken-deposit processing,
    │                  static addresses
    ├── callbacks.md   callback mechanics and the three annotated payloads
    ├── v2.md          12 V2 requests
    ├── custody.md     8 Custody requests
    ├── errors.md      both error-code sheets as markdown tables
    ├── quirks.md      docs vs. observed behaviour
    └── SOURCES.md     URLs, fetch date, regenerate and verify scripts
```

The skill is named `heropayments` — the vendor's own spelling. The driver's
singular `heropayment` appears in the frontmatter description, so the skill
fires on either.

`SKILL.md` stays under ~80 lines: when the skill applies, a one-line map of each
reference, the handful of traps worth knowing before reading anything, and
pointers to the driver files. Everything bulky sits in `references/`, which
loads only when relevant.

### Splitting `callbacks.md` out of the overview

The overview arrives as one 41k-char blob, but webhook work — the most common
reason to open this skill — needs only its callback half. The generator splits
it at the heading carrying `id="callbacks"` and stops at the one carrying
`id="payment-statuses-v2-flow"`, emitting the head and tail into `overview.md`
and the middle into `callbacks.md`.

Matching on the heading `id` rather than its visible text is deliberate: the
ids are slugs in the source HTML and survive changes to capitalisation,
punctuation and wording. It is still a dependency on two upstream identifiers —
the one fragile thing in the pipeline — so the generator exits non-zero when
either is missing rather than silently emitting an empty file, and `SOURCES.md`
records both.

### Mirror generation

Mirrors are generated, never hand-copied, so they can be regenerated and diffed.
Per the APS precedent the script lives in `SOURCES.md` as an inert fenced block
rather than a committed executable: it runs a handful of times a year, and a
code block cannot drift from its own documentation.

The pipeline:

1. Fetch the collection JSON.
2. Walk folders depth-first. For each request emit, in order: heading, method
   and URL, path and query parameters with their descriptions, request body,
   the description HTML, then each saved response with its name, status code
   and body.
3. Convert every HTML fragment through `pandoc -f html -t gfm --wrap=none`.
   JSON bodies are emitted as fenced blocks directly, not via pandoc.
4. Split `info.description` at the two boundary headings and convert each part.
5. Fetch both error CSVs and render them as markdown tables.

Pandoc passes unknown HTML through verbatim, so the verification below checks
for raw tags rather than assuming they are absent.

Postman's descriptions are hand-written HTML and carry the usual artefacts —
smart quotes in field names (`"userNotes"`), a comma for a decimal point
(`"networkFee": 0,5`), and typos. These are reproduced as-is. A mirror that
silently corrects its source stops being a mirror, and `quirks.md` is where
corrections belong. The same applies to request bodies, several of which are
truncated mid-object in the source and are not parseable JSON; they are emitted
verbatim rather than repaired.

### The one thing that is not reproduced verbatim

Four saved responses are bulk data lists rather than examples of contract
shape — every supported cryptocurrency (1,631 lines), every Custody minimum
(677), every supported fiat (275), every Custody balance (100). Verbatim they
are 2,683 of the collection's 2,986 response-body lines, and they re-diff on
every coin listing, burying real contract changes in noise. The driver reads
all four from the live API at runtime and caches them
(`HeropaymentQuoteService.php:42-74, 140-171`), so the mirrored copy is stale
the day it lands.

Response bodies are therefore capped at 60 lines, with the elision marked in
place and naming its cause. The cap sits above the longest genuine payload
example (42 lines), so no example of contract shape is ever cut — the rule only
ever fires on repeated data rows. This is the sole departure from verbatim
mirroring, and the verify script asserts the elision count so it cannot spread
silently.

### `quirks.md`

The one hand-authored reference, and the reason this is a skill rather than a
bookmark. Each entry follows a fixed shape:

> **What the docs say** → **what actually happens** → **where we handle it**
> (`file:line`)

An entry is admitted only if both halves can be cited: a specific passage in the
mirrored docs, and a specific line of driver code or a commit. Anything that
cannot be grounded on both sides is dropped rather than guessed at. Restating
what the mirrors already say is also grounds for dropping — `quirks.md` earns
its keep only by holding what they cannot tell you.

The entries:

1. **Repeat deposits reuse `externalOrderId`.** Deposit addresses are static per
   `customerId`, so a customer who saves the address and sends again produces a
   *new payment* carrying the same `externalOrderId` with a fresh `sequence`
   value; callbacks for it go to the original payment's `callbackUrl`.
   "Automated mistaken deposits processing" does the same with
   `sequence: original` and a changed `payCurrency`.
   `HeropaymentProvider.php:200-205` resolves the transaction by
   `externalOrderId` alone, and `sequence` is read nowhere in `src/`. We pass
   the MT5 trading account login as `customerId`
   (`HeropaymentProvider.php:109-114`), so each account holds one permanent
   deposit address for as long as it exists.
2. **`actuallyPaid` is not a Heropayments field.** The string appears zero times
   in the collection; the documented field is `paidAmount`.
   `HeropaymentAdapter.php:78` and `:137` both read `actuallyPaid`, so
   `settlement_actually_paid` and `heropayment_actually_paid` are always absent.
   The docs still link `support@nowpayments.io` — they are a NOWPayments fork,
   and `actually_paid` is NOWPayments' spelling.
3. **`partially_paid` is not a documented status.** The nine documented V2
   statuses are `waiting`, `confirming`, `exchanging`, `sending`, `finished`,
   `failed`, `refunded`, `hold`, `expired`. `HeropaymentAdapter.php:111` maps
   `partially_paid` to `Succeeded` and `:81-83` flags it for attention. Also a
   NOWPayments name. Unverified against production callback logs.
4. **Signature payload encoding.** The docs prescribe normalizing JSON with
   `JSON.stringify(JSON.parse(x))` and say nothing about what that implies
   outside JavaScript: Node leaves `/` and non-ASCII unescaped, while PHP's
   default `json_encode` escapes both, yielding `401 Invalid signature`.
   `HeropaymentClient.php:11-17`.
5. **`networkfee` units.** `/v2/network-fees` is documented as returning a USDT
   equivalent; live values read as native currency units (`btc` returns
   `0.000007`, ~$0.75 as BTC and meaningless as USDT).
   `HeropaymentClient.php:88-94`, `HeropaymentQuoteService.php:156-171`.
6. **Invoice id is not payment id.** The create-invoice response's `id` is an
   invoice id — which the docs themselves warn against using as an identifier,
   "as it does not appear in the report" — while callbacks carry a separate
   payment id. Correlation therefore runs on `externalOrderId`.
   `HeropaymentProvider.php:80-92`.
7. **No endpoint exposes the contracted `feePercent`.** It appears only on a
   created payment and on status callbacks, so quotes shown before the redirect
   use a configured value that must be reconciled against callbacks after the
   fact. `HeropaymentQuoteService.php:173-185`.
8. **The widget takes no currency allow-list.** `payCurrency` pins exactly one
   currency; left null, the widget lists everything Heropayments supports,
   including currencies we never quoted a minimum for.
   `HeropaymentProvider.php:116-135`.
9. **`/v2/payments-address` exists in prose only.** It is named under "Static
   deposit address per each customer" and in the V2 error sheet, but the
   collection contains no such request — no parameters, no body, no response.
10. **Custody's status vocabulary is disjoint from V2's** (`new`, `pending`,
    `processing` against `waiting`, `confirming`, `exchanging`, `sending`).
    `HeropaymentAdapter::mapStatus` (`:110-117`) knows only the V2 names, so all
    three Custody in-progress statuses fall through `default => Pending`.
    Latent rather than live — recorded because the Custody mirror now ships
    alongside it.

Two candidates were considered and cut. `confirming` must not credit balances —
but the adapter already maps it to `Pending`, so the docs and the code agree and
the mirror says it two files away. Likewise the GET signing rule (sign the query
string without the `?`, sign the empty string when there is none): documented
plainly, implemented plainly, nothing to add.

### Findings that are not fixed here

Entries 1, 2, 3 and 10 describe behaviour that may be wrong in production, not
merely undocumented. They are recorded and left alone, following the APS
precedent where the config-example gap was documented rather than closed.

The reasoning: entry 2 changes the settlement path on the evidence of a document
search alone, and entries 1 and 3 cannot be settled without production callback
logs — whether Heropayments emits `partially_paid`, and whether any repeat
deposit has actually arrived, are facts about our traffic, not about the docs.
Mixing a behaviour change into a documentation commit also costs the clean
`git log` entry that makes the fix reviewable on its own terms.

Each of these entries carries an explicit **Unverified** marker naming what
would settle it.

### Trigger

```yaml
---
name: heropayments
description: Use when working with the Heropayments crypto gateway
  (api.heropayments.io) — the Heropayment driver,
  HeropaymentClient/HeropaymentProvider/HeropaymentAdapter/HeropaymentQuoteService,
  Heropayment callbacks and webhooks, or Heropayment crypto deposits, invoices,
  rate and minimum-amount quotes, withdrawals and balances. Covers both the V2
  and Custody API flows.
---
```

The description names both the concrete symbols someone would have open and the
operations they would be performing, so the skill fires from either direction.

### Staleness

`SOURCES.md` records the collection URL, the documenter page URL, both CSV export
URLs, the fetch date, the two boundary headings, and the regenerate command.
Refreshing is one command run; `git diff` on the result is the changelog
Heropayments does not publish. No automation — an unattended fetch that rewrites
vendored docs without review removes the moment where someone notices the
contract moved.

## Verification

The work is done when all of the following hold:

1. `.claude/skills/heropayments/SKILL.md` exists with valid frontmatter and is
   under 80 lines.
2. `v2.md` renders 12 requests with 12 saved responses; `custody.md` renders 8
   requests with 6 saved responses. Each request shows its method and full URL.
3. `callbacks.md` contains all three annotated callback examples — V2 deposit,
   V2 withdrawal, Custody deposit. There is no Custody withdrawal example
   upstream; because `callbacks.md` is generated and would overwrite any note
   placed in it, that absence is recorded in `SOURCES.md` under what the source
   does not contain.
4. `overview.md` retains all five signing samples — PHP, Python, Node, C#,
   browser — and both Postman pre-request scripts.
5. `errors.md` holds both tables at full row count — 25 V2 rows, 24 Custody.
6. No raw HTML survives conversion: `grep -cE '^<[a-z]+[ />]'` returns 0 for
   every file in `references/`. The trailing character class is required —
   pandoc emits GFM autolinks (`<https://api.heropayments.io/v2/rate>`), and the
   looser `^<[a-z]` used by the APS skill matches four of them as false
   positives.
7. Exactly four capped response bodies remain, two in `v2.md` and two in
   `custody.md`, each carrying its elision marker.
8. Every `quirks.md` citation resolves to a real line, checked by opening each
   reference.
9. The regenerate and verify scripts in `SOURCES.md` both run clean from the
   repo root.

Points 2 to 5 are the ones that matter most: a mirror that silently drops a
request, a response example or a signing sample is worse than no mirror, because
it reads as complete.

## Alternatives considered

**Scrape the rendered documenter page**, as `aps-mirror.py` scrapes Docusaurus.
Rejected: the page is a client-rendered SPA with no server HTML to extract, and
it would discard the structure the JSON hands over for free.

**Commit the raw collection JSON alongside the mirrors** as the diff artefact.
Rejected as redundant — the generated markdown diffs at least as legibly, and
248 KB of minified JSON in review is noise, not signal.

**V2 only**, matching what the driver calls today. Rejected: Custody costs
nothing until read, and re-scoping the generator later is work saved for no
present gain.

**One reference file for the whole collection.** Rejected: ~25k tokens would
load as a unit whenever any part of the contract is needed, when most tasks want
one of callbacks, quotes, or a single endpoint.

**WebFetch on demand.** Rejected for the same reason as APS: it re-summarizes
through a small model on every read, losing exactly the field-level detail that
makes the docs worth having, and it yields no diff when Heropayments changes
something.
