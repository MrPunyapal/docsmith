# AI Documentation with Docsmith

Docsmith's AI story is **coding-agent driven**: your AI assistant (Claude Code,
Codex, Cursor, or any MCP-capable agent) does the writing; Docsmith provides the
tools. There is no API pipeline to configure, no SDK, and Docsmith never calls
an LLM itself.

## The Workflow

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

The agent calls `read_source` to explore the codebase (file listing, contents,
structure analysis), `write_markdown` to create documentation pages, and
`build_site` to render the static site. Docsmith needs no API key of its own —
the agent uses its own credentials. See [MCP Server](mcp-server.md) for the
full tool reference and Codex/Cursor/Boost setup.

## Building the site

Docsmith's builder turns markdown into a static site:

```bash
docsmith build --source=./docs-source --output=./docs --title="My App"
```

## What happened to the old AI pipeline?

Earlier versions ran an internal AI pipeline (an SDK-based provider, a
dependency-free OpenAI-compatible HTTP provider, a review agent, an `agent:run`
command, and a structural `generate` command that produced shallow LLM-free
doc pages). In the v1 refactor those entry points were removed and AI is
**MCP-only** — Docsmith never calls an LLM itself, and compliance with your
coding agent's provider terms and keys is entirely up to that agent.

The removed work is preserved in the repository for anyone who wants it back:

- `backup/ai-pipeline/` — the SDK-free provider, reviewer agent, `agent:run`,
  and pre-strip copies of the pipeline, PHP API, CLI, tests, and docs
- `backup/laravel-ai-provider/` — the original `laravel/ai` SDK-based provider
- `backup/structural-pipeline/` — the structural `generate` pipeline, agents,
  media capture, and its tests

See [Future Scope](future-scope.md) for how these could be restored.
