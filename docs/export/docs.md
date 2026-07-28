# Architecture

# Architecture

## Current pipeline

The current implementation is intentionally small.

1. `Docsmith` exposes the public API.
2. `Builder` collects configuration (versions, llms-export, readme-index, theme, etc.).
3. `BuildConfig` validates source and output paths.
4. `SourceScanner` discovers Markdown files (respects per-version source directories).
5. `CommonMarkRenderer` converts Markdown into HTML.
6. `SiteBuilder` writes HTML pages, version switcher, search overlay, and publishes CSS/JS assets.
7. `AssetPublisher` generates `search-index.json`, `sitemap.xml`, `.nojekyll`, `llms.txt`, `llms-full.txt`, and `export/docs.md`.

## Current source model

Every discovered Markdown file is normalized into a `Document` object containing:

- source path
- relative path
- output path
- title
- raw Markdown
- rendered HTML

## Current renderer

The current renderer produces:

- a sidebar navigation
- a main content area
- a generated landing page when needed
- local CSS under `assets/app.css`

This is the minimal implementation baseline, not the final architecture.


---

# Development

# Development

## Quality commands

```bash
composer test:lint
composer test:types
composer test:unit
composer test
```

## Tooling

The repository is configured with:

- Pest for tests
- PHPStan for static analysis
- Rector for automated refactoring
- Pint for formatting

## Build the package docs

```bash
composer docs:build
```

That command uses Docsmith itself to read Markdown from `md/` and regenerate the documentation site into `docs/`.

## CI / GitHub Actions

The repository includes a workflow at `.github/workflows/docs.yml` that builds and commits `docs/` on every push that changes the source markdown or build script.

For your own projects using Docsmith, here is a CI pattern you can adapt:

```yaml
name: Build docs

on:
  push:
    branches: [main]

permissions:
  contents: write

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer:v2

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Generate docs
        run: php build-docs.php

      - name: Commit generated docs
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git add docs
          if git diff --cached --quiet; then
            echo "No changes to commit"
          else
            git commit -m "chore: regenerate docs [skip ci]"
            git push
          fi
```

Adjust the PHP version, source paths, and build command to match your project.


---

# Docsmith

# Docsmith

Docsmith is a small PHP package for turning Markdown files into a static documentation site.

## Current capabilities

- Build a multi-page documentation site from a Markdown directory.
- Generate one HTML page per Markdown file.
- Publish local CSS assets into the output directory.
- Publish local JS assets for search, theme toggle, and code-copy UX.
- Support both a static entry point and a fluent builder API.
- Render Markdown through League CommonMark with GitHub-flavored extensions.
- Parse frontmatter metadata (`title`, `description`, `slug`, `order`, `sidebar_label`, `hidden`).
- Hide pages from navigation, search, and pagination via frontmatter `hidden: true`.
- Generate `search-index.json`, `sitemap.xml`, and `.nojekyll`.
- Support repository/edit links and previous/next page navigation.
- Build multiple documentation versions with a version switcher.
- Search overlay with `Cmd+K` / `Ctrl+K` keyboard shortcut.
- AI-consumable export: `llms.txt`, `llms-full.txt`, `export/docs.md`.
- Validate the package with Pest, PHPStan, Rector, and Pint.

## Current status

Docsmith is actively used to generate documentation for multiple packages and supports static-hosting workflows out of the box.

Search includes both:

- sidebar link filtering
- global index search powered by generated `search-index.json`
- overlay modal with keyboard shortcut

## Documentation pages

- Installation
- Usage
- Architecture
- Development
- Versioned Docs
- LLM Export


---

# Installation

# Installation

## Requirements

- PHP 8.3 or newer
- Composer

## Install the package

```bash
composer require mrpunyapal/docsmith
```

## Build documentation

Docsmith can build a static site from any Markdown directory.

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/docs',
    title: 'Docsmith',
    description: 'Craft static documentation sites from Markdown with minimal setup.',
);
```

That setup keeps the Markdown source in `md/` and writes the generated site into `docs/`. The main entry page is written to `docs/index.html`.


---

# LLM Export

# LLM Export

Docsmith can generate AI-consumable exports of your documentation for use with LLMs and AI agents.

## Enabling the export

Export is **enabled by default**. Disable it with:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->siteUrl('https://acme.github.io/project')
    ->llmsExport(false)
    ->build();
```

## Generated files

Three files are written to the output directory:

### `llms.txt`

A directory listing per the [llms.txt](https://llmstxt.org/) standard:

```
# Project Docs
> Description.

## Docs

- https://example.com/installation: Installation
- https://example.com/guides/configuration: Configuration
```

### `llms-full.txt`

Every page rendered as plain text, concatenated:

```
# Installation

Install the package with composer...

---

# Configuration

Set environment variables...
```

### `export/docs.md`

Every page's raw Markdown merged into a single file with frontmatter metadata:

```
# Installation

> Install the package with composer...

## Requirements

...
```

## Requirements

`siteUrl` must be set for correct URL generation in `llms.txt`.

If no `index.md` exists in the source directory, a generated landing page is included in the export.


---

# Usage

# Usage

## Static API

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/dist',
    title: 'Project Docs',
    description: 'Internal package documentation.',
    accentColor: '#ff2d20',
);
```

## Fluent API

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Internal package documentation.')
    ->accentColor('#ff2d20')
    ->accentColorDark('#ff6b61')
    ->repositoryUrl('https://github.com/acme/project')
    ->siteUrl('https://acme.github.io/project')
    ->editBranch('main')
    ->rightSidebar()
    ->build();

```

