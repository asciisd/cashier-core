# APS Payments Skill — Design

**Date:** 2026-08-11
**Status:** Approved for planning
**Scope:** A project-local Claude skill that mirrors the APS merchant integration docs and records where the live API departs from them.

## Problem

`cashier-core` ships an APS driver — [`ApsClient`](../../../src/Drivers/Aps/ApsClient.php),
[`ApsProvider`](../../../src/Drivers/Aps/ApsProvider.php),
[`ApsAdapter`](../../../src/Drivers/Aps/ApsAdapter.php),
[`ApsWebhookController`](../../../src/Http/Controllers/Webhooks/ApsWebhookController.php) — built against
APS's `/api/v3` surface. The contract it targets lives only at
`https://merchant.aps.money/developers/docs/`, behind a Docusaurus site with no
OpenAPI spec, no changelog, and no versioning.

Two costs follow. Anyone (human or agent) changing the driver either re-reads the
site or guesses at field names and status vocabularies. And when APS silently
changes the contract, nothing surfaces it — the first signal is a production
failure.

## Goals

1. The full APS contract is available offline, in the repo, at full fidelity.
2. It loads automatically when someone works on APS code, without being named.
3. Upstream doc changes become reviewable diffs.
4. Knowledge already earned in driver comments is collected in one place rather
   than scattered across four files.

### Non-goals

- Changing driver behaviour. This spec adds documentation only.
- Covering payment providers other than APS (Sticpay, Payport, Jenapay,
  Heropayment, Internal). If the pattern proves out, it can be repeated later.
- Mirroring the merchant portal UI docs or any authenticated page.

## Source survey

`https://merchant.aps.money/developers/sitemap.xml` lists eight URLs, of which
three are content pages:

| URL path | Title | Extracted text | Mirror? |
|---|---|---|---|
| `docs/h2h/integration` | Host to Host | ~52,000 chars | yes |
| `docs/fpf-v3/integration` | Fast Payment Flow v3 | ~44,000 chars | yes |
| `docs/new-documentation/integration` | Payment Methods | 61 chars | no — see below |
| `docs/intro`, `docs/category/*`, `markdown-page`, `/` | nav stubs | — | no |

The Payment Methods page server-renders a title and nothing else; its body is
client-rendered and unreachable without a browser. It gets an entry in
`SOURCES.md` marking it un-mirrorable, not an empty file.

Both mirrored pages are Docusaurus v3.0.1 SSR output, and all of their substance
is in the server HTML:

| | `h2h` | `fpf-v3` |
|---|---|---|
| `<table>` elements | 14 | 11 |
| code blocks | 27 | 21 |
| — tagged `bash` / `json` / `go` / `csv` | 10 / 4 / 1 / 1 | 8 / 3 / 1 / 1 |
| images | 4 | 3 |

The `go` block on each page is a worked signature-verification implementation.
It is the single most valuable fragment on either page for this package, and it
is exactly the kind of thing a summarizing fetch would compress away — which is
much of the argument for mirroring verbatim.

## Design

### Layout

```
.claude/skills/aps-payments/
├── SKILL.md
└── references/
    ├── h2h.md          Host-to-Host mirror
    ├── fpf-v3.md       Fast Payment Flow v3 mirror
    ├── quirks.md       docs vs. observed behaviour
    └── SOURCES.md      URLs, fetch date, regenerate command
```

`SKILL.md` stays under ~80 lines: when the skill applies, a one-line map of each
reference, the handful of traps worth knowing before reading anything, and
pointers to the driver files. Everything bulky sits in `references/`, which
loads only when relevant. This keeps the always-on cost near zero while putting
~30k tokens of contract one hop away.

`.claude/` does not yet exist in this repo, so this introduces it as a committed
directory. It is not in `.gitignore`, and committing it is the intent — the
skill is meant to arrive with a clone.

### Mirror generation

Mirrors are generated, never hand-copied, so they can be regenerated and diffed.
The pipeline, per page:

