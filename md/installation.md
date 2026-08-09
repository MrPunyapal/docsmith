# Installation

## Requirements

- PHP 8.3 or newer
- Composer

## Install the package

```bash
composer require mrpunyapal/docsmith
```

## Build documentation

Docsmith can build a static site from any Markdown directory, either from PHP or from the command line.

### Command line

After installing the package, the binary is available at `vendor/bin/docsmith`:

```bash
vendor/bin/docsmith build --source=md --output=docs --title="Docsmith"
```

Run `vendor/bin/docsmith --help` for the full list of options.

### PHP

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/docs',
    title: 'Docsmith',
    description: 'Craft static documentation sites from Markdown with minimal setup.',
);
```

That setup keeps the Markdown source in `md/` and writes the generated site into `docs/`. The main entry page is written to `docs/index.html`.