## Theme Color

Docsmith defaults to a Laravel red accent. Override it when building docs:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->accentColor('#1d4ed8')
    ->accentColorDark('#60a5fa')
    ->build();
```

Use hex colors for the best results because Docsmith derives the hover, focus, and dark-mode variants from the accent.

### Custom CSS

If you need to apply project-specific tweaks, you can append raw CSS or a CSS file during the build:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->customCss('body { background: #fff }')
    ->build();
```

Or:

```php
    ->customCss(__DIR__ . '/overrides.css')
```

## Search

Docsmith generates `search-index.json` and uses it for global result search in the sidebar.

- Type at least 2 characters to see global matches.
- Results include title, description, headings, and page content text.
- Selecting a result navigates to that page.

The existing sidebar filter search still works for quick navigation filtering.

## Search Overlay (Cmd+K)

Docsmith includes a modal search overlay accessible via keyboard shortcut or click.

- Press `Cmd+K` (macOS) or `Ctrl+K` (Windows/Linux) to open the overlay.
- Press `Esc` or click the backdrop to close.
- Results appear after typing at least 1 character.
- The search input in the header also opens the overlay on click.

The overlay searches the same `search-index.json` as the sidebar search.

## Versioned Docs

Docsmith supports building multiple documentation versions with a version switcher.

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Internal package documentation.')
    ->versions([
        ['slug' => 'v1', 'label' => 'v1.0', 'default' => true],
        ['slug' => 'v2', 'label' => 'v2.0'],
    ])
    ->build();
```

- Each version reads Markdown from `md/{slug}/`.
- The default version writes pages to the root (e.g., `installation/index.html`).
- Non-default versions are namespaced under `{slug}/` (e.g., `v2/installation/index.html`).
- Pages that exist only in a non-default version are not duplicated to the root.
- A version switcher dropdown appears in the site header.

### Version directory structure

```
md/
├── v1/          # default version — pages at root
│   ├── index.md
│   └── installation.md
└── v2/          # non-default — pages under /v2/
    ├── index.md
    └── installation.md
```

## Frontmatter `hidden`

Any page can be hidden from navigation, search results, and pagination by setting `hidden: true` in its frontmatter:

```markdown
---
title: Draft Page
hidden: true
---
```

Hidden pages are still rendered to HTML and directly accessible via URL, but they do not appear in:

- Sidebar navigation
- Search index
- Previous / next page links

## AI / LLM Export

Docsmith can generate AI-consumable exports of your documentation:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->siteUrl('https://acme.github.io/project')
    ->llmsExport(true)
    ->build();
```

Enabled by default. Set `->llmsExport(false)` to disable.

Three files are generated in the output directory:

| File | Contents |
|---|---|
| `llms.txt` | Directory listing with URLs and descriptions (per the llms.txt standard) |
| `llms-full.txt` | Full plain-text rendering of every page |
| `export/docs.md` | Merged Markdown of all pages with frontmatter metadata |

If the source directory does not contain `index.md`, Docsmith includes a generated landing page in the export files.

`siteUrl` is required for correct URL generation in `llms.txt`.

## Current output model

Each Markdown file becomes an HTML page.

- `md/index.md` becomes `index.html`
- `md/installation.md` becomes `installation/index.html`
- `md/guides/configuration.md` becomes `guides/configuration/index.html`

If the source directory does not contain an `index.md`, Docsmith generates a landing page automatically.

## README index compatibility mode

Docsmith can import README index formats used by existing projects like `laravel-undocumented` and `laravel-attributes-list`.

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->readmeIndex(__DIR__ . '/README.md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Generated from README index.')
    ->build();
```

Supported README item styles:

- `- [withAggregate()](features/eloquent/withAggregate.md) — description`
- `* [`#[Table]`](attributes/eloquent/Table.md) — description`


---

# Versioned Docs

# Versioned Docs

Docsmith supports building multiple documentation versions with a version switcher in the header.

## Setup

Pass a list of versions to `->versions()`:

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->versions([
        ['slug' => 'v1', 'label' => 'v1.0', 'default' => true],
        ['slug' => 'v2', 'label' => 'v2.0'],
    ])
    ->build();
```

Each version reads Markdown from `md/{slug}/`.

## Directory structure

```
md/
├── v1/               # default version — pages at root
│   ├── index.md
│   └── installation.md
└── v2/               # non-default — pages under /v2/
    ├── index.md
    └── installation.md
```

## How it works

- The version marked `default: true` writes pages to the root (e.g., `installation/index.html`).
- Non-default versions are namespaced under `{slug}/` (e.g., `v2/installation/index.html`).
- Pages that exist only in a non-default version are **not** duplicated to the root.
- A version switcher dropdown appears in the site header linking between versions.

