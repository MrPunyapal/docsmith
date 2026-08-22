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

You do not write a capturist config. Docsmith generates it during the build.

## One image for every page

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/docs')
    ->title('My Package Docs')
    ->siteUrl('https://example.com/docs')
    ->ogGeneratedAll()
    ->build();
```

This writes `docs/og/cover.png` and points every page at it.

Always set `siteUrl()` so crawlers get absolute `og:image` URLs:

- With `siteUrl()`: `og:image` is an absolute URL, which every crawler resolves correctly.
- Without `siteUrl()` but with a subpath `baseUrl()` (for example `/docs`): Docsmith emits a root-relative path (`/docs/og/cover.png`) so crawlers resolve it against the host root instead of the page.
- With neither: `og:image` is page-relative and may break scrapers on subpath hosts because they resolve against the domain root and drop the subpath.

## One image per page

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->siteUrl('https://example.com/docs')
    ->ogGeneratedPerPage()
    ->build();
```

Each page gets its own preview at `og/<page>.png`.

## Link an existing image

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->ogLink('https://example.com/og/cover.png')
    ->build();
```

The URL can be absolute or root-relative.

## Custom card template

Pass a file path or raw HTML. Tokens `{site_title}`, `{title}`, `{description}`, and `{accent_color}` are replaced per page. The template renders inside a 1200x630 shell, so you only write the card markup:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->siteUrl('https://example.com/docs')
    ->ogTemplate(__DIR__ . '/og-card.html', scope: 'per-page')
    ->build();
```

For full control, `ogImage(...)` exposes every option directly:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->ogImage(
        type: 'generated',
        scope: 'per-page',
        template: __DIR__ . '/og-card.html',
        scale: 2,
        viewport: ['width' => 1200, 'height' => 630],
    )
    ->build();
```

## Frontmatter overrides

Individual pages can override the image, title, or description used in their tags:

```md
---
og_image: /assets/page-og.png
og_title: Custom Social Title
og_description: Custom social description.
---
```

## Capture control

Capture runs during `build()` when a generated mode is enabled. It is incremental through the capturist cache: unchanged cards are skipped, and rebuilds print `Open Graph images up to date`.

| Method | Purpose |
|--------|---------|
| `captureOg(false)` | Write previews and `capturist.config.json` only; skip screenshots |
| `forceOg()` | Recapture everything, ignoring the capturist cache |
| `runCapturist(false)` | Deprecated alias of `captureOg(false)` |

To force a full regeneration without the method, delete `og/.capturist-cache.json`.

If capture is enabled but Node, capturist, or Chromium is missing, the build fails with install instructions.

## CI

Install Node dependencies and Chromium before a docs build that runs capture:

```yaml
- uses: actions/setup-node@v4
  with:
    node-version: '20'
    cache: 'npm'

- run: npm install
- run: npx playwright install chromium --with-deps
- run: php build-docs.php
```

To split the steps, call `captureOg(false)` in the HTML job, then capture later with `npx capturist --cwd docs --config capturist.config.json`.
