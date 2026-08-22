---
name: docsmith-development
description: "Use for tasks involving mrpunyapal/docsmith: building static documentation sites from Markdown, the Docsmith::build()/make() API, the vendor/bin/docsmith CLI, frontmatter keys, versioned docs, docs hubs, remote source syncing, llms.txt export, and Open Graph images. Do not use for general static site generators unrelated to Docsmith."
license: MIT
metadata:
  author: mrpunyapal
---

# Docsmith Development

Docsmith turns a directory of Markdown into a self-contained static documentation site (sidebar navigation, search, dark mode, code copy). Requires PHP 8.3+. It is framework-agnostic — no Laravel or Illuminate dependency.

## Install

```bash
composer require --dev mrpunyapal/docsmith
```

## Three entry points, one option model

```php
// 1. Static API
Docsmith::build(source: __DIR__.'/md', output: __DIR__.'/docs', title: 'Docs');

// 2. Fluent builder
Docsmith::make()
    ->source(__DIR__.'/md')
    ->output(__DIR__.'/docs')
    ->title('Project Docs')
    ->accentColor('#1d4ed8')     // hex; hover/dark variants derive from it
    ->repositoryUrl('https://github.com/acme/project') // enables edit links
    ->siteUrl('https://acme.github.io/project')
    ->rightSidebar()
    ->build();

// 3. CLI (thin wrapper; every flag maps to a fluent method)
vendor/bin/docsmith build --source=md --output=docs --title="Project Docs"
```

Common options: `output` (default `docs`), `description`, `accentColor`, `accentColorDark`, `customCss`, `baseUrl`, `repositoryUrl`, `siteUrl`, `editBranch`, `showDocsmithBadge(false)`, `navigationOrder([...])`.

## Output model

- `md/index.md` → `index.html`; `md/installation.md` → `installation/index.html`
- Missing `index.md` → landing page generated automatically
- Always generates `search-index.json`, `sitemap.xml`, `.nojekyll`
- LLM export is on by default: `llms.txt`, `llms-full.txt`, `export/docs.md` (`->llmsExport(false)` to disable). `siteUrl()` is required for correct absolute URLs in these exports.

## Frontmatter keys

`title`, `description`, `slug`, `order`, `sidebar_label`, `hidden: true` (excludes page from nav, search index, and pagination but keeps the URL live), plus OG overrides `og_image`, `og_title`, `og_description`.

## Versions and hubs

- **Versions**: `->versions([['slug'=>'v2','label'=>'v2.0','default'=>true], ...])`. Each version reads `md/{slug}/`; the default version owns the site root, others nest under `{slug}/`.
- **Hub** (multiple independent doc sets + sidebar dropdown): `->hub(['pkg' => ['label' => ..., 'source' => ..., 'navigation' => [...], 'versions' => [...]]])`. Entries mount under their slug; `/` forwards to the first entry.

## Remote sources (git sync without the git binary)

Declare sources in `docsmith.sources.php`, then sync and build:

```php
return [
    ['repository' => 'https://github.com/acme/pkg.git', 'ref' => 'main', 'path' => 'docs', 'target' => 'pkg'],
];
```

```bash
php bin/docsmith sync              # fetch/materialize under md/
php bin/docsmith build --sync      # or sync+build in one step
# extra flags: --sources=FILE --force --verify --md=DIR
```

Commit `docsmith.sources.lock.json` so repeat syncs are incremental; delete it to force a full refresh. Sync failures exit non-zero (CI-safe).

Private repositories: add `'token' => '${ACME_PAT}'` (resolved from the environment; a missing variable is a config error naming it) and optional `'username'`. Without a token key, fallbacks apply: `DOCSMITH_TOKEN` works for any host; `GITHUB_TOKEN` / `GH_TOKEN` are used only for github.com hosts and never sent elsewhere. Never commit real tokens.

## Open Graph images

- `->ogGeneratedAll()` one shared card, `->ogGeneratedPerPage()` per-page cards (needs `npm i -D playwright capturist@^0.1` + `npx playwright install chromium`; Docsmith writes the capturist config)
- `->ogLink(url)` to point at an existing image
- Always set `siteUrl()` so crawlers get absolute `og:image` URLs
- Incremental capture via capturist cache; `->forceOg()` recaptures everything

## Developing inside this repository

```bash
composer test          # lint (rector+pint) + phpstan + pest
composer test:lint     # rector --dry-run && pint --test
composer test:types    # phpstan
composer test:unit     # pest --parallel
composer docs:build    # regenerate docs/ from md/ using Docsmith itself
```

Pipeline: `Docsmith` (API) → `Builder`/`BuildConfig` (config validation) → `SourceScanner` → `CommonMarkRenderer` → `SiteBuilder` (pages, hub dropdown, version pills) → `AssetPublisher` (search-index, sitemap, llms export). Remote syncing lives in `src/RemoteSources/*`.

## Gotchas

- PHP 8.3 minimum; no Laravel required, so never add Illuminate imports when fixing issues.
- Prefer hex colors for accents — named colors break variant derivation.
- Versioned builds: pages existing only in a non-default version are NOT duplicated to root.
- Hub entries with `versions`: the primary version mounts at the entry slug, siblings nest under `{entry}/{version}/`.
