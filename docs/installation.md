# Installation

```bash
composer require blackcube/dcore
```

## Requirements

- PHP 8.2+
- Relational DB with JSON support

## Configuration

The package uses `config-plugin` for automatic Yii3 registration. Four files are auto-loaded:

- `config/common/params.php` — parameters: the `addons` and `upgrades` lists of `blackcube/dcore`, filled by the add-ons and the modules
- `config/console/params.php` — the `upgrade` console command
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

Run migrations to create the 26 CMS tables:

```bash
./yii migrate/up
```

On an existing base, apply the upgrades the installed packages declare before the migrations (see [Add-ons and upgrades](addons.md)):

```bash
./yii upgrade
./yii migrate/up
```
