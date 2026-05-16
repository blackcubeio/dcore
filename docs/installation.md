# Installation

```bash
composer require blackcube/dcore
```

## Requirements

- PHP 8.1+
- Relational DB with JSON support

## Configuration

The package uses `config-plugin` for automatic Yii3 registration. Two files are auto-loaded:

- `config/common/params.php` — parameters
- `config/common/di.php` — DI container definitions

### DI bindings

| Interface | Default implementation |
|---|---|
| `SlugGeneratorInterface` | `HazeltreeSlugGenerator` |
| `PreviewManagerInterface` | `PreviewManager` |
| `PreviewContextInterface` | `PreviewContext` |
| `JsonLdBuilderInterface` | `JsonLdBuilder` |

### Alias

`@dcore` points to the package `src/` directory.

## Migrations

The package registers its migration namespace automatically:

```php
'yiisoft/db-migration' => [
    'sourceNamespaces' => [
        'Blackcube\Dcore\Migrations',
    ],
],
```

Run migrations to create the 23 CMS tables:

```bash
./yii migrate/up
```
