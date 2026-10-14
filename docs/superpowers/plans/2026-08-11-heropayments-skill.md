# Heropayments Skill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Vendor the Heropayments API contract into a project-local Claude skill, alongside a hand-written record of where the driver departs from it.

**Architecture:** A generator script fetches the Postman collection as JSON, walks its folders into per-flow markdown references, splits the 41k-char overview into narrative and callback halves on heading ids, and pulls both error-code tables from Google Sheets as CSV. Every HTML fragment goes through pandoc. A thin `SKILL.md` routes to the results and loads automatically when someone works on Heropayment code.

**Tech Stack:** Python 3 standard library only — no `beautifulsoup4` needed, since the source is JSON rather than HTML — plus `pandoc` (installed, 3.9, at `/opt/homebrew/bin/pandoc`), and git.

**Spec:** [`docs/superpowers/specs/2026-08-11-heropayments-skill-design.md`](../specs/2026-08-11-heropayments-skill-design.md)

## Global Constraints

- Everything lands under `.claude/skills/heropayments/`. The parent
  `.claude/skills/` already exists and is committed, holding `aps-payments/`.
- No source file under `src/` is modified. This plan adds documentation only.
  Four `quirks.md` entries describe behaviour that may be wrong in production;
  they are recorded with an **Unverified** marker, not fixed.
- Mirrors are **generated**, never hand-edited. `quirks.md` and `SKILL.md` are
  the only hand-authored files; `SOURCES.md` is hand-authored and holds the two
  scripts as inert fenced blocks.
- Every `quirks.md` entry cites both a passage in a mirrored doc and a
  `file:line` in this repo. Entries that cannot be grounded on both sides are
  dropped, not guessed.
- The collection JSON and the error CSVs are **not** committed; only the
  generated markdown is.
- Verification counts are exact, not minimums. Every number in this plan was
  measured by running the generator against the live sources on 2026-08-11, and
  the verify script passed clean with all 30 checks green.
- Both scripts in this plan are final and tested. Type them as given. Three
  details in them look incidental and are not: the `User-Agent` header (Postman
  answers urllib's default with `403`), the `[ />]` in the raw-tag pattern (GFM
  autolinks match the looser pattern), and the `click-to-expand-wrapper` fence
  rewrite (10 fences carry a Postman CSS class as their language).

---

### Task 1: Generator, mirrors and `SOURCES.md`

**Files:**
- Create: `.claude/skills/heropayments/references/SOURCES.md`
- Create: `.claude/skills/heropayments/references/overview.md` (generated)
- Create: `.claude/skills/heropayments/references/callbacks.md` (generated)
- Create: `.claude/skills/heropayments/references/v2.md` (generated)
- Create: `.claude/skills/heropayments/references/custody.md` (generated)
- Create: `.claude/skills/heropayments/references/errors.md` (generated)
- Scratch (not committed): `/tmp/hero-mirror.py`, `/tmp/hero-verify.sh`

**Interfaces:**
- Consumes: nothing.
- Produces: the five mirrors. Task 2 cites passages in `overview.md`,
  `callbacks.md`, `v2.md` and `custody.md`; Task 3 links all five from
  `SKILL.md`. `SOURCES.md` holds the generator as a fenced `python` block under
  `## Regenerating` and the checks as a fenced `bash` block under `## Verifying`.

- [ ] **Step 1: Create the skill directory**

```bash
mkdir -p .claude/skills/heropayments/references
```

- [ ] **Step 2: Write the verification script and watch it fail**

Save as `/tmp/hero-verify.sh`:

````bash
#!/usr/bin/env bash
# Verifies the Heropayments mirrors against counts measured on 2026-08-11.
# A failure after a refresh usually means the source changed, not that the
# mirror broke: read the diff, then update the expected number here.
set -u
cd "$(git rev-parse --show-toplevel)/.claude/skills/heropayments/references" || exit 1
status=0
check() { # name actual expected
  if [ "$2" = "$3" ]; then printf '  ok   %-24s %s\n' "$1" "$2"
  else printf '  FAIL %-24s got %s want %s\n' "$1" "$2" "$3"; status=1; fi
}

for f in overview.md callbacks.md v2.md custody.md errors.md quirks.md SOURCES.md; do
  [ -f "$f" ] || { echo "FAIL missing $f"; status=1; }
done

# Raw HTML that pandoc passed through. The pattern requires a space, slash or
# close-bracket after the tag name so GFM autolinks (<https://...>) don't match.
for f in overview.md callbacks.md v2.md custody.md errors.md; do
  check "raw tags in $f" "$(grep -cE '^<[a-z]+[ />]' "$f")" 0
  check "fences even in $f" "$(( $(grep -c '^```' "$f") % 2 ))" 0
done