1. `curl` the page to HTML.
2. Extract the `<article>` element — drops nav, sidebar, footer, search.
3. Clean Docusaurus chrome from the extracted HTML **before** conversion.
   Counts below are `h2h` / `fpf-v3`:
   - remove `<a class="hash-link">` anchors appended to every heading (24 / 20)
   - remove `<div class="buttonGroup*">` copy-to-clipboard controls (27 / 21)
   - remove the `tocCollapsible` mobile table-of-contents block (2 / 2), which
     duplicates the heading structure
   - unwrap `codeBlockContainer` / `codeBlockContent` / `language-*` wrappers,
     preserving `codeBlockTitle` text (8 / 8) as a caption line above the block —
     these titles are load-bearing, carrying both the operation name
     (`Initiate transaction`) and the language label (`Go`)
4. `pandoc -f html -t gfm --wrap=none`.

Cleaning before pandoc rather than after is deliberate: pandoc passes unknown
HTML through verbatim, so anything not stripped up front lands in the markdown as
raw tags. A trial conversion of `h2h` without cleanup left 213 raw-HTML lines out
of 1,514; the four rules above account for all of them.

Neither page uses Docusaurus `Tabs` — checked explicitly, since tabs SSR every
panel and would otherwise smear several language variants together with their
labels stripped. There are no `role="tabpanel"` elements on either page, so no
content is hidden behind interaction and the `<article>` extraction is complete.

The script lives in `references/SOURCES.md` as a fenced block, not as a
committed executable — it runs a handful of times a year and an inert code block
cannot drift from its own documentation.

### Diagram transcription

Four PNGs sit in the `h2h` article (`steps`, `Sequence_Diagram_For_Deposit`,
`Sequence_Diagram_For_Payout`, `hosted_page`) and three in `fpf-v3`. Markdown
image links to them would be inert — the skill is read as text, so a linked
binary conveys nothing.

Each diagram is instead read and transcribed into the mirror at the position it
occupied:

- the two sequence diagrams become mermaid `sequenceDiagram` blocks
- `steps` and `hosted_page` become short prose or a numbered list

Transcriptions are marked with an HTML comment naming the source image, so a
future regeneration can tell transcribed content from converted content. The
PNGs are not committed.

### `quirks.md`

The one hand-authored reference, and the reason this is a skill rather than a
bookmark. Each entry follows a fixed shape:

> **What the docs say** → **what actually happens** → **where we handle it**
> (`file:line`)

An entry is admitted only if both halves can be cited: a specific passage in the
mirrored docs, and a specific line of driver code or a commit. Anything that
cannot be grounded on both sides is dropped rather than guessed at.

The candidate set, drawn from the existing driver:

1. **Transaction lookup route.** No `/transactions/{id}` collection route exists;
   the transaction GUID sits directly under the merchant GUID. A wrong path
   returns plain-text `404 page not found`, indistinguishable from an unknown
   transaction once the status is discarded. `ApsClient.php:42-70`
2. **Callback secret ≠ app secret.** Outbound auth uses `X-App-Token` /
   `X-App-Secret`; callbacks are HMAC-SHA256 signed over the raw body with a
   separately issued callback secret, delivered in `X-Signature`.
   `ApsClient.php:96-111`
3. **Callbacks carry no merchant identifier.** With several APS accounts posting
   to one URL, the sender is identified by trying each account's callback secret
   until one verifies — the match is the proof.
   `ApsWebhookController.php:68-98`, `ApsProvider.php:218-231`
4. **One account per product.** APS issues a separate GUID, app key and callback
   secret per product; Binance Pay is not reachable with the card account's
   credentials. `ApsProvider.php:31-39`
5. **BANKCARD `how` URL points at the wrong host.** It returns the PCI gateway's
   JSON API host, which shows the customer a raw JSON payment session; the card
   form SPA serves the identical path from a sibling host.
   `ApsProvider.php:118-142`
6. **Two parallel status vocabularies.** Fiscal (`pending`, `canceled`,
   `expired`, `done`, `failed`) and sep31 deposit (`pending_sender`,
   `pending_external`, `completed`, `error`) both appear, sometimes in the same
   payload. `ApsAdapter.php:86-101`
7. **Downstream rejection arrives as `canceled`, not `failed`,** with the PSP's
   reason in `external_message` (e.g. `"512: Desktop devices are not
   supported"`). `ApsAdapter.php:60-84`, commit `3fe3673`
