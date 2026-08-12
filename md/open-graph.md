---
order: 5
sidebar_label: Open Graph
---

# Open Graph Images

Docsmith can emit `og:` / `twitter:` meta tags and generate social preview images during the docs build.

## Install capture tools (once)

Generated images need Node, Playwright, and capturist:

```bash
npm install -D playwright capturist@^0.1.3
npx playwright install chromium
```

You do not write a capturist config — Docsmith generates it.

## Single image for every page

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/docs')
    ->title('My Package Docs')
    ->siteUrl('https://example.com/docs')
    ->ogGeneratedAll()
    ->build();
```

Writes `docs/og/cover.png` and points every page at it.

Always set `siteUrl()` so crawlers get absolute `og:image` URLs:

- With `siteUrl()`: `og:image` is an absolute URL, which every crawler resolves correctly.
- Without `siteUrl()` but with a subpath `baseUrl()` (e.g. `/docs`): Docsmith emits a root-relative path (`/docs/og/cover.png`) so crawlers resolve it against the host root instead of the broken page-relative default.
- Without either: `og:image` is page-relative and may break scrapers on subpath hosts (they resolve against the domain root and drop the subpath).

## One image per page

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->siteUrl('https://example.com/docs')
    ->ogGeneratedPerPage()
    ->build();
```

## Link an existing image

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->ogLink('https://example.com/og/cover.png')
    ->build();
```

## Custom card template

Tokens: `{site_title}`, `{title}`, `{description}`, `{accent_color}`.

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->siteUrl('https://example.com/docs')
    ->ogTemplate(__DIR__ . '/og-card.html', scope: 'per-page')
    ->build();
```

## Frontmatter overrides

```md
---
og_image: /assets/page-og.png
og_title: Custom Social Title
og_description: Custom social description.
---
```

## Capture control

| Method | Purpose |
|--------|---------|
| `captureOg(false)` | Write previews + config only; skip screenshots |
| `forceOg()` | Recapture everything (ignore capturist cache) |
| `runCapturist(false)` | Deprecated alias of `captureOg(false)` |

Capture is incremental via capturist. Unchanged cards are skipped; force regen with `forceOg()` or by deleting `og/.capturist-cache.json`.

## CI

Install Node deps and Chromium before a docs build that runs capture:

```yaml
- uses: actions/setup-node@v4
  with:
    node-version: '20'
    cache: 'npm'

- run: npm install
- run: npx playwright install chromium --with-deps
- run: php build-docs.php
```

Split builds: `->captureOg(false)` in the HTML job, capture later with `npx capturist --cwd docs --config capturist.config.json`.

## Future scope

Shipped today: generated cards, meta tags, frontmatter overrides, capturist incremental cache, install/CI guidance.

Possible later work (not committed to a timeline):

- CI smoke job that runs a real Playwright capture once
- Stronger tests for versioned docs + OG paths
- Optional hard-fail when `siteUrl` is missing for generated OG
- Default-card logo / simple layout presets
- Cleaner published output (e.g. ignore `og/preview/` HTML, keep PNGs)

Non-goals for now: mid-build auto-install of Node tools, PHP-side cache, or requiring consumers to hand-write capturist config.