echo "v2.md"
check "requests"   "$(grep -c '^### ' v2.md)" 12
check "responses"  "$(grep -c '^#### ' v2.md)" 12
check "fences"     "$(grep -c '^```' v2.md)" 32

echo "custody.md"
check "requests"   "$(grep -c '^### ' custody.md)" 8
check "responses"  "$(grep -c '^#### ' custody.md)" 6
check "fences"     "$(grep -c '^```' custody.md)" 16

echo "callbacks.md"
for label in "V2 deposit Callback" "V2 withdrawal Callback" "Custody deposit Callback"; do
  check "$label" "$(grep -c "$label" callbacks.md)" 1
done

echo "overview.md"
for sample in "example in node.js" "example in C#" "example in PHP" \
              "example in Python" "example in browser" \
              "pre-request script (V2)" "pre-request script (Custody)"; do
  check "$sample" "$(grep -c "$sample" overview.md)" 1
done

echo "errors.md"
check "V2 rows"      "$(awk '/^## V2/{s=1;next} /^## Custody/{s=0} s&&/^\| /&&!/^\|-/{n++} END{print n+0}' errors.md)" 25
check "Custody rows" "$(awk '/^## Custody/{s=1;next} s&&/^\| /&&!/^\|-/{n++} END{print n+0}' errors.md)" 24

echo "elisions"
check "v2.md"      "$(grep -c 'elided by the mirror generator' v2.md)" 2
check "custody.md" "$(grep -c 'elided by the mirror generator' custody.md)" 2

exit $status
````

Run: `bash /tmp/hero-verify.sh; echo "EXIT=$?"`
Expected: seven `FAIL missing` lines, then failures for every count, `EXIT=1`.

- [ ] **Step 3: Write the generator script**

Save as `/tmp/hero-mirror.py`:

````python
#!/usr/bin/env python3
"""Mirror the Heropayments Postman collection to markdown references.

Writes overview.md, callbacks.md, v2.md, custody.md and errors.md into the
current directory. Requires pandoc.
"""
import json
import re
import subprocess
import sys
import urllib.request

COLLECTION = ("https://documenter.gw.postman.com/api/collections/"
              "17469357/UVyvwv7a?segregateAuth=true&versionTag=latest")
SHEET = ("https://docs.google.com/spreadsheets/d/"
         "16R_DIBU_3TIwKG7j6e2Iq6smyWXvSK5kWRYPpyPwym4/export?format=csv&gid=")
ERROR_SHEETS = [("V2", "0"), ("Custody", "1669444698")]

# Four response examples are bulk data lists — every supported ticker, every
# minimum — not contract shape. Verbatim they are 2,683 of the collection's
# 2,986 response-body lines, and they re-diff on every coin listing. Capped,
# with the elision marked. The cap sits above the longest real payload (42
# lines) so no example of contract shape is ever cut.
MAX_RESPONSE_LINES = 60

# The overview is one 41k-char blob; callback material is the half most often
# needed alone. Split on heading ids, which are stable slugs in the source.
CALLBACKS_START = r'<h[1-6][^>]*id="callbacks"'
CALLBACKS_END = r'<h[1-6][^>]*id="payment-statuses-v2-flow"'


def fetch(url):
    # Postman's API answers urllib's default user-agent with 403.
    request = urllib.request.Request(url, headers={"User-Agent": "curl/8.7.1"})
    return urllib.request.urlopen(request).read().decode("utf-8")


def md(html):
    """HTML fragment -> GitHub-flavoured markdown."""
    if not html or not html.strip():
        return ""
    result = subprocess.run(
        ["pandoc", "-f", "html", "-t", "gfm", "--wrap=none"],
        input=html, capture_output=True, text=True, check=True,
    )
    # Postman marks long blocks with a CSS class that pandoc reads as a
    # language name. Nothing downstream understands it; leave the fence bare.
    return re.sub(r"^``` click-to-expand-wrapper$", "```", result.stdout.strip(), flags=re.M)


def split_at(text, start_pattern, end_pattern):
    """Return (before + after, between) for the region between two headings."""
    start = re.search(start_pattern, text)
    end = re.search(end_pattern, text)
    if start is None:
        sys.exit(f"boundary heading not found: {start_pattern}")
    if end is None:
        sys.exit(f"boundary heading not found: {end_pattern}")
    return text[:start.start()] + text[end.start():], text[start.start():end.start()]


