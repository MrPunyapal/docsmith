# Workflows

Remote sources only materialize local folders under your markdown root — every recipe below just points a normal build at those folders. Pick the recipe that matches your setup; all of them use the same two commands:

```bash
php bin/docsmith sync          # fetch/update remote sources
php bin/docsmith build         # or: php bin/docsmith build --sync
```

## Recipe 1: Docs hub from several repositories

Sync three repositories into three targets, then build one hub with a dropdown:

```php
// docsmith.sources.php
return [
    [
        'repository' => 'https://github.com/laravel/docs.git',
        'ref' => '12.x',
        'path' => '',
        'target' => 'laravel',
    ],
    [
        'repository' => 'https://github.com/acme/auth-jobs.git',
        'ref' => 'main',
        'path' => 'docs',
        'target' => 'auth-jobs',
    ],
    [
        'repository' => 'https://github.com/acme/blog-kit.git',
        'ref' => 'main',
        'path' => 'guide',
        'target' => 'blog-kit',
    ],
];
```

```php
// build.php
use Docsmith\Docsmith;

Docsmith::make()
    ->output(__DIR__ . '/docs')
    ->title('Acme Developer Portal')
    ->hub([
        'laravel' => ['label' => 'Laravel Docs', 'source' => __DIR__ . '/md/laravel'],
        'auth-jobs' => ['label' => 'Auth Jobs', 'source' => __DIR__ . '/md/auth-jobs'],
        'blog-kit' => ['label' => 'Blog Kit', 'source' => __DIR__ . '/md/blog-kit'],
    ])
    ->build();
```

The dropdown lists Laravel Docs, Auth Jobs, and Blog Kit — each mounted at its own slug.

## Recipe 2: One repository, two branches = versions

Sync the same repository twice with different refs, then build a versioned single-docs site:

```php
// docsmith.sources.php
return [
    [
        'repository' => 'https://github.com/acme/auth-jobs.git',
        'ref' => 'main',
        'path' => 'docs',
        'target' => 'auth-jobs-2x',
    ],
    [
        'repository' => 'https://github.com/acme/auth-jobs.git',
        'ref' => '1.x',
        'path' => 'docs',
        'target' => 'auth-jobs-1x',
    ],
];
```

```php
Docsmith::make()
    ->output(__DIR__ . '/docs')
    ->versions([
        ['slug' => 'v2', 'label' => 'v2.0', 'default' => true],
        ['slug' => 'v1', 'label' => 'v1.0', 'source' => __DIR__ . '/md/auth-jobs-1x'],
    ])
    ->build();
```

`versions()` reads `md/{slug}` by default — here the first target is named `auth-jobs-2x`, so we point v2's implied source at it by naming the slug `v2` and overriding v1 explicitly. The flagged `default` version owns the site root.

## Recipe 3: Hub entry with versions from synced branches

Combine both: other packages in the dropdown, plus one package that has two synced branches as embedded versions:

```php
// docsmith.sources.php — Recipe 1 manifest plus:
[
    'repository' => 'https://github.com/acme/auth-jobs.git',
    'ref' => '1.x',
    'path' => 'docs',
    'target' => 'auth-jobs-1x',
],
```

```php
->hub([
    'laravel' => ['label' => 'Laravel Docs', 'source' => __DIR__ . '/md/laravel'],
    'auth-jobs' => [
        'label' => 'Auth Jobs',
        'source' => __DIR__ . '/md/auth-jobs',   // backs the default version (ref: main)
        'versions' => [
            ['slug' => 'v2', 'label' => 'v2', 'default' => true],                        // /auth-jobs/
            ['slug' => 'v1', 'label' => 'v1', 'source' => __DIR__ . '/md/auth-jobs-1x'], // /auth-jobs/v1/
        ],
    ],
])
```

In the built site the dropdown shows only "Auth Jobs" — never "Auth Jobs v1" — while its pages carry v1/v2 pills.

## Recipe 4: Plain single site from one repository

No hub, no versions — sync one repo and build it directly:

```php
// docsmith.sources.php
return [
    [
        'repository' => 'https://github.com/acme/my-package.git',
        'ref' => 'main',
        'path' => 'docs',
        'target' => 'my-package',
    ],
];
```

```bash
php bin/docsmith build --sync --source=md/my-package --output=docs
```

Nothing is fetched at build time unless `--sync` asks for it; without `docsmith.sources.lock.json` changes, repeat syncs are no-ops.

## GitHub Actions workflow

`.github/workflows/docs.yml` for any of the recipes above:

```yaml
name: Build docs

on:
  push:
    branches: [main]
  workflow_dispatch:

permissions:
  contents: read

jobs:
  docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer:v2

      - run: composer install --no-interaction --prefer-dist --no-progress

      # Syncs remote sources (incremental when docsmith.sources.lock.json
      # matches) and builds in one step.
      - run: php bin/docsmith build --sync

      - uses: actions/upload-pages-artifact@v3
        with:
          path: docs
```

Add a deploy job with `actions/deploy-pages@v4` (enable GitHub Pages → Source: GitHub Actions), or upload to any static host.

Notes:

- Commit `docsmith.sources.lock.json` so repeat runs sync incrementally; delete it to force a full refresh.
- Sync failures fail the workflow — errors exit non-zero.
- Private repositories are not supported yet; keep synced sources public.
