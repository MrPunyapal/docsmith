# Installation

## Requirements

- PHP 8.3 or newer
- Composer

## Install the package

```bash
composer require --dev mrpunyapal/docsmith
```

## Install the AI agent skills

Docsmith ships two [Agent Skills](https://agentskills.io) compatible skills:

- `docsmith-development`: teaches agents the package itself, build options, frontmatter keys, versioned docs, docs hubs, remote source syncing, and Open Graph images.
- `docs-writing`: teaches agents how to write documentation pages that build cleanly and read well.

They work with coding agents such as Claude Code, Cursor, Codex, and OpenCode.

### Via Laravel Boost

If your Laravel project uses [Boost](https://laravel.com/docs/boost), the skill installs automatically because Docsmith is in your `composer.json`:

```bash
php artisan boost:install
```

You can also add it directly from this repository:

```bash
php artisan boost:add-skill MrPunyapal/docsmith/resources/boost/skills
```

### Via the skills CLI

Any agent supported by the [skills CLI](https://skills.sh) can install it too:

```bash
npx skills add MrPunyapal/docsmith/resources/boost/skills
```

After installing, ask your agent to activate the `docsmith-development` skill when it works on documentation builds, and `docs-writing` when it writes or edits documentation pages.

## Build documentation

Docsmith builds a static site from any Markdown directory, either from PHP or from the command line.

### Command line

After installing the package, the binary is available at `vendor/bin/docsmith`:

```bash
vendor/bin/docsmith build --source=md --output=docs --title="Docsmith"
```

Run `vendor/bin/docsmith --help` for the full list of options.

### PHP

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/docs',
    title: 'Docsmith',
    description: 'Craft static documentation sites from Markdown with minimal setup.',
);
```

This setup keeps the Markdown source in `md/` and writes the generated site into `docs/`. The entry page is written to `docs/index.html`.