def render_request(item, depth):
    """One request -> markdown: URL, body, description, saved responses."""
    request = item["request"]
    url = request["url"]
    url = url if isinstance(url, str) else url.get("raw", "")

    out = [f"{'#' * depth} {item['name']}", "", f"`{request['method']} {url}`", ""]

    body = (request.get("body") or {}).get("raw", "")
    if body.strip():
        # Emitted verbatim: several bodies in the source are truncated and are
        # not parseable JSON. A mirror reproduces its source, warts included.
        out += ["**Request body**", "", "```json", body.rstrip(), "```", ""]

    description = md(request.get("description", ""))
    if description:
        out += [description, ""]

    for response in item.get("response", []):
        code = response.get("code")
        label = f"{response.get('name', 'Response')}"
        if code:
            label += f" — {code} {response.get('status', '')}".rstrip()
        out += [f"{'#' * (depth + 1)} {label}", "", "```json",
                *cap(response.get("body") or ""), "```", ""]

    return out


def cap(body):
    """Response body lines, truncating bulk data lists with a marked elision."""
    lines = body.rstrip().splitlines()
    if len(lines) <= MAX_RESPONSE_LINES:
        return lines
    dropped = len(lines) - MAX_RESPONSE_LINES
    return lines[:MAX_RESPONSE_LINES] + [
        f"... {dropped} more lines elided by the mirror generator "
        f"(bulk data list; fetch the live response for the full set)"]


def render_folder(item, depth):
    out = [f"{'#' * depth} {item['name']}", ""]
    description = md(item.get("description", ""))
    if description:
        out += [description, ""]
    for child in item["item"]:
        out += (render_folder if "item" in child else render_request)(child, depth + 1)
    return out


def csv_to_table(text):
    import csv
    import io

    rows = list(csv.reader(io.StringIO(text)))
    if not rows:
        return []
    width = max(len(r) for r in rows)

    def line(cells):
        cells = [c.replace("\n", " ").replace("|", "\\|").strip() for c in cells]
        cells += [""] * (width - len(cells))
        return "| " + " | ".join(cells) + " |"

    return [line(rows[0]), "|" + "---|" * width] + [line(r) for r in rows[1:] if any(r)]


def write(path, lines):
    with open(path, "w") as handle:
        handle.write("\n".join(lines).rstrip() + "\n")
    print(f"wrote {path}")


def main():
    collection = json.loads(fetch(COLLECTION))

    overview_html, callbacks_html = split_at(
        collection["info"]["description"], CALLBACKS_START, CALLBACKS_END)

    write("overview.md", ["# Heropayments — overview", "", md(overview_html)])
    write("callbacks.md", ["# Heropayments — callbacks", "", md(callbacks_html)])

    for folder, filename in zip(collection["item"], ("v2.md", "custody.md")):
        write(filename, render_folder(folder, 1))

    errors = ["# Heropayments — API error codes", ""]
    for name, gid in ERROR_SHEETS:
        errors += [f"## {name}", ""] + csv_to_table(fetch(SHEET + gid)) + [""]
    write("errors.md", errors)


if __name__ == "__main__":
    main()
````

- [ ] **Step 4: Generate the five mirrors**

```bash
cd .claude/skills/heropayments/references && python3 /tmp/hero-mirror.py && cd -
```

Expected: five `wrote <file>` lines, no traceback.

- [ ] **Step 5: Confirm the sizes look right before trusting the counts**

```bash
wc -lc .claude/skills/heropayments/references/*.md
```

Expected, within a line or two:

```
   170   15002 callbacks.md
   587   21433 custody.md
    58    8617 errors.md
   561   20974 overview.md
   925   33015 v2.md
```

If `v2.md` comes back near 2,700 lines / 90k chars, `MAX_RESPONSE_LINES` is not
being applied — the four bulk lists are still verbatim. Re-check `cap()`.

- [ ] **Step 6: Run verification and confirm every check passes**

Run: `bash /tmp/hero-verify.sh; echo "EXIT=$?"`
Expected: `quirks.md` and `SOURCES.md` report `FAIL missing` (they arrive in
Tasks 2 and 1 Step 7), every other line reads `ok`, `EXIT=1`.

- [ ] **Step 7: Write `SOURCES.md`**

Create `.claude/skills/heropayments/references/SOURCES.md`. Paste the full
generator from Step 3 into the `python` block under `## Regenerating`, and the
full verification script from Step 2 into the `bash` block under `## Verifying`.

````markdown
# Sources

Vendored from the Heropayments Postman collection. Do not hand-edit
`overview.md`, `callbacks.md`, `v2.md`, `custody.md` or `errors.md` — they are
overwritten on regeneration. `quirks.md` is hand-authored and is not touched by
the generator.

| File | Source | Fetched |
|---|---|---|
| `overview.md` | collection `info.description`, minus the callbacks section | 2026-08-11 |
| `callbacks.md` | collection `info.description`, callbacks section | 2026-08-11 |
| `v2.md` | collection folder "V2 Flow" | 2026-08-11 |
| `custody.md` | collection folder "Custody flow" | 2026-08-11 |
| `errors.md` | two Google Sheets, CSV export | 2026-08-11 |

