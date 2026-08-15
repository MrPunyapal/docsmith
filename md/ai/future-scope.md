# Future Scope

## Restore the Automated AI Pipeline

Earlier iterations of Docsmith shipped an internal AI pipeline: a `laravel/ai`
SDK-based provider, then a dependency-free `OpenAiHttpProvider` (OpenAI-compatible
chat completions over cURL), a project-level planner that let the model design
whole doc sets, a `ReviewerAgent` quality pass, an `agent:run` command, and
`--ai-provider` / `--ai-model` / `--ai-api-key` / `--ai-base-url` CLI options.
Later it also shipped a structural `generate` pipeline that produced shallow
LLM-free pages (no parameter detail, unreliable attribution) and a Playwright
media-capture stage.

That work is **preserved in the repository**:

| Location | Contents |
|----------|----------|
| `backup/ai-pipeline/` | SDK-free provider, reviewer agent, `agent:run`, pre-strip pipeline/PHP API/CLI copies, tests, docs |
| `backup/laravel-ai-provider/` | The original `laravel/ai` SDK-based provider |
| `backup/structural-pipeline/` | `GenerateCommand`, scan/write/media agents, pipeline classes, tests, media-capture docs |

v1 deliberately ships **AI only through the MCP server** (coding agents carry
their own keys; Docsmith never calls an LLM). Structure analysis is available
to agents via the `read_source` tool (`list_files` / `read_file` /
`analyze_structure`); writers use `write_markdown`; the site renders with
`build_site`. If an unattended/CI AI pipeline becomes desirable again, it can
be restored from `backup/ai-pipeline/` (see its README for the exact restore
commands) or rebuilt on top of the MCP tools.

## Multi-Agent Parallel Generation

For large codebases, doc generation could be parallelized — each module or
namespace processed simultaneously. This would dramatically reduce generation
time for projects with hundreds of files.

## Coding-Agent Skill / Guidelines Package

Ship a `skills/` or `CLAUDE.md`-style guidelines file that teaches coding agents
exactly how to use the MCP tools well: when to call `read_source` vs
`write_markdown`, page structure conventions, and how to keep `docs-source`
consistent with `docs/`. First-class guidance for Claude Code, Codex, Cursor,
and Laravel Boost setups.

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