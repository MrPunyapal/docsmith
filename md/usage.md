# Usage

## Static API

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/dist',
    title: 'Project Docs',
    description: 'Internal package documentation.',
);
```

## Fluent API

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Internal package documentation.')
    ->build();
```

## Current output model

Each Markdown file becomes an HTML page.

- `md/index.md` becomes `index.html`
- `md/installation.md` becomes `installation/index.html`
- `md/guides/configuration.md` becomes `guides/configuration/index.html`

If the source directory does not contain an `index.md`, Docsmith generates a landing page automatically.

## README index compatibility mode

Docsmith can import README index formats used by existing projects like `laravel-undocumented` and `laravel-attributes-list`.

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->readmeIndex(__DIR__ . '/README.md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Generated from README index.')
    ->build();
```

Supported README item styles:

- `- [withAggregate()](features/eloquent/withAggregate.md) — description`
- `* [`#[Table]`](attributes/eloquent/Table.md) — description`