Human-readable page: https://documenter.getpostman.com/view/17469357/UVyvwv7a

Collection JSON (what the generator actually reads):
https://documenter.gw.postman.com/api/collections/17469357/UVyvwv7a?segregateAuth=true&versionTag=latest

Error tables, exported as CSV without authentication:
https://docs.google.com/spreadsheets/d/16R_DIBU_3TIwKG7j6e2Iq6smyWXvSK5kWRYPpyPwym4/export?format=csv&gid=0
https://docs.google.com/spreadsheets/d/16R_DIBU_3TIwKG7j6e2Iq6smyWXvSK5kWRYPpyPwym4/export?format=csv&gid=1669444698

Requires `pandoc`. Python 3 standard library only.

## What the source does not contain

- **No Custody withdrawal callback example.** The overview shows three annotated
  callbacks — V2 deposit, V2 withdrawal, Custody deposit. There is no fourth.
- **No `/v2/payments-address` request.** The endpoint is named in the overview
  under "Static deposit address per each customer" and in the V2 error table,
  but the collection defines no such request: no parameters, no body, no
  response. See `quirks.md` entry 9.
- **Four capped responses.** The supported-cryptocurrency list (1,631 lines),
  the Custody minimums (677), the supported-fiat list (275) and the Custody
  balances (100) are bulk data, not contract shape. They are truncated at 60
  lines with the elision marked in place. The driver reads all four from the
  live API at runtime.

## Overview split boundaries

`callbacks.md` is the region between the headings carrying `id="callbacks"` and
`id="payment-statuses-v2-flow"`; `overview.md` is everything outside it. The
generator exits non-zero if either id is missing, so a renamed section fails
loudly instead of silently emptying a file.

## Regenerating

Save the script below to `/tmp/hero-mirror.py`, then, from the repo root:

    cd .claude/skills/heropayments/references
    python3 /tmp/hero-mirror.py

`git diff` on the result is the changelog Heropayments does not publish. Read it
before committing: a changed field name or status is exactly what this mirror
exists to surface.

```python
<the full generator script from Step 3>
```

## Verifying

Counts were measured on 2026-08-11. A failure after a refresh usually means the
source changed rather than the mirror breaking — read the diff, then update the
expected number.

```bash
<the full verification script from Step 2>
```
````

- [ ] **Step 8: Re-run verification**

Run: `bash /tmp/hero-verify.sh; echo "EXIT=$?"`
Expected: only `FAIL missing quirks.md` remains, `EXIT=1`.

- [ ] **Step 9: Confirm the scripts in `SOURCES.md` are the ones that ran**

```bash
cd .claude/skills/heropayments/references
python3 - <<'PY'
import re
src = open("SOURCES.md").read()
for lang, path in (("python", "/tmp/hero-mirror.py"), ("bash", "/tmp/hero-verify.sh")):
    block = re.search(rf"```{lang}\n(.*?)\n```", src, re.S)
    assert block, f"no {lang} block in SOURCES.md"
    assert block.group(1).strip() == open(path).read().strip(), f"{path} differs from SOURCES.md"
    print(f"ok  {lang} block matches {path}")
PY
cd -
```

Expected: two `ok` lines. This is the one way `SOURCES.md` can rot silently —
an inert code block that no longer matches what was run is worse than no script.

- [ ] **Step 10: Commit**

```bash
git add .claude/skills/heropayments/references/
git commit -m "docs(heropayments): mirror the Postman API contract

Generated from the collection JSON rather than the rendered page, which
is a client-side SPA. Five references: the overview, callbacks split out
for webhook work, both API flows, and the error tables Heropayments
keeps in Google Sheets.

Four bulk-data response examples are capped at 60 lines with the
elision marked; everything else is verbatim, truncated request bodies
and NOWPayments leftovers included."
```

---

### Task 2: Write `quirks.md`

**Files:**
- Create: `.claude/skills/heropayments/references/quirks.md`
- Read only: `src/Drivers/Heropayment/*.php`,
  `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php`
- Scratch (not committed): `/tmp/cite-check.py`

**Interfaces:**
- Consumes: the mirrors from Task 1, as the docs half of every citation.
- Produces: `references/quirks.md` with ten numbered entries. Task 3 links it
  from `SKILL.md` and lifts five of them into the "Before you change anything"
  section.

- [ ] **Step 1: Verify every citation before writing a word**

