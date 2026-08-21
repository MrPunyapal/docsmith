# Versioned Docs

Docsmith can build multiple versions of one documentation set. Every page shows v1/v2/v3 pill buttons for switching between them. No dropdown appears in this mode — that is the docs hub feature.

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

- Sources are implied as `{source}/{slug}` — the config above reads `md/v1/` and `md/v2/`. You can also set `source` per version to point anywhere.
- Keyed maps work too: `'v1' => ['label' => 'v1.0']`.
- The version marked `default: true` is served at the site root (`installation/index.html`). If none is marked, the first listed version is used.
- Other versions are namespaced under their slug (`v2/installation/index.html`).
- Pages that exist only in a non-default version are **not** duplicated to the root.
- Pill buttons on every page switch versions. They link to the same page in another version when it exists there, otherwise to that version's home.
- `navigation` can be set per version; frontmatter `order:` still applies per page.

## Docs Hub vs Versions

These are two separate features:

- **`->versions()`** — multiple versions of ONE doc set, pill buttons on pages.
- **`->hub()`** — several independent doc sets with a sidebar dropdown.

A docs hub entry can embed a `versions` list to get pills on its pages while staying one dropdown item. See [Docs Hub](docs-hub.md).
