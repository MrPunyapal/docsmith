# Development

## Quality commands

```bash
composer test:lint     # rector --dry-run && pint --test
composer test:types    # phpstan
composer test:unit     # pest --parallel
composer test          # all of the above
```

## Tooling

The repository is configured with:

- Pest for tests
- PHPStan for static analysis
- Rector for automated refactoring
- Pint for formatting

## Build the package docs

```bash
composer docs:build
```

That command uses Docsmith itself to read Markdown from `md/` and regenerate the documentation site into `docs/`.

## CI / GitHub Actions

The repository includes a workflow at `.github/workflows/docs.yml` that builds and commits `docs/` on every push that changes the source Markdown or the build script.

If you enable generated Open Graph images, install Node, Playwright, and Chromium in CI as well:

```yaml
name: Build docs

on:
  push:
    branches: [main]

permissions:
  contents: write

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer:v2

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Install Node dependencies
        run: npm install

      - name: Install Playwright Chromium
        run: npx playwright install chromium --with-deps

      - name: Generate docs
        run: php build-docs.php

      - name: Commit generated docs
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git add docs
          if git diff --cached --quiet; then
            echo "No changes to commit"
          else
            git commit -m "chore: regenerate docs [skip ci]"
            git push
          fi
```

Adjust the PHP version, source paths, and build command to match your project. Without Open Graph capture you can omit the Node and Playwright steps.
