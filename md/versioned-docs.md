# Versioned Docs

Docsmith supports building multiple versions of one documentation set with a version switcher on every page.

## Setup

Pass a list of versions to `->docs()`:

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->docs([
        'project' => [
            'label' => 'Project Docs',
            'versions' => [
                '2.x' => ['label' => '2.x', 'source' => __DIR__ . '/md/2.x', 'default' => true],
                '1.x' => ['label' => '1.x', 'source' => __DIR__ . '/md/1.x'],
            ],
        ],
    ])
    ->build();
```

## How it works

- The version marked `default: true` is served at the docs root (`/project/…`). If none is marked, the first listed version is used.
- Other versions are namespaced under their version key (`/project/1.x/…`).
- A version switcher appears in the sidebar. Buttons link to the same page in another version when it exists there, otherwise to that version's home.
- `navigation` can be set per docs entry; frontmatter `order:` still applies per page.

## Multiple documentation sets

Each top-level entry in `docs()` is an independent set with its own selector entry and its own mounted path:

```php
->docs([
    'package-a' => ['label' => 'Package A', 'source' => __DIR__ . '/md/a'],
    'package-b' => [
        'label' => 'Package B',
        'versions' => [
            '2.x' => ['label' => '2.x', 'source' => __DIR__ . '/md/b-2x', 'default' => true],
            '1.x' => ['label' => '1.x', 'source' => __DIR__ . '/md/b-1x'],
        ],
    ],
])
```

When no root page exists, `/` forwards to the first entry.
