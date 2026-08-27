# Architecture

## Build pipeline

1. `Docsmith` exposes the static and fluent API.
2. `Builder` collects configuration: versions, hub entries, LLM export, theme, Open Graph, and more.
3. `BuildConfig` validates the source and output paths.
4. `SourceScanner` discovers Markdown files and reads frontmatter. Versioned builds scan one source directory per version.
5. `CommonMarkRenderer` converts Markdown to HTML through League CommonMark with GitHub-flavored Markdown extensions.
6. `SiteBuilder` renders pages with sidebar navigation, version pills, and the hub dropdown, and writes them to the output directory. It rewrites relative media references so images, videos, and downloads resolve from the built page locations.
7. `AssetPublisher` publishes CSS and JS assets, minifying them through `AssetMinifier` before writing, and generates `search-index.json`, `sitemap.xml`, `.nojekyll`, and the LLM export files.
8. `MediaPublisher` copies image, video, audio, and PDF files from the source tree into the output directory, preserving their relative structure (disable with `publishMedia(false)`).

Remote source syncing lives in `src/RemoteSources/` and runs before a build. It only writes local directories; the pipeline above never talks to the network.

## Document model

Every discovered Markdown file becomes a `Document` object containing:

- source path
- relative path
- output path
- title
- raw Markdown
- rendered HTML