8. **Callback payloads nest under a top-level `payload` key;** retrieve responses
   may or may not. `ApsAdapter.php:43-46, 64-67`
9. **Money field names.** `amount_in`, `amount_out`, `customer_fee`; the hosted
   checkout URL is `how`. `ApsAdapter.php:18-38`
10. **Base URL.** `https://fpf-api.proc-gw.com` serves the H2H `/api/v3` API
    despite the FPF-flavoured hostname. `ApsProvider.php:49`
11. **Undocumented connection keys.** The driver reads `callback_secret`,
    `deposit_method`, `redirect_url`, `webhook_url` and `checkout_host_map`, none
    of which appear in the example block in `config/cashier-core.php:32-38`.

Entry 11 documents a real gap in the package's own config example. Closing that
gap is a code change and is out of scope here; the entry records it so it is not
lost.

An earlier draft included a twelfth entry — that `/info` fields vary per method,
so the field list should not be cached. It was cut: that is not a divergence,
it is simply what the docs say, and the docs are mirrored verbatim two files
away. `quirks.md` earns its keep only by holding what the mirrors cannot tell
you; restating them dilutes it.

### Trigger

```yaml
---
name: aps-payments
description: Use when working with the APS payment gateway (merchant.aps.money) —
  the Aps driver, ApsClient/ApsProvider/ApsAdapter, APS webhooks and callbacks,
  or APS deposits, remits, refunds, balance and payment-method lookups. Covers
  the Host-to-Host and Fast Payment Flow v3 API contracts.
---
```

The description names both the concrete symbols someone would have open and the
operations they would be performing, so the skill fires from either direction.

### Staleness

`SOURCES.md` records each URL, the fetch date, the Docusaurus version observed,
and the regenerate command. Refreshing is one command run; `git diff` on the
result is the changelog APS does not publish. No automation — an unattended
fetch that rewrites vendored docs without review is worse than a stale mirror,
because it removes the moment where someone notices the contract moved.

## Verification

The work is done when all of the following hold:

1. `.claude/skills/aps-payments/SKILL.md` exists with valid frontmatter and is
   under 80 lines.
2. `references/h2h.md` and `references/fpf-v3.md` contain no raw `<div>`,
   `<button>`, `<span>` or `hash-link` markup. Checked with `grep -c '^<[a-z]'`,
   which must return 0 — the `<!-- source: … -->` comments introduced by diagram
   transcription (point 4) are expected and do not match that pattern.
3. No content was dropped in conversion, checked by count against the source
   article:

   | | `h2h.md` | `fpf-v3.md` |
   |---|---|---|
   | markdown tables (`^\|` header rows) | 14 | 11 |
   | fenced code blocks (` ``` ` pairs) | 27 | 21 |

   The `go` signature-verification sample must be present in both, complete with
   its constant-time-comparison warning.
4. Both sequence diagrams appear as mermaid blocks, each preceded by an HTML
   comment naming its source image.
5. Every `quirks.md` entry cites a file:line or commit that exists — checked by
   opening each reference.
6. `SOURCES.md` regenerate command runs clean from the repo root.

Point 3 is the one that matters most: a mirror that silently drops a table is
worse than no mirror, because it reads as complete.

## Alternatives considered

**WebFetch on demand.** Always current, no vendoring. Rejected: it re-summarizes
through a small model on every read, losing exactly the field-level detail that
makes the docs worth having, and it yields no diff when APS changes something.

**Plain files under `docs/`.** Same content, reviewable, no skill machinery.
Rejected as the primary form: it must be pointed at explicitly, which fails goal 2.
The mirrors are readable as plain markdown either way, so nothing is lost by
placing them under `.claude/skills/`.

**Everything in `CLAUDE.md`.** Rejected: ~30k tokens in every session's context,
including the majority of sessions that never touch APS.

**Mirror `h2h` only.** Half the size, and the driver only uses the `/api/v3`
surface today. Rejected: FPF v3 documents the hosted-checkout flow that the
`how` URL and `checkout_host_map` host-swap already brush against, so the two
are not cleanly separable in practice.
