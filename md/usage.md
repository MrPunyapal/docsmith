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

## CommonMark extensions

Docsmith enables CommonMark core and GitHub-flavored Markdown by default. Register additional League CommonMark extensions with the fluent API:

```php
use Docsmith\Docsmith;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/docs')
    ->commonMarkExtensions([
        new DescriptionListExtension(),
    ])
    ->commonMarkConfig([
        'html_input' => 'strip',
    ])
    ->build();
```

Configuration passed to `commonMarkConfig()` overrides Docsmith's environment defaults and may include configuration for registered extensions. The static `Docsmith::build()` API also accepts `commonMarkExtensions` and `commonMarkConfig` arrays.

For command-line builds, put both values in a PHP configuration file:

```php
<?php

use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

return [
    'extensions' => [
        new DescriptionListExtension(),
    ],
    'config' => [
        'html_input' => 'strip',
    ],
];
```

Pass that file to the build command:

```bash
vendor/bin/docsmith build \
    --source=md \
    --commonmark-config=docsmith.commonmark.php
```

## Alerts

GitHub-style alerts are enabled by default. Start a block quote with `[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, or `[!CAUTION]` (case-insensitive) to turn it into a colored callout:

```md
> [!TIP]
> Helpful advice for doing things better.

> [!WARNING]
> Urgent info that needs immediate user attention.
```

The marker must be alone on its first line, and content follows on the lines below. Alerts support any Markdown inside — lists, code blocks, links. Unknown markers like `[!FOO]` and regular block quotes are rendered as plain block quotes. Styling ships with the built-in theme; override `.markdown-alert` in custom CSS to change it.

Every type in action — this is what the five markers look like in the built theme:

> [!NOTE]
> Useful information that users should know, even when skimming content.

> [!TIP]
> Helpful advice for doing things better.

> [!IMPORTANT]
> Key information users need to know.

> [!WARNING]
> Urgent info that needs immediate user attention to avoid problems.

> [!CAUTION]
> Advises about risks or negative outcomes of certain actions.

## Command line

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
| `--source=DIR` | Directory with Markdown sources (required) | none |
| `--output=DIR` | Output directory | `docs` |
| `--title=TITLE` | Site title | `Documentation` |
| `--description=DESC` | Site description | `Project documentation.` |
| `--accent-color=HEX` | Accent color | `#ff2d20` |
| `--accent-color-dark=HEX` | Dark-mode accent color | derived from accent |
| `--custom-css=FILE` | Path to a custom CSS file | none |
| `--commonmark-config=FILE` | PHP file returning CommonMark extensions and environment config | none |
| `--base-url=URL` | Base URL | `/` |
| `--right-sidebar` | Enable the right sidebar table of contents | off |
| `--repository-url=URL` | Repository URL for edit links | none |
| `--site-url=URL` | Canonical site URL | none |
| `--edit-branch=BRANCH` | Branch used for edit links | `main` |
| `--edit-prefix=PREFIX` | Path prefix prepended to the file path in edit links, for example `md/` | none |
| `--favicon=FILE` | Favicon URL, data URI, or local file path | generated default |
| `--no-docsmith-badge` | Hide the "Built with DocSmith" sidebar badge | shown |
| `--help` | Show usage | |

Example with edit links and a right sidebar:

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

The binary is a wrapper around `Docsmith::build()`. Every option maps to the static API parameter or fluent method of the same name.

## Theme color

Docsmith defaults to a Laravel red accent. Override it when building docs:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->accentColor('#1d4ed8')
    ->accentColorDark('#60a5fa')
    ->build();
```

Use hex colors for the best results because Docsmith derives hover, focus, and dark-mode variants from the accent.

### Custom CSS

Append raw CSS during the build:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->customCss('body { background: #fff }')
    ->build();
```

Or append a CSS file by passing a path instead:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->customCss(__DIR__ . '/overrides.css')
    ->build();
