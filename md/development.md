# Development

## Quality commands

```bash
composer test:lint
composer test:types
composer test:unit
composer test
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
