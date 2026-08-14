# Future Scope

## Multi-Agent Parallel Generation

For large codebases, agents could be spawned in parallel — each processing a different module or namespace simultaneously. This would dramatically reduce generation time for projects with hundreds of files.

## Custom Prompt Templates

Allow users to define their own documentation style and structure via template files:

```php
Docsmith::generate()
    ->source('./app')
    ->withPromptTemplate('./docs/templates/laravel-style.md')
    ->build();
```

## RAG from Existing Docs

Feed existing documentation as context for consistency. Newly generated pages would match the tone, terminology, and structure of hand-written docs already in the project.

## CI Integration

Run `docsmith generate` as a GitHub Action step to auto-update structural documentation on every push:

```yaml
- run: docsmith generate --source=./src --output=./docs
```

## Web Dashboard

A browser-based UI to monitor, trigger, and review doc generation — showing pipeline progress, review scores, and media previews before publishing.

## Plugin System

Third-party agents and tools via Composer:

```bash
composer require docsmith/agent-swagger
composer require docsmith/tool-graphviz
```

Plugins could add new source scanners (for Python, Go, Rust), custom media capturers, or alternative output formats (PDF, API Blueprint).

## Multi-Format Export

Beyond HTML sites, generate PDF manuals, OpenAPI specs, or Markdown API references from the same pipeline.

## Incremental Generation

Only regenerate docs for changed files since the last build, using git diffs to detect modifications. This would make iteration on large projects near-instant.

## Docstring Extraction

Parse PHPDoc, JSDoc, or Python docstrings to enrich generated documentation with inline comments, `@param` / `@return` annotations, and type information.