```

Either way the rules are appended to the published `assets/app.css`.

## Search

Docsmith generates `search-index.json` at build time and uses it for global search.

- Type at least 1 character in the sidebar search box to see global matches.
- Results include title, description, headings, and page content.
- Selecting a result navigates to that page.

The sidebar filter still narrows the visible navigation links as you type.

### Choosing navigation order

Use `navigationOrder()` to place pages in a custom sidebar sequence. Entries can match a page title, `sidebar_label`, relative Markdown path, or output path. Pages not listed keep their existing order after the listed ones:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->navigationOrder(['Installation', 'Usage', 'Open Graph'])
    ->build();
```

## Search overlay (Cmd+K)

Docsmith includes a modal search overlay.

- Press `Cmd+K` (macOS) or `Ctrl+K` (Windows/Linux) to open it.
- Press `Esc` or click the backdrop to close it.
- Results appear after typing at least 1 character.
- Clicking the search input in the header also opens it.

The overlay searches the same `search-index.json` as the sidebar search.

## Versioned docs

Docsmith can build multiple versions of one documentation set with pill buttons on every page. See [Versioned Docs](versioned-docs.md) for details.

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->versions([
        ['slug' => 'v1', 'label' => 'v1.0', 'default' => true],
        ['slug' => 'v2', 'label' => 'v2.0'],
    ])
    ->build();
```

## Frontmatter

Every page accepts these frontmatter keys:

| Key | Effect |
|---|---|
| `title` | Page title, falling back to the first heading or filename |
| `description` | Page description used in meta tags and search results |
| `slug` | Custom output path instead of the file path |
| `order` | Sort position in the sidebar (default `999`) |
| `sidebar_label` | Shorter label shown in the sidebar |
| `hidden` | Set to `true` to exclude the page from navigation, search, and pagination |
| `og_image`, `og_title`, `og_description` | Per-page Open Graph overrides |

A hidden page is still rendered to HTML and reachable by URL, but it does not appear in the sidebar, the search index, or previous/next links:

```markdown
---
title: Draft Page
hidden: true
---
```

## LLM export

Docsmith generates three text files for LLM consumption: `llms.txt`, `llms-full.txt`, and `export/docs.md`. This is enabled by default; see [LLM Export](llm-export.md) for details.

## Attribution badge

Docsmith adds a small "Built with DocSmith" link at the bottom of the sidebar. It is shown by default and can be disabled per build:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->showDocsmithBadge(false)
    ->build();
```

Or via the static API:

```php
Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/dist',
    showDocsmithBadge: false,
);
```

Via the CLI, pass `--no-docsmith-badge`.

## Output structure

Each Markdown file becomes an HTML page:

- `md/index.md` becomes `index.html`
- `md/installation.md` becomes `installation/index.html`
- `md/guides/configuration.md` becomes `guides/configuration/index.html`

If the source directory has no `index.md`, Docsmith generates a landing page automatically.

Every build also writes `search-index.json`, `sitemap.xml`, `.nojekyll`, and the LLM export files into the output directory.

## Linking between pages

Write internal links the GitHub way, pointing at the `.md` file:

```markdown
See [Versioned Docs](versioned-docs.md) for details.
```

Docsmith rewrites these to the built page URLs at build time. Relative paths (`../installation.md`) and fragments (`configuration.md#options`) both resolve, in plain builds as well as versioned and hub builds. Links to `.md` files that are not part of the build are left untouched, as are external URLs and anchors.

## Media

Images, videos, audio, and PDFs in the source directory are published into the build automatically. Relative references to them are rewritten for each page so they resolve from the built URL:

```markdown
![Diagram](images/diagram.png)

<video controls src="media/demo.mp4"></video>

[Download the spec](files/spec.pdf)
```

Remote URLs, root-relative paths, data URIs, and files that were not published are left untouched. Disable with `->publishMedia(false)`. See [Media](media.md) for details.

## README index compatibility mode

Docsmith can import README index formats used by projects like `laravel-undocumented` and `laravel-attributes-list`:

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->readmeIndex(__DIR__ . '/README.md')
    ->readmeSkipSections(['Contributing', 'Author', 'Notes'])
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Generated from README index.')
    ->build();
```

Supported README item styles:

- `- [withAggregate()](features/eloquent/withAggregate.md) - description`
- `* [`#[Table]`](attributes/eloquent/Table.md) - description`
