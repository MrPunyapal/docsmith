# Backup: AI pipeline (SDK-free, v1 refactor)

This folder preserves the AI **pipeline** implementation that was removed in the
v1 refactor. Per the project direction, AI lives **only** behind the MCP server
(coding agents drive `read_source` / `write_markdown` / `build_site` tools), and
the CLI/PHP-api AI pipeline was moved here.

## What's in this folder

- `Provider/` — the dependency-free OpenAI-compatible HTTP provider
  (`OpenAiHttpProvider` via cURL), its interface, and `ProviderConfig`. Replaced
  the `laravel/ai` SDK implementation (see `backup/laravel-ai-provider/`).
- `ReviewerAgent.php` — AI review agent (generated summary of generated docs).
- `AgentRunCommand.php` — `agent:run` CLI command for running agents manually.
- `Agent/DocWriterAgent.php` — pre-strip copy: includes the AI project planner
  (`runProject`, `planPages`, `writePage`, `generateWithAi`).
- `Pipeline/` — pre-strip copies of `GenerationPipeline`, `PipelineConfig`,
  `DocsmithGenerate` (the fluent `withAi()`/`reviewEnabled()` API).
- `Command/GenerateCommand.php` — pre-strip copy with `--ai-provider` /
  `--ai-model` / `--ai-api-key` / `--ai-base-url` options.
- `tests/` — agent/pipeline tests that exercised AI behavior.
- `docs/` — the original AI provider docs.

The linked `backup/laravel-ai-provider/` folder holds the older
`laravel/ai`-SDK-based provider.

## How to restore

Everything here (plus the pre-SDK implementations) is also preserved in git
history on branch `feat/AI`. To bring the AI pipeline back:

```bash
git checkout feat/AI -- src/Ai/Provider src/Ai/Agent/ReviewerAgent.php \
    src/Command/AgentRunCommand.php
git checkout feat/AI -- md/ai/provider-configuration.md
```

Then restore the AI wiring in `GenerateCommand`, `GenerationPipeline`,
`PipelineConfig`, and `DocsmithGenerate` (pre-strip copies are in
`Pipeline/` and `Command/` for reference).