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
        env:
          ACME_PAT: ${{ secrets.ACME_PAT }}   # only needed for private sources

      - uses: actions/upload-pages-artifact@v3
        with:
          path: docs
```

Add a deploy job with `actions/deploy-pages@v4` (enable GitHub Pages → Source: GitHub Actions), or upload to any static host.

Notes:

- Commit `docsmith.sources.lock.json` so repeat runs sync incrementally; delete it to force a full refresh.
- Locally, tokens may also live in a `.env` file next to `docsmith.sources.php` — real environment variables always take precedence.
- Sync failures fail the workflow — errors exit non-zero.
- Private repositories are supported with a token; see [Remote Sources](remote-sources.md).
- Keep synced sources public or provide a token via repository secrets.

## Rebuild automatically when a source repository updates

The workflow above only runs when the docs repository itself changes. When a synced package updates its docs, nothing tells your site to rebuild. Close that gap with GitHub's cross-repository `repository_dispatch`: each source repository notifies the docs repository after a push, which then syncs and rebuilds.

### 1. Listen for the event (docs repository)

Extend the `on` block of `.github/workflows/docs.yml`:

```yaml
on:
  push:
    branches: [main]
  repository_dispatch:
    types: [content-updated]
  workflow_dispatch:
```

The existing `php bin/docsmith build --sync` step needs no changes — a dispatched run simply fetches the updated sources before building.

### 2. Notify the docs repository (each source repository)

`.github/workflows/notify-docs.yml` in every synced package:

```yaml
name: Notify docs site

on:
  push:
    branches:
      - main

jobs:
  notify:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger docs rebuild
        env:
          TOKEN: ${{ secrets.DOCS_DISPATCH_TOKEN }}
        run: |
          if [ -z "$TOKEN" ]; then
            echo "DOCS_DISPATCH_TOKEN is not set"
            exit 1
          fi
          curl --fail --show-error -X POST \
            -H "Authorization: Bearer $TOKEN" \
            -H "Accept: application/vnd.github.v3+json" \
            https://api.github.com/repos/acme/acme-docs/dispatches \
            -d '{"event_type": "content-updated"}'
```

Replace `acme/acme-docs` with your docs repository. Keep `--fail --show-error` so HTTP errors fail the step instead of passing silently.

### 3. Create the access token

1. Create a **fine-grained personal access token** (GitHub Developer Settings).
2. Under Repository Access, select only the docs repository.
3. Set the **Contents** permission to **Read and write**.
4. Save it as `DOCS_DISPATCH_TOKEN` in each source repository's Actions secrets.

Fine-grained tokens keep dispatch access limited to exactly the target repository.

### How it fits together

```text
auth-jobs repo: push docs → notify workflow → repository_dispatch
                                                    │
acme-docs repo: build --sync  ←  content-updated  ──┘
```

A merged PR in any synced package now ends with an up-to-date hub — no manual rebuilds, no scheduled polling.
