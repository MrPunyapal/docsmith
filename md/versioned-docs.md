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
