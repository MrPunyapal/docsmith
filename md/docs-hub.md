# Docs Hub

The docs hub builds several **independent** documentation sets into one site. A dropdown in the sidebar switches between them.

This is a separate feature from [Versioned Docs](versioned-docs.md): versions switch between v1/v2/v3 of one doc set with pill buttons; the hub switches between different packages with a dropdown.

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
    'extended-relationships' => [
        'label' => 'Extended Relationships',
        'source' => __DIR__ . '/md/extended-relationships',
    ],
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

- The embedded `versions` list uses the same shape as `->versions()`. It describes all versions of that entry.
- The primary version — flagged `default`, else the first listed — mounts at the entry root (`/auth-jobs/…`); siblings nest under it (`/auth-jobs/v1/…`).
- The entry-level `source` may stand in for the primary version's source (as above). Other versions need their own `source`, or resolve to `{source}/{entry-slug}/{version-slug}` when `->source()` is set.

So in the built site the dropdown shows only "Extended Relationships" and "Auth Jobs" — never "Auth Jobs v1" — while Auth Jobs pages carry v1/v2 pills.

## Remote sources are orthogonal

`docsmith sync` materializes remote repositories into `md/<target>` before building. It works identically for a plain build, a versioned build, or a hub — see [Documentation Hub (sync)](documentation-hub.md).