```bash
cd "$(git rev-parse --show-toplevel)"
while read -r f n; do printf '%-30s %3s: %s\n' "$(basename $f)" "$n" "$(sed -n "${n}p" "$f" | sed 's/^ *//')"; done <<'EOF'
src/Drivers/Heropayment/HeropaymentAdapter.php 78
src/Drivers/Heropayment/HeropaymentAdapter.php 111
src/Drivers/Heropayment/HeropaymentAdapter.php 137
src/Drivers/Heropayment/HeropaymentClient.php 17
src/Drivers/Heropayment/HeropaymentProvider.php 202
src/Drivers/Heropayment/HeropaymentQuoteService.php 167
src/Drivers/Heropayment/HeropaymentQuoteService.php 182
EOF
```

Expected, exactly:

```
HeropaymentAdapter.php            78: 'settlement_actually_paid' => $payload['actuallyPaid'] ?? null,
HeropaymentAdapter.php           111: 'finished', 'sending', 'partially_paid' => PaymentStatus::Succeeded,
HeropaymentAdapter.php           137: 'heropayment_actually_paid' => $payload['actuallyPaid'] ?? null,
HeropaymentClient.php             17: private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
HeropaymentProvider.php          202: $orderId = $payload['externalOrderId'] ?? $payload['orderID'] ?? $payload['orderId'] ?? null;
HeropaymentQuoteService.php      167: $fees[strtolower((string) ($row['ticker'] ?? ''))] = (float) ($row['networkfee'] ?? 0);
HeropaymentQuoteService.php      182: $percent = $this->config['fee_percent'] ?? null;
```

If any line has drifted, find the new line number and use it. Do not write an
entry against a citation you have not seen resolve.

- [ ] **Step 2: Confirm the two absent-field claims still hold**

```bash
cd "$(git rev-parse --show-toplevel)/.claude/skills/heropayments/references"
printf 'actuallyPaid in mirrors:   %s (want 0)\n' "$(grep -c actuallyPaid *.md | awk -F: '{s+=$2} END{print s}')"
printf 'paidAmount in mirrors:     %s (want >0)\n' "$(grep -c paidAmount *.md | awk -F: '{s+=$2} END{print s}')"
printf 'partially_paid in mirrors: %s (want 0)\n' "$(grep -c partially_paid *.md | awk -F: '{s+=$2} END{print s}')"
cd "$(git rev-parse --show-toplevel)"
printf 'sequence in src/:          %s (want 0)\n' "$(grep -rn 'sequence' src/Drivers/Heropayment src/Http/Controllers/Webhooks/HeropaymentWebhookController.php | wc -l | tr -d ' ')"
```

Expected: `0`, a positive number, `0`, `0`. These four numbers are the whole
evidential basis for entries 1, 2 and 3. If any has changed, the entry changes
with it.

- [ ] **Step 3: Write the file**

Create `.claude/skills/heropayments/references/quirks.md`:

````markdown
# Quirks

Where the live Heropayments contract departs from its documentation, and where
the docs are silent. Each entry cites both sides: a passage in a mirror in this
directory, and a line in this repo.

Entries marked **Unverified** describe behaviour that may be wrong in
production. They are recorded rather than fixed, because settling them needs
production callback logs rather than a document search. Each names what would
settle it.

## 1. Repeat deposits reuse `externalOrderId`

