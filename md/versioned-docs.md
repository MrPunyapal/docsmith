# Versioned Docs

Docsmith has two separate features that compose:

- **`versions()`** — multiple versions of *one* documentation set. Every page shows v1/v2/v3 pill buttons for switching. No dropdown.
- **`docs()`** — a docs hub: several *independent* documentation sets, one dropdown in the sidebar to pick between them.

A docs hub entry can itself use the versions feature — then it stays a single dropdown item and its pages get the pill buttons.

## Versioning one doc set: `versions()`

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

How it works:

- Sources are implied as `{source}/{slug}` — the config above reads `md/v1/` and `md/v2/`. You can also set `source` per version to point anywhere.
- The version marked `default: true` is served at the site root (`/…`). If none is marked, the first listed version is used.
- Other versions are namespaced under their slug (`/v2/…`).
- Pill buttons on every page switch versions. They link to the same page in another version when it exists there, otherwise to that version's home.
- No docs dropdown appears in this mode.

## Multiple doc sets: `docs()`

Each entry in `docs()` is an independent set with one dropdown option and its own mounted path:

```php
->docs([
    'package-a' => ['label' => 'Package A', 'source' => __DIR__ . '/md/a'],
    'package-b' => ['label' => 'Package B', 'source' => __DIR__ . '/md/b'],
])
```

- Entry `n` mounts at `/package-n/…`; nothing lives at the root except a redirect to the first entry.
- The sidebar shows a dropdown listing all entries; selecting one navigates to its home.
- `navigation` can be set per entry; frontmatter `order:` still applies per page.

## Combining both: a hub entry with versions

Give an entry a `versions` list (same shape as `->versions()`) and it becomes one dropdown item with pill buttons inside:

```php
->docs([
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

- The `versions` list describes **all** versions of that entry. The primary version — flagged `default`, else the first listed — mounts at `/auth-jobs/…`; siblings nest under `/auth-jobs/{slug}/…`.
- The entry-level `source` may stand in for the primary version's source (as above). Other versions need their own `source`, or resolve to `{source}/{entry-slug}/{version-slug}` when `->source()` is set.
- Pages of that entry show v1/v2 pills; other entries keep plain dropdown behavior.

## Remote sources are orthogonal

`docsmith sync` materializes repositories into `md/<target>` before building. It works the same whether you build a single site, a versioned set, or a multi-docs hub — the hub layer only adds the selector on top.
