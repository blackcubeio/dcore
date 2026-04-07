# Blackcube dcore

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/dcore.svg)](https://packagist.org/packages/blackcube/dcore)

dcore is the data layer of Blackcube CMS — models, trees, dynamic properties, SEO, translations. **JSON Schema + virtual columns, no EAV.** Your data stays yours: your own tables, your server, your backups.

It is consumed by two packages on top:

- **[blackcube/dboard](https://github.com/blackcubeio/dboard)** — admin backoffice (content editing, CRUD)
- **[blackcube/ssr](https://github.com/blackcubeio/ssr)** — public front (routing, SEO, handlers)

You do not consume dcore directly. Pick dboard for admin, ssr for front, or both.

## Where dcore sits

```
┌────────────────────────┐    ┌────────────────────────┐
│ dboard                 │    │ ssr                    │
│ admin backoffice       │    │ public front           │
│ (editing, CRUD)        │    │ (routing, SEO, handlers)│
└──────────┬─────────────┘    └───────────┬────────────┘
           │                              │
           └──────────────┬───────────────┘
                          ↓
                    ┌──────────┐
                    │  dcore   │  ← data layer
                    └──────────┘
                          ↓
                         DB
```

## Requirements

- Relational DB with JSON support

## Installation

```bash
composer require blackcube/dcore
```

## Getting started

dcore is not used alone. Start with a demo app:

| Framework | Demo repo |
|---|---|
| Yii3 | [blackcube/yii-app](https://github.com/blackcubeio/yii-app) |
| Slim | [blackcube/slim-app](https://github.com/blackcubeio/slim-app) |
| Laravel | [blackcube/laravel-app](https://github.com/blackcubeio/laravel-app) |

Each demo shows how to wire ssr into its framework, how to write handlers with `#[RoutingHandler]`, and how to consume dcore data.

## Why dcore?

| Approach | Problem |
|----------|---------|
| Full-stack CMS | Monolith. You want a page? Take the whole engine. |
| Headless CMS (SaaS) | Vendor lock-in. Your data lives elsewhere. |
| Raw DB access | 15 models, 8 pivots, tree management, JSON schema validation — from scratch? |
| **dcore** | None of the above |

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

## Models vs Entities

dcore provides two namespaces for each model:

| Namespace | Default scope | Use case |
|---|---|---|
| `Blackcube\Dcore\Models\Content` | none | Admin, backoffice |
| `Blackcube\Dcore\Entities\Content` | `->publishable()` | Frontend, public rendering |

Entities extend Models and override `query()` to automatically filter by publishable status (active + dates + ancestors). Use Models when you need full access, Entities when you render for visitors.

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
| `PreviewManager` | Reads preview state (active flag + date simulation) from session. |
| `HazeltreeSlugGenerator` | Automatic slug generation based on tree position. |

## Documentation

Detailed documentation:

- [API overview](docs/index.md)

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>
