# LLM Export

Docsmith can generate AI-consumable exports of your documentation for use with LLMs and AI agents.

## Enabling the export

Export is **enabled by default**. Disable it with:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->siteUrl('https://acme.github.io/project')
    ->llmsExport(false)
    ->build();
```

## Generated files

Three files are written to the output directory:

### `llms.txt`

A directory listing per the [llms.txt](https://llmstxt.org/) standard:

```
# Project Docs
> Description.

## Docs

- https://example.com/installation: Installation
- https://example.com/guides/configuration: Configuration
```

### `llms-full.txt`

Every page rendered as plain text, concatenated:

```
# Installation

Install the package with composer...

---

# Configuration

Set environment variables...
```

### `export/docs.md`

Every page's raw Markdown merged into a single file with frontmatter metadata:

```
# Installation

> Install the package with composer...

## Requirements

...
```

## Requirements

`siteUrl` must be set for correct URL generation in `llms.txt`.

If no `index.md` exists in the source directory, a generated landing page is included in the export.
