# Future Scope

## The Internal AI Pipeline (Removed)

Earlier iterations of Docsmith shipped an internal AI pipeline: a `laravel/ai`
SDK-based provider, then a dependency-free `OpenAiHttpProvider` (OpenAI-compatible
chat completions over cURL), a project-level planner that let the model design
whole doc sets, a `ReviewerAgent` quality pass, an `agent:run` command, and
`--ai-provider` / `--ai-model` / `--ai-api-key` / `--ai-base-url` CLI options.
Later it also shipped a structural `generate` pipeline that produced shallow
LLM-free pages and a Playwright media-capture stage.

All of it was removed in favor of the coding-agent architecture: agents bring
their own model and keys, and drive Docsmith through MCP (`read_source`,
`write_markdown`, `capture_media`, `build_site`). The media-capture stage was
rebuilt properly as the `capture_media` tool backed by
[capturist](https://github.com/MrPunyapal/capturist) — screenshots and stepped
videos of the running app, written straight into the docs source.

The removed code lives in git history (pre-refactor commits on `feat/AI`,
before `f808bf1`) if anyone ever wants to revisit an unattended/CI pipeline.

## Multi-Agent Parallel Generation

For large codebases, doc generation could be parallelized — each module or
namespace processed simultaneously. This would dramatically reduce generation
time for projects with hundreds of files.

## Custom Prompt Templates

Allow users to define documentation style and structure conventions the coding
agent should follow (for MCP-driven generation):

```php
Docsmith::serveMcp(
    sourcePath: './app',
    docsSourcePath: './docs-source',
);
```

## RAG from Existing Docs

Feed existing documentation as context for consistency. Newly generated pages
would match the tone, terminology, and structure of hand-written docs already in
the project.

## CI Integration

Run `docsmith build` as a GitHub Action step to publish the markdown docs as a
static site on every push:

```yaml
- run: docsmith build --source=./docs-source --output=./docs
```

## Web Dashboard

A browser-based UI to monitor, trigger, and review doc generation — showing
pipeline progress, media previews, and (when the AI pipeline returns) review
scores before publishing.

## Plugin System

Third-party agents and tools via Composer:

```bash
composer require docsmith/agent-swagger
composer require docsmith/tool-graphviz
```

Plugins could add new source scanners (for Python, Go, Rust), custom media
capturers, or alternative output formats (PDF, API Blueprint).

## Multi-Format Export

Beyond HTML sites, generate PDF manuals, OpenAPI specs, or Markdown API
references from the same pipeline.

## Incremental Generation

Only regenerate docs for changed files since the last build, using git diffs to
detect modifications. This would make iteration on large projects near-instant.

## Docstring Extraction

Parse PHPDoc, JSDoc, or Python docstrings to enrich generated documentation
with inline comments, `@param` / `@return` annotations, and type information.