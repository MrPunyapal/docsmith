# Structural Pipeline (Backup)

Snapshot of the structural documentation pipeline removed in the MCP-only
refactor. The v1 product surface is:

- `docsmith build` — static site builder from markdown sources
- `docsmith mcp:serve` — MCP server (`read_source`, `write_markdown`, `build_site`)
  for coding agents (Claude Code, Codex, Cursor, Laravel Boost)

The structural pipeline generated shallow, LLM-free doc pages (features,
classes, functions) with no parameter detail and unreliable attribution, so it
was moved here. Coding agents already get structure analysis through the
`read_source` tool (`list_files` / `read_file` / `analyze_structure`) and write
real docs through `write_markdown`.

## Inventory

| Path | Contents |
|------|----------|
| `Command/GenerateCommand.php` | `generate` console command |
| `Ai/Agent/` | `AgentInterface`, `CodeScanAgent` (with dependency-dir exclusions), `DocWriterAgent`, `MediaAgent` |
| `Ai/Pipeline/` | `DocsmithGenerate`, `GenerationPipeline`, `PipelineConfig`, `PipelineResult` |
| `Ai/Media/` | `MediaEmbedder`, `MediaStorage`, `ScreenshotCapture`, `VideoRecorder` |
| `tests/` | `GenerationPipelineTest`, `CodeScanAgentTest`, `DocWriterAgentTest` |
| `md/ai/media-capture.md` | Media capture documentation |
| `ai-test/README.md` | Dev scratchpad for the AI pipeline |

## Restore

Copy the directories back into `src/` / `tests/` / `md/ai/`:

```powershell
Copy-Item backup\structural-pipeline\Command\GenerateCommand.php src\Command\
Copy-Item backup\structural-pipeline\Ai\Agent\* src\Ai\Agent\
Copy-Item backup\structural-pipeline\Ai\Pipeline\* src\Ai\Pipeline\
Copy-Item backup\structural-pipeline\Ai\Media\* src\Ai\Media\
```

Then re-add in `src/Docsmith.php`:

```php
use Docsmith\Ai\Pipeline\DocsmithGenerate;

public static function generate(): DocsmithGenerate
{
    return new DocsmithGenerate();
}
```

and the `generate` dispatch in `bin/docsmith` (see git history of those files).
