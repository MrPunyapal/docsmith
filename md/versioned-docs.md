# Versioned Docs

Docsmith can build multiple versions of one documentation set. Every page shows pill buttons for switching between versions.

## Setup

Pass a list of versions to `versions()`:

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

Each version reads Markdown from `{source}/{slug}`:

```
md/
├── v1/               # default version, pages written to the site root
│   ├── index.md
│   └── installation.md
└── v2/               # non-default version, pages written under /v2/
    ├── index.md
    └── installation.md
```

Set a per-version `source` to read from anywhere instead.

## How it works

- Keyed maps work too: `'v1' => ['label' => 'v1.0']`.
- The version marked `default: true` writes pages to the site root (`installation/index.html`). If none is marked, the first listed version is used.
- Other versions are namespaced under their slug (`v2/installation/index.html`).
- Pages that exist only in a non-default version are not duplicated to the root.
- Pill buttons on every page switch versions. They link to the same page in another version when it exists there, otherwise to that version's home.
- No docs dropdown appears in this mode.

## Default version

The version flagged `default: true` owns the site root. If no version is flagged, the first listed version is the default. Flag a later one to override:

```php
->versions([
    ['slug' => 'v2', 'label' => 'v2.0'],                     // first listed...
    ['slug' => 'v1', 'label' => 'v1.0', 'default' => true],  // ...but v1 owns the root
])
```

Here `/` serves v1 and `/v2/` holds the other version.

## Navigation order

Set `navigation` on a version to control its sidebar order. Entries are matched by title, sidebar label, or file path. Pages not listed keep their natural order after the listed ones, and frontmatter `order:` still applies per page:

```php
->versions([
    [
        'slug' => 'v2',
        'label' => 'v2.0',
        'default' => true,
        'navigation' => ['index.md', 'usage.md', 'installation.md'],
    ],
    ['slug' => 'v1', 'label' => 'v1.0', 'source' => __DIR__ . '/md/v1'],
])
```

Versions without their own `navigation` fall back to the global `navigationOrder([...])`.
