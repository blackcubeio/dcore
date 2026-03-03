# Blackcube dcore

CMS data layer — models, trees, dynamic properties, SEO, translations.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/dcore.svg)](https://packagist.org/packages/blackcube/dcore)

## Installation

```bash
composer require blackcube/dcore
```

## Requirements

- PHP >= 8.3
- MySQL/MariaDB (for JSON column and tree support)

## Why dcore?

| Approach | Problem |
|----------|---------|
| Full-stack CMS | Monolith. You want a page? Take the whole engine. |
| Headless CMS (SaaS) | Vendor lock-in. Your data lives elsewhere. |
| Raw ActiveRecord | 15 models, 8 pivots, tree management, JSON schema validation — from scratch? |
| **dcore** | None of the above |

**Your data stays yours.** MySQL tables, your server, your backups.

**Trees without recursion.** Content, Tag and Menu are tree-structured via [Hazeltree](https://github.com/blackcubeio/hazeltree) — one query, no cache.

**Dynamic properties without EAV.** JSON Schema validation, virtual columns, transparent queries via [Elastic](https://github.com/blackcubeio/elastic).

**SEO is built in.** Per-URL metadata (Xeo), global config (GlobalXeo), sitemap.xml, robots.txt — not bolted on after.

**Multilingual by design.** Translation groups link Contents across languages. Not an afterthought.

## Capabilities per model

| Model | Tree | Dynamic properties | Blocs | Tags | Authors |
|---|---|---|---|---|---|
| `Content` | yes | yes | yes | yes | yes |
| `Tag` | yes | yes | yes | no | yes |
| `Bloc` | no | yes | no | no | no |
| `Menu` | yes | no | no | no | no |
| `GlobalXeo` | no | yes | no | no | no |

## Quick Start

### 0. Bootstrap the database connection

dcore needs a `Yiisoft\Db\Connection\ConnectionInterface` registered in your DI container, and `ConnectionProvider::set()` called at bootstrap.

```php
// DI container
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection;
use Yiisoft\Db\Mysql\Driver;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Cache\ArrayCache;

return [
    ConnectionInterface::class => static function (): ConnectionInterface {
        $driver = new Driver("mysql:host=localhost;dbname=mydb;port=3306", 'user', 'password');
        $driver->charset('UTF8MB4');
        return new Connection($driver, new SchemaCache(new ArrayCache()));
    },
];
```

```php
// Bootstrap
use Psr\Container\ContainerInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

return [
    static function (ContainerInterface $container): void {
        ConnectionProvider::set($container->get(ConnectionInterface::class));
    },
];
```

### 1. Run migrations

```bash
php yii.php migrate:up
```

### 2. Create a content type

```php
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Enums\ElasticSchemaKind;

$schema = new ElasticSchema();
$schema->setName('Article');
$schema->setSchema(json_encode([
    'type' => 'object',
    'properties' => [
        'subtitle' => ['type' => 'string'],
        'image' => ['type' => 'string'],
        'body' => ['type' => 'string'],
    ],
]));
$schema->setKind(ElasticSchemaKind::Page);
$schema->setActive(true);
$schema->save();

$type = new Type();
$type->setName('Article');
$type->setHandler('article');
$type->setContentAllowed(true);
$type->save();
```

### 3. Create content in a tree

```php
use Blackcube\Dcore\Models\Content;

$blog = new Content();
$blog->setName('Blog');
$blog->setLanguageId('en');
$blog->setTypeId($type->getId());
$blog->setElasticSchemaId($schema->getId());
$blog->save(); // root node

$article = new Content();
$article->setName('First Post');
$article->setLanguageId('en');
$article->setTypeId($type->getId());
$article->setElasticSchemaId($schema->getId());
$article->subtitle = 'Hello world';
$article->saveInto($blog); // child of Blog
```

### 4. Attach blocs

```php
use Blackcube\Dcore\Models\Bloc;

$bloc = new Bloc();
$bloc->setElasticSchemaId($blocSchema->getId());
$bloc->setActive(true);
$bloc->save();

$article->attachBloc($bloc);       // appends at last position
$article->attachBloc($bloc2, 1);   // inserts at position 1
$article->moveBlocUp($bloc2);      // reorder
```

### 5. Query with visibility scopes

```php
// Published content in French, root nodes only
Content::query()
    ->language('fr')
    ->publishable()
    ->roots()
    ->all();

// Children of a node, active only
Content::query()
    ->forNode($blog)
    ->children()
    ->active()
    ->all();

// Filter on dynamic JSON properties
Content::query()
    ->available()
    ->andWhere(['subtitle' => 'Hello world'])
    ->all();
```

### 6. SEO

```php
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Models\Sitemap;

$xeo = new Xeo();
$xeo->setSlugId($slug->getId());
$xeo->setTitle('First Post — My Blog');
$xeo->setDescription('An introduction to dcore.');
$xeo->setOg(true);
$xeo->setOgType('article');
$xeo->setActive(true);
$xeo->save();

$sitemap = new Sitemap();
$sitemap->setSlugId($slug->getId());
$sitemap->setFrequency('weekly');
$sitemap->setPriority(0.8);
$sitemap->setActive(true);
$sitemap->save();
```

### 7. Translations

```php
$contentFr->linkTranslation($contentEn);

$translations = $contentFr->getTranslationsQuery()->all();
$english = $contentFr->getTranslationsQuery()
    ->andWhere(['languageId' => 'en'])
    ->one();
```

## Models vs Entities

dcore provides two namespaces for each model:

| Namespace | Default scope | Use case |
|---|---|---|
| `Blackcube\Dcore\Models\Content` | none | Admin, backoffice |
| `Blackcube\Dcore\Entities\Content` | `->publishable()` | Frontend, public rendering |

Entities extend Models and override `query()` to automatically filter by publishable status (active + dates + ancestors). Use Models when you need full access, Entities when you render for visitors.

```php
// Backoffice — see everything
use Blackcube\Dcore\Models\Content;
Content::query()->all();

// Frontend — only published content
use Blackcube\Dcore\Entities\Content;
Content::query()->language('fr')->roots()->all();
// publishable() is already applied
```

## Architecture

### Models

| Model | Table | Description |
|---|---|---|
| `Content` | `contents` | Editorial content. Tree-structured, typed, multilingual. |
| `Tag` | `tags` | Taxonomy. Tree-structured, typed. |
| `Bloc` | `blocs` | Reusable content brick. Carries dynamic properties. |
| `Menu` | `menus` | Navigation item. Tree-structured, linked to host and language. |
| `Slug` | `slugs` | URL (host + path). Can be a redirect. |
| `Host` | `hosts` | Domain. `id=1` = wildcard. |
| `Type` | `types` | Content/tag type. Carries SSR handler and allowed schemas. |
| `Language` | `languages` | Available language. String PK. |
| `ElasticSchema` | `elasticSchemas` | JSON Schema for dynamic properties. |
| `Author` | `authors` | Author (schema.org Person). |
| `Xeo` | `xeos` | Per-URL SEO metadata. |
| `GlobalXeo` | `globalXeos` | Global SEO per host + kind. |
| `Sitemap` | `sitemaps` | Per-URL sitemap configuration. |
| `Parameter` | `parameters` | Key-value parameters per domain. |
| `TranslationGroup` | `translationGroups` | Translation group linking Contents. |

### Services

| Service | Role |
|---|---|
| `HandlerDescriptor` | Path → SSR handler resolution. CMS routing entry point. |
| `PreviewManager` | HMAC-signed preview mode. Date simulation for pre-publication. |
| `HazeltreeSlugGenerator` | Automatic slug generation based on tree position. |

### HTTP Handlers

| Handler | Route | Role |
|---|---|---|
| `SitemapHandler` | `sitemap.xml` | Generates the XML sitemap. |
| `RobotsHandler` | `robots.txt` | Generates robots.txt from GlobalXeo. |
| `RedirectHandler` | (slug redirect) | Redirects according to Slug targetUrl/httpCode. |

## Documentation

Detailed documentation is available in two languages:

- [English](docs/en/index.md)

## Let's be honest

**This is a data layer, not a CMS.**

There is no admin panel, no page builder, no theme engine. dcore gives you models, queries, and handlers. You build the application around it.

**Yii3 ActiveRecord.**

dcore depends on `yiisoft/active-record`. Any framework works as long as you provide a `Yiisoft\Db\Connection\ConnectionInterface` in the DI container.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>
