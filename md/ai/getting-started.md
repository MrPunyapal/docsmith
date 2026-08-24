# AI Documentation with Docsmith

Docsmith's AI story is **coding-agent driven**: your AI assistant (Claude Code,
Codex, Cursor, or any MCP-capable agent) does the writing; Docsmith provides the
tools. There is no API pipeline to configure, no SDK, and Docsmith never calls
an LLM itself.

## One-Command Setup

From the project root:

```bash
docsmith install:ai
```

This detects your installed agents and writes everything they need:

- `.ai/skills/docsmith-docs/SKILL.md` — the canonical doc-writing skill
  (single source of truth, also deployed to each agent's own skill directory)
- `.mcp.json` — the docsmith MCP server entry (Claude Code, Cursor, Gemini CLI,
  Junie, and Boost all read this; an existing Boost-generated file is merged,
  not clobbered)
- `.claude/skills/`, `.cursor/skills/`, `.agents/skills/`, `.opencode/skills/`
  — the skill for each installed agent (Codex and Antigravity share `.agents/skills`)
- `.codex/config.toml` — MCP server entry (only when Codex is installed)
- `opencode.json` — MCP server entry (only when OpenCode is installed)
- `.agents/mcp_config.json` — Antigravity MCP server entry

Skills load on demand (progressive disclosure), so docsmith guidance costs zero
tokens until the agent actually writes docs — no always-on rules files needed.

Pin agents or paths explicitly:

```bash
docsmith install:ai --agents=claude,codex --source=./app --docs-source=./docs-source --force
```

Or install only part of the setup:

```bash
docsmith install:ai --no-mcp        # skills only
docsmith install:ai --no-skills     # MCP configuration only
```

Then open your coding agent in the project and ask it to write documentation.

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

The removed code lives in git history (pre-refactor commits on `feat/AI`).
See [Future Scope](future-scope.md) for why the coding-agent architecture
replaced it.
