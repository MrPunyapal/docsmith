# Docs Hub

The docs hub builds several **independent** documentation sets into one site. A dropdown in the sidebar switches between them.

## Setup

Pass one entry per documentation set to `->hub()`:

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->output(__DIR__ . '/dist')
    ->title('Acme Docs')
    ->hub([
        'package-a' => ['label' => 'Package A', 'source' => __DIR__ . '/md/a'],
        'package-b' => ['label' => 'Package B', 'source' => __DIR__ . '/md/b'],
    ])
    ->build();
```

## How it works

- Each entry gets one dropdown option and mounts under its slug (`/package-a/…`, `/package-b/…`).
- Nothing is generated at the root: `/` simply forwards to the first entry.
- `navigation` can be set per entry; frontmatter `order:` still applies per page.

## Hub entries with versions

An entry can embed a `versions` list. The entry stays a **single** dropdown item, and its pages get version pill buttons:

```php
->hub([
    'auth-jobs' => [
        'label' => 'Auth Jobs',
        'source' => __DIR__ . '/md/auth-jobs',          // backs the default version
        'navigation' => ['index.md', 'usage.md', ...],  // optional, per entry
        'versions' => [
            ['slug' => 'v2', 'label' => 'v2', 'default' => true],
            ['slug' => 'v1', 'label' => 'v1', 'source' => __DIR__ . '/md/auth-jobs-1x'],
        ],
    ],
])
```

- The `versions` list describes all versions of that entry.
- The primary version — flagged `default`, else the first listed — mounts at the entry root (`/auth-jobs/…`); siblings nest under it (`/auth-jobs/v1/…`).
- The entry-level `source` may stand in for the primary version's source (as above). Other versions need their own `source`, or resolve to `{source}/{entry-slug}/{version-slug}` when `->source()` is set.

So in the built site the dropdown shows only "Auth Jobs" — never "Auth Jobs v1" — while Auth Jobs pages carry v1/v2 pills.
