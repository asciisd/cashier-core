# Sources

Vendored from the APS merchant documentation. Do not hand-edit `h2h.md` or
`fpf-v3.md` except inside the `<!-- source: … -->` transcription blocks —
everything else is overwritten on regeneration.

| File | Source URL | Fetched |
|---|---|---|
| `h2h.md` | https://merchant.aps.money/developers/docs/h2h/integration | 2026-10-15 |
| `fpf-v3.md` | https://merchant.aps.money/developers/docs/fpf-v3/integration | 2026-10-15 |

Generator: Docusaurus v3.0.1. Requires `pandoc` and Python `beautifulsoup4`.

Fenced blocks inside a blockquote (the `[DEPRECATED]` legacy-auth examples)
carry a `> ` prefix and are deliberately not counted by the verifier below.

**Not mirrored:** `https://merchant.aps.money/developers/docs/new-documentation/integration`
("Payment methods documentation"). The page server-renders a title and nothing
else; its body is client-rendered and unreachable without a browser. Re-check it
on each refresh in case APS starts server-rendering it.

Also not mirrored, as they hold no content: `docs/intro`, `docs/category/*`,
`markdown-page`, and `/`.

## Regenerating

Save the script below to `/tmp/aps-mirror.py`, then, from the repo root:

    cd .claude/skills/aps-payments/references
    python3 /tmp/aps-mirror.py https://merchant.aps.money/developers/docs/h2h/integration h2h.md
    python3 /tmp/aps-mirror.py https://merchant.aps.money/developers/docs/fpf-v3/integration fpf-v3.md

Then re-apply the diagram transcriptions — regeneration restores the plain
image links. `git diff` shows both what APS changed and which transcriptions
need redoing.

```python
#!/usr/bin/env python3
"""Mirror an APS Docusaurus doc page to clean GitHub-flavoured markdown."""
import subprocess
import sys
import urllib.request

from bs4 import BeautifulSoup

url, out_path = sys.argv[1], sys.argv[2]

html = urllib.request.urlopen(url).read().decode("utf-8")
article = BeautifulSoup(html, "html.parser").find("article")
if article is None:
    sys.exit(f"no <article> element at {url}")

# Chrome that carries no information: breadcrumbs, heading anchors,
# copy-to-clipboard controls, and the mobile TOC (duplicates the headings).
for selector in (
    'nav[class*="breadcrumb"]',
    "ul.breadcrumbs",
    "a.hash-link",
    'div[class*="buttonGroup"]',
    'div[class*="tocCollapsible"]',
):
    for node in article.select(selector):
        node.decompose()

# Presentational attributes on links and images make pandoc bail out to raw
# HTML. Reduced to href/src/alt, they convert to real markdown.
for node in article.find_all("a"):
    node.attrs = {k: v for k, v in node.attrs.items() if k == "href"}
for node in article.find_all("img"):
    node.attrs = {k: v for k, v in node.attrs.items() if k in ("src", "alt")}

# Code block titles are load-bearing ("Initiate transaction", "Go") — keep the
# text as a bold caption before unwrapping the containers around it.
for node in article.select('div[class*="codeBlockTitle"]'):
    caption = BeautifulSoup("", "html.parser").new_tag("p")
    strong = BeautifulSoup("", "html.parser").new_tag("strong")
    strong.string = node.get_text(strip=True)
    caption.append(strong)
    node.replace_with(caption)

# Collapsible language tabs ("bash / zsh", "PHP", ...): keep the summary as a
# bold caption, then let the content stand on its own.
for node in article.find_all("details"):
    summary = node.find("summary")
    caption = BeautifulSoup("", "html.parser").new_tag("p")
    strong = BeautifulSoup("", "html.parser").new_tag("strong")
    strong.string = summary.get_text(strip=True) if summary else ""
    caption.append(strong)
    if summary:
        summary.replace_with(caption)
    for wrapper in node.find_all("div", recursive=False):
        for inner in wrapper.select('div[class*="collapsibleContent"]'):
            inner.unwrap()
        wrapper.unwrap()
    node.unwrap()

# Admonitions ("DEPRECATED!") become a blockquote led by their heading; the
# inline SVG icon would otherwise survive as a base64 data URI.
for node in article.select('div[class*="theme-admonition"]'):
    heading = node.select_one('div[class*="admonitionHeading"]')
    content = node.select_one('div[class*="admonitionContent"]')
    quote = BeautifulSoup("", "html.parser").new_tag("blockquote")
    lead = BeautifulSoup("", "html.parser").new_tag("p")
    strong = BeautifulSoup("", "html.parser").new_tag("strong")
    strong.string = heading.get_text(strip=True) if heading else ""
    lead.append(strong)
    quote.append(lead)
    if content:
        for child in list(content.children):
            quote.append(child.extract())
    node.replace_with(quote)

# Unwrap the presentational scaffolding around <pre>, innermost first.
for selector in (
    'div[class*="codeBlockContent"]',
    'div[class*="codeBlockContainer"]',
    'div[class*="theme-code-block"]',
    'div[class*="language-"]',
    'div[class*="theme-doc-markdown"]',
):
    for node in article.select(selector):
        node.unwrap()

result = subprocess.run(
    ["pandoc", "-f", "html", "-t", "gfm", "--wrap=none"],
    input=str(article), capture_output=True, text=True, check=True,
)
with open(out_path, "w") as handle:
    handle.write(result.stdout)
print(f"wrote {out_path}")
```

## Verifying

````bash
#!/usr/bin/env bash
# Verifies the APS mirrors against counts measured from the live pages.
set -u
cd "$(git rev-parse --show-toplevel)/.claude/skills/aps-payments/references" || exit 1
status=0
check() { # name actual expected
  if [ "$2" = "$3" ]; then printf '  ok   %-22s %s\n' "$1" "$2"
  else printf '  FAIL %-22s got %s want %s\n' "$1" "$2" "$3"; status=1; fi
}
# Fields: name, table headers, code fences, diagram slots. The fence counts are
# 60/56 converted from the source pages + 2 mermaid diagram transcriptions each.
for spec in "h2h 18 62 4" "fpf-v3 15 58 3"; do
  set -- $spec
  f="$1.md"
  echo "$f"
  if [ ! -f "$f" ]; then echo "  FAIL missing"; status=1; continue; fi
  check "raw html lines" "$(grep -c '^<[a-z]' "$f")" 0
  check "table headers"  "$(grep -cE '^\|[-: |]+\|$' "$f")" "$2"
  check "code fences"    "$(( $(grep -c '^```' "$f") / 2 ))" "$3"
  check "diagram slots"  "$(( $(grep -c '^!\[' "$f") + $(grep -c '^<!-- source:' "$f") ))" "$4"
done
grep -q 'crypto/hmac' h2h.md   || { echo "FAIL h2h.md: go hmac sample missing"; status=1; }
grep -q 'crypto/hmac' fpf-v3.md || { echo "FAIL fpf-v3.md: go hmac sample missing"; status=1; }
exit $status
````