**Docs** (`overview.md`, "Multiple deposit processing" and "Static deposit
address per each customer"): a deposit address is generated per unique
`customerId` and reused forever. When a customer sends to a saved address
without creating an order, Heropayments raises a *new payment* carrying the
**same `externalOrderId`** with a fresh `sequence` value, and posts its callback
to the original payment's `callbackUrl`. "Automated mistaken deposits
processing" does the same with `sequence: original` and a changed `payCurrency`.

**Here:** `HeropaymentProvider.php:200-205` resolves our transaction from
`externalOrderId` alone, and `sequence` is read nowhere under
`src/Drivers/Heropayment/`. A second deposit therefore arrives addressed to the
first deposit's transaction. We pass the MT5 trading account login as
`customerId` (`HeropaymentProvider.php:109-114`), so every account holds one
permanent deposit address for as long as it exists — this is reachable by any
customer who scrolls back to an old deposit screen.

**Unverified:** whether any repeat deposit has actually arrived. Search
production callback logs for two payloads sharing an `externalOrderId` with
differing `sequence`.

## 2. `actuallyPaid` is not a Heropayments field

**Docs** (`callbacks.md`, all three callback examples; `v2.md`, "Payment status
check by id (V2)"): the amount actually received is `paidAmount`. The string
`actuallyPaid` does not occur anywhere in the collection.

**Here:** `HeropaymentAdapter.php:78` and `HeropaymentAdapter.php:137` both read `actuallyPaid`, so
`settlement_actually_paid` and `heropayment_actually_paid` are never populated —
the reconcile-to-what-arrived path has no figure to work from.

How it got there: the docs still link `support@nowpayments.io` from the
callbacks section (`callbacks.md`, step 6). Heropayments' documentation is a
NOWPayments fork, and `actually_paid` is NOWPayments' spelling.

**Unverified:** whether Heropayments also sends an undocumented `actuallyPaid`
alongside `paidAmount`. Grep a production callback payload for both.

## 3. `partially_paid` is not a documented status

**Docs** (`v2.md`, "Payment status check by id (V2)"): the V2 status vocabulary
is `waiting`, `confirming`, `exchanging`, `sending`, `finished`, `failed`,
`refunded`, `hold`, `expired`. Nine values, no `partially_paid`.

**Here:** `HeropaymentAdapter.php:111` maps `partially_paid` to `Succeeded`, and
`HeropaymentAdapter.php:81-83` flags it for admin attention. Another
NOWPayments name.

**Unverified:** whether the status is emitted at all. Search production
callbacks for `"status":"partially_paid"`. If it never appears, the mapping is
dead code; if it does, the docs are incomplete and this entry stays.

## 4. Signature payload encoding is only specified for JavaScript

**Docs** (`overview.md`, "API Request signing"): normalize the JSON before
signing — no spaces, no newlines, no zero-padded numbers — with
`JSON.stringify(JSON.parse(x))`. What that implies outside JavaScript is left
unsaid: Node leaves `/` and non-ASCII unescaped, while PHP's `json_encode`
escapes both by default. Sign PHP's default output and every request returns
`401 Invalid signature`.

**Here:** `HeropaymentClient.php:17` pins
`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, and the same encoding is
reused to verify callbacks (`HeropaymentProvider.php:187-198`).

## 5. `networkfee` is documented in USDT and delivered in native units

**Docs** (`v2.md`, "Network fees (V2)"): the value is described as a USDT
equivalent.

**Here:** live values read as native currency units — `btc` returns `0.000007`,
which is about $0.75 as BTC and meaningless as USDT.
`HeropaymentQuoteService.php:156-171` treats them as native units and
`HeropaymentClient.php:88-94` records the discrepancy at the call site. Confirm
with Heropayments before showing a converted figure to customers.

## 6. Invoice id is not payment id

**Docs** (`callbacks.md`, V2 deposit callback): of `invoice.id` — "not
recommended to use it as payment identificator, as it does not appear in the
report". The create-invoice response returns that invoice id as its top-level
`id`; callbacks carry a different payment id.

**Here:** `HeropaymentProvider.php:80-92` returns `externalOrderId` as the
transaction id and keeps the invoice id in metadata, so correlation runs on the
one identifier both sides agree on.

## 7. No endpoint exposes the contracted `feePercent`

**Docs:** `feePercent` appears on a created payment and on status callbacks
(`callbacks.md`). No endpoint in `v2.md` or `custody.md` returns the merchant's
contracted rate.

**Here:** `HeropaymentQuoteService.php:173-185` reads it from connection config,
because a quote must be shown before any payment exists. That configured value
is an assertion about a commercial agreement, not a fact from the API —
reconcile it against `feePercent` on incoming callbacks.

## 8. The widget takes no currency allow-list

**Docs** (`v2.md`, "Create an invoice (widget)" and "Create an invoice (widget -
chosen currency)"): `payCurrency` is optional and singular. There is no
parameter for offering a subset.

**Here:** `HeropaymentProvider.php:116-135` pins exactly one currency, chosen by
the customer from the connection's `currencies` list in our own deposit modal.
Left null, the widget lists everything Heropayments supports — including
currencies we never quoted a minimum for.

## 9. `/v2/payments-address` exists in prose only

**Docs:** named in `overview.md` under "Static deposit address per each
customer" as one of the V2 deposit-creating methods, and again in the V2 error
table (`errors.md`). The collection defines no such request — no parameters, no
body, no response example.

**Here:** nothing. Recorded so the next person to find the name in the error
table does not go looking for a contract that was never published.

## 10. Custody's status vocabulary is disjoint from V2's

**Docs** (`custody.md`, "Payment status check by id (Custody)";
`overview.md`, "Payments statuses - (Custody flow)"): Custody uses `new`,
`pending`, `processing`, `finished`, `failed`, `refunded`, `hold`, `expired`.
Three of those — `new`, `pending`, `processing` — do not exist in V2.

**Here:** `HeropaymentAdapter::mapStatus` (`HeropaymentAdapter.php:110-117`)
knows only the V2 names, so all three Custody in-progress statuses fall through
`default => Pending`.
`processing` in particular would report as Pending rather than Processing.

Latent, not live: nothing in this package calls `/custody`. Recorded because the
Custody mirror now ships beside it, and the first person to switch flows will
not otherwise see this.
````

- [ ] **Step 4: Verify every citation in the finished file resolves**

Save as `/tmp/cite-check.py` and run it from anywhere in the repo:

```python
#!/usr/bin/env python3
"""Check that every file:line citation in quirks.md resolves, and show it."""
import pathlib
import re
import subprocess
import sys

root = pathlib.Path(subprocess.run(
    ["git", "rev-parse", "--show-toplevel"],
    capture_output=True, text=True, check=True).stdout.strip())
quirks = root / ".claude/skills/heropayments/references/quirks.md"
sources = {p.name: p for p in (root / "src").rglob("*.php")}

status = 0
seen = set()
for name, first, last in re.findall(
        r"`(Heropayment[A-Za-z]*\.php):(\d+)(?:-(\d+))?`", quirks.read_text()):
    key = (name, first, last)
    if key in seen:
        continue
    seen.add(key)

    path = sources.get(name)
    if path is None:
        print(f"FAIL {name}: no such file under src/")
        status = 1
        continue

    lines = path.read_text().splitlines()
    end = int(last or first)
    if end > len(lines):
        print(f"FAIL {name}:{first}-{end} past EOF ({len(lines)} lines)")
        status = 1
        continue

    span = f"{first}-{last}" if last else first
    print(f"ok   {name}:{span:<8} {lines[int(first) - 1].strip()[:64]}")

sys.exit(status)
```

Run: `python3 /tmp/cite-check.py; echo "EXIT=$?"`

Expected: 14 `ok` lines and `EXIT=0`. Read the printed source lines rather than
just counting them — the check proves a line exists, but only you can see it is
the line the entry is talking about. `HeropaymentAdapter.php:78` should print
`'settlement_actually_paid' => $payload['actuallyPaid'] ?? null,`, and
`HeropaymentProvider.php:200-205` should print
`public function extractWebhookTransactionId(...)`.

This is in Python rather than shell on purpose: the equivalent `find`/`wc` loop
breaks in this environment, where `find` is a shell function that does not
survive a pipeline subshell.

- [ ] **Step 5: Confirm the docs half of each citation resolves too**

```bash
cd "$(git rev-parse --show-toplevel)/.claude/skills/heropayments/references"
grep -c 'Multiple deposit processing' overview.md
grep -c 'support@nowpayments.io' callbacks.md
grep -c 'not recommended to use it as payment identificator' callbacks.md
grep -c 'payments-address' overview.md errors.md
```

Expected: each returns at least 1. These are the passages entries 1, 2, 6 and 9
quote; if a refresh has removed one, the entry needs rewriting, not re-citing.

- [ ] **Step 6: Commit**

```bash
git add .claude/skills/heropayments/references/quirks.md
git commit -m "docs(heropayments): record ten divergences from the contract

Four are potentially live and marked Unverified: repeat deposits reuse
externalOrderId and we never read sequence; the adapter reads a field
name (actuallyPaid) that does not exist in the contract; partially_paid
is not a documented status; and Custody's vocabulary falls through our
V2-only status map.

Both undocumented names are NOWPayments spellings, and the Heropayments
docs still link support@nowpayments.io — they are a fork."
```

---

### Task 3: Write `SKILL.md` and verify end to end

**Files:**
- Create: `.claude/skills/heropayments/SKILL.md`

**Interfaces:**
- Consumes: all six files in `references/`.
- Produces: the skill entry point. Nothing depends on it.

- [ ] **Step 1: Write `SKILL.md`**

Create `.claude/skills/heropayments/SKILL.md`:

````markdown
---
name: heropayments
description: Use when working with the Heropayments crypto gateway (api.heropayments.io) — the Heropayment driver, HeropaymentClient/HeropaymentProvider/HeropaymentAdapter/HeropaymentQuoteService, Heropayment callbacks and webhooks, or Heropayment crypto deposits, invoices, rate and minimum-amount quotes, withdrawals and balances. Covers both the V2 and Custody API flows.
---

# Heropayments

Heropayments publishes no OpenAPI spec, no changelog and no API versioning —
only a Postman documenter page. This skill vendors the collection so the
contract is available offline and upstream changes show up as diffs.

## References

Read the one you need; they are large.

- `references/quirks.md` — **start here.** Where the live API departs from its
  own docs, and where the docs are silent. Ten entries, each citing the code in
  this package that handles it. Four are marked **Unverified**: they may be
  live defects.
- `references/overview.md` — auth, HMAC-SHA512 request signing with worked
  samples in five languages, both integration flows, the two status
  vocabularies, multiple-deposit and mistaken-deposit processing, static
  deposit addresses.
- `references/callbacks.md` — callback mechanics and the three annotated
  payloads. The file to open for webhook work.
- `references/v2.md` — the 12 V2 requests. This is the flow the driver uses.
- `references/custody.md` — the 8 Custody requests. Nothing here calls them.
- `references/errors.md` — both error-code tables, V2 and Custody.
- `references/SOURCES.md` — source URLs, fetch date, and the commands to
  regenerate and verify the mirrors.

## Before you change anything

Five things that will otherwise cost time:

1. **A saved deposit address keeps working forever.** Addresses are static per
   `customerId` — we pass the MT5 login — and a repeat send raises a new payment
   with the **same `externalOrderId`** and a new `sequence`. We resolve
   transactions by `externalOrderId` alone and never read `sequence`, so the
   second deposit lands on the first one's transaction. `quirks.md` entry 1.
2. **The field is `paidAmount`, not `actuallyPaid`.** The adapter reads a name
   that appears nowhere in the contract. `quirks.md` entry 2.
3. **Sign the body exactly as Node's `JSON.stringify` would emit it.** PHP's
   default `json_encode` escapes slashes and unicode; both yield
   `401 Invalid signature`. `quirks.md` entry 4.
4. **The id in the create-invoice response is not the id in the callback.** One
   is an invoice, the other a payment. Correlate on `externalOrderId`.
   `quirks.md` entry 6.
5. **V2 and Custody have disjoint status vocabularies.** `waiting`/`confirming`/
   `exchanging`/`sending` against `new`/`pending`/`processing`. Our status map
   knows only the V2 half. `quirks.md` entry 10.

## The code

- `src/Drivers/Heropayment/HeropaymentClient.php` — HTTP surface, signing,
  signature verification
- `src/Drivers/Heropayment/HeropaymentProvider.php` — charge, retrieve,
  webhook correlation
- `src/Drivers/Heropayment/HeropaymentAdapter.php` — response/callback →
  cashier-core objects, status mapping
- `src/Drivers/Heropayment/HeropaymentQuoteService.php` — pre-redirect rate,
  network fee and minimum lookups, with caching
- `src/Drivers/Heropayment/HeropaymentQuote.php` — the quote value object
- `src/Http/Controllers/Webhooks/HeropaymentWebhookController.php` — callback
  entry point, signature check, replay guard

Refunds, capture, authorize and void all throw: Heropayments has no
merchant-initiated refund.

## Refreshing

Follow `## Regenerating` in `references/SOURCES.md`, then read `git diff` — that
diff is the changelog Heropayments does not publish. `quirks.md` is hand-written
and is not regenerated; check whether any entry the diff touches still holds.
````

- [ ] **Step 2: Check the frontmatter and length**

```bash
cd "$(git rev-parse --show-toplevel)"
head -4 .claude/skills/heropayments/SKILL.md
wc -l .claude/skills/heropayments/SKILL.md
```

Expected: `---`, a `name: heropayments` line, a `description:` line on one
physical line, `---`; and a total under 80 lines.

- [ ] **Step 3: Full verification passes for the first time**

Run: `bash /tmp/hero-verify.sh; echo "EXIT=$?"`
Expected: every line `ok`, no `FAIL`, `EXIT=0`.

- [ ] **Step 4: Confirm every reference `SKILL.md` names exists**

```bash
cd "$(git rev-parse --show-toplevel)/.claude/skills/heropayments"
grep -o 'references/[a-zA-Z0-9_.-]*\.md' SKILL.md | sort -u |
  while read -r f; do [ -f "$f" ] && echo "ok   $f" || echo "FAIL $f missing"; done
```

Expected: six `ok` lines, no `FAIL`. A skill that routes to a file that isn't
there is worse than one that routes nowhere.

- [ ] **Step 5: Confirm the whole spec is satisfied**

Walk the nine numbered points in `## Verification` of
[the spec](../specs/2026-08-11-heropayments-skill-design.md) and confirm each.
Points 2 through 7 are covered by Step 3 above; check points 1, 8 and 9 by hand
— `SKILL.md` length and frontmatter, every `quirks.md` citation opened, and both
scripts run clean from the repo root.

- [ ] **Step 6: Commit**

```bash
git add .claude/skills/heropayments/SKILL.md
git commit -m "docs(heropayments): add the skill entry point

Routes to the six references and leads with the five traps worth knowing
before touching the driver — chief among them that a saved deposit
address keeps producing payments under the original externalOrderId."
```

---

## After the plan

Four `quirks.md` entries — 1, 2, 3 and 10 — describe behaviour that may be wrong
in production. Settling them needs production callback logs, not more reading:

- Entry 1: search for two callbacks sharing an `externalOrderId` with differing
  `sequence`. If any exist, deposits have been silently collapsing into one
  transaction.
- Entry 2: check whether any callback carries `actuallyPaid`. If none does,
  `HeropaymentAdapter.php:78` and `:137` should read `paidAmount`.
- Entry 3: check whether `partially_paid` is ever emitted.
- Entry 10 stays latent until something calls `/custody`.

Each is a separate, testable change with its own commit. None belongs in this
plan, which adds no behaviour.
