# Getting Started with AI-Powered Documentation

Docsmith can automatically generate documentation for your project. There are two distinct workflows depending on whether you want an AI assistant to drive the process or run a fully automated pipeline.

## Two Workflows

| Approach | When to use | Needs API key? |
|----------|-------------|----------------|
| **MCP Server** | Let Claude Code / Cursor drive doc generation interactively | Your AI assistant's key |
| **Auto Pipeline** | One-command generation from CLI or CI | Only if you want AI-written docs |
| **Structural Only** | Basic docs from code analysis (no LLM) | No |

## Workflow 1: AI Assistant via MCP

Start the MCP server, then your AI assistant controls the tools:

```bash
docsmith mcp:serve --transport=stdio --source=./my-app
```

Claude Code can then call `read_source`, `write_markdown`, and `build_site` tools directly. No Docsmith API key needed — your assistant uses its own.

## Workflow 2: Auto Pipeline

Generate everything in one command:

```bash
# Basic docs from code structure (no API key needed)
docsmith generate --source=./my-app --output=./docs

# AI-enriched docs with optional media capture and review
docsmith generate \
    --source=./my-app \
    --output=./docs \
    --title="My App Documentation" \
    --ai-provider=anthropic \
    --media \
    --review
```

## Workflow 3: PHP API

```php
Docsmith::generate()
    ->source(__DIR__ . '/app')
    ->output(__DIR__ . '/docs')
    ->title('My App')
    ->withAi(provider: 'anthropic', apiKey: $apiKey, model: 'claude-sonnet-4-6')
    ->mediaEnabled()
    ->reviewEnabled()
    ->build();
```

## Pipeline Stages

1. **Code Scan** — Recursively scans source files and builds a feature map (classes, functions, namespaces)
2. **Doc Writing** — Generates markdown documentation for each feature. Uses AI if configured, otherwise produces structural docs
3. **Media Capture** (optional with `--media`) — Scores features for UI relevance, captures screenshots/video via Playwright
4. **Review** (optional with `--review`) — Validates headings, links, code blocks, media references; calculates quality score
5. **Build** — Renders the static HTML site
