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
```

## Verifying

Counts were measured on 2026-08-11. A failure after a refresh usually means the
source changed rather than the mirror breaking — read the diff, then update the
expected number.

```bash
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
```
