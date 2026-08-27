# AI documentation

Ask your coding agent (Grok, Claude Code, Codex, Cursor, OpenCode) to write
docs. Docsmith gives it tools. Docsmith does not call an LLM and does not need
its own API key.

## Quick start

```bash
docsmith install:ai
```

Then in the agent:

> Write usage docs for this project. Put a quick start on the landing page.

Ask for screenshots or a demo video only if you want them:

> Add cropped screenshots and a short demo video of the UI. If you cannot
> boot a demo, ask me for a URL.

`install:ai` detects agents you have installed and writes:

- `.ai/skills/docsmith-docs/SKILL.md` (also copied into each agent's skills dir)
- `.mcp.json` for Claude Code, Cursor, Gemini CLI, Junie, Boost
- `.grok/config.toml` and `.grok/skills/` when Grok is installed
- `.codex/config.toml` when Codex is installed
- `opencode.json` when OpenCode is installed
- `.agents/mcp_config.json` when Antigravity is installed

```bash
docsmith install:ai --agents=grok,claude --source=./app --docs-source=./docs-source --force
docsmith install:ai --no-mcp        # skills only
docsmith install:ai --no-skills     # MCP config only
```

## What the agent should produce

A small set of usage pages, not one page per source file.

The landing page opens with a quick start and the most common use case. The
agent does not take screenshots or record videos unless you asked. When you
do, the widget is cropped with a little space around it, and login is not in
the recording unless the page is about login.

The agent uses `read_source`, `write_markdown`, and `build_site`. It uses
`capture_media` only when you asked for images or videos. See
[MCP Server](mcp-server.md).

## Build the site

```bash
docsmith build --source=./docs-source --output=./docs --title="My App"
```

You can also start the MCP server yourself:

```bash
docsmith mcp:serve --transport=stdio --source=./my-app --docs-source=./docs-source
```

## Old AI pipeline

Earlier versions called an LLM from Docsmith (`agent:run`, providers, a
reviewer). That is gone. Coding agents do the writing with their own keys.
The old code is in git history on `feat/AI`. See [Future Scope](future-scope.md).
