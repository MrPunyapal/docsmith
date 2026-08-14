# AI Documentation with Docsmith

Docsmith's AI story is **coding-agent driven**: your AI assistant (Claude Code,
Codex, Cursor, or any MCP-capable agent) does the writing; Docsmith provides the
tools. There is no API-pipeline to configure, no SDK, and Docsmith never calls
an LLM itself.

## Two Workflows

| Approach | When to use | Needs API key? |
|----------|-------------|----------------|
| **Coding Agent via MCP** | Let Claude Code / Codex / Cursor read source and write docs interactively | The agent's own |
| **Structural CLI** | One-command static docs from code analysis (no LLM) | No |

## Workflow 1: Coding Agent via MCP (recommended for AI docs)

Start the MCP server, then ask your agent to generate documentation:

```bash
docsmith mcp:serve --transport=stdio --source=./my-app --docs-source=./docs-source
```

Point your agent at the server. Claude Code example (`~/.claude.json` or your
project `.mcp.json`):

```json
{
  "mcpServers": {
    "docsmith": {
      "command": "docsmith",
      "args": ["mcp:serve", "--transport=stdio", "--source=./my-app"]
    }
  }
}
```

Then prompt it, e.g.:

> "Use the docsmith tools to write installation, configuration, and usage
> documentation for this project."

The agent calls `read_source`, `write_markdown`, and `build_site` directly.
Docsmith needs no API key of its own — the agent uses its own credentials.
See [MCP Server](mcp-server.md) for the full tool reference and Codex/Cursor
setup.

## Workflow 2: Structural CLI

Generate static, structural documentation from code analysis — no LLM, no keys:

```bash
docsmith generate --source=./my-app --output=./docs
```

```php
Docsmith::generate()
    ->source(__DIR__ . '/app')
    ->output(__DIR__ . '/docs')
    ->title('My App')
    ->build();
```

## Pipeline Stages

1. **Code Scan** — Recursively scans source files and builds a feature map (classes, functions, namespaces)
2. **Doc Writing** — Generates a markdown page per feature from the feature map
3. **Media Capture** (optional with `--media`) — Captures screenshots/video of runnable features via Playwright
4. **Build** — Renders the static HTML site

## What happened to the old AI pipeline?

Earlier versions ran an internal AI pipeline (`--ai-provider`, an SDK-based
provider, a dependency-free OpenAI-compatible HTTP provider, a review agent,
and an `agent:run` command). In the v1 refactor those entry points were removed
and AI is **MCP-only** — Docsmith never calls an LLM itself, and compliance
with your coding agent's provider terms and keys is entirely up to that agent.

The removed work is preserved in the repository for anyone who wants it back:

- `backup/ai-pipeline/` — the SDK-free provider, reviewer agent, `agent:run`,
  and pre-strip copies of the pipeline, PHP API, CLI, tests, and docs
- `backup/laravel-ai-provider/` — the original `laravel/ai` SDK-based provider

See [Future Scope](future-scope.md) for how the pipeline could be restored on
top of the current structural one.