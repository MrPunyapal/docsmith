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

## Command Line

Docsmith ships a standalone binary that builds a site without writing any PHP. After installing the package, run:

```bash
vendor/bin/docsmith build --source=md --output=docs --title="Project Docs"
```

When developing inside this repository, call it directly:

```bash
php bin/docsmith build --source=md --output=docs --title="Project Docs"
```

| Option | Description | Default |
|---|---|---|
| `--source=DIR` | Directory with Markdown sources (required) | — |
| `--output=DIR` | Output directory | `docs` |
| `--title=TITLE` | Site title | `Documentation` |
| `--description=DESC` | Site description | `Project documentation.` |
| `--accent-color=HEX` | Accent color | `#ff2d20` |
| `--accent-color-dark=HEX` | Dark-mode accent color | — |
| `--custom-css=FILE` | Path to a custom CSS file | — |
| `--base-url=URL` | Base URL | `/` |
| `--right-sidebar` | Enable the right sidebar | off |
| `--repository-url=URL` | Repository URL for edit links | — |
| `--site-url=URL` | Canonical site URL | — |
| `--edit-branch=BRANCH` | Branch used for edit links | `main` |
| `--help` | Show usage | — |

Example with edit links, right sidebar, and search-ready output:

```bash
vendor/bin/docsmith build \
    --source=md \
    --output=docs \
    --title="Project Docs" \
    --description="Internal package documentation." \
    --accent-color="#1d4ed8" \
    --site-url=https://acme.github.io/project \
    --repository-url=https://github.com/acme/project \
    --edit-branch=main \
    --right-sidebar
```

The binary is a thin wrapper around `Docsmith::build()` — every option maps to the static API parameter of the same name.

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
