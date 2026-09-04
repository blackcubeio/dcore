# Installation

```bash
composer require blackcube/dcore
```

## Requirements

- PHP 8.4+
- Relational DB with JSON support

## Configuration

The package uses `config-plugin` for automatic Yii3 registration. Three files are auto-loaded:

- `config/common/params.php` — parameters
- `config/common/di.php` — DI container definitions
- `config/common/bootstrap.php` — registers the `QueryExpressions` builders on the connection

### DI bindings

| Interface | Default implementation |
|---|---|
| `SlugGeneratorInterface` | `HazeltreeSlugGenerator` |
| `PreviewManagerInterface` | `PreviewManager` |
| `PreviewContextInterface` | `PreviewContext` |
| `JsonLdBuilderInterface` | `JsonLdBuilder` |

## Migrations

The application registers the migration namespace in its own params:

```php
'yiisoft/db-migration' => [
    'sourceNamespaces' => [
        'Blackcube\Dcore\Migrations',
    ],
],
```

Run migrations to create the 25 CMS tables:

```bash
./yii migrate/up
```
