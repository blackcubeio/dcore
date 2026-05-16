# dcore — Public API

Blackcube headless CMS package. Provides models, services and handlers to manage contents, tags, menus, blocs, SEO and files.

- [Installation](installation.md)

## Capabilities per model

| Model | Tree structure | Dynamic properties | Blocs | Tags | Authors |
|---|---|---|---|---|---|
| `Content` | yes | yes | yes | yes | yes |
| `Tag` | yes | yes | yes | no | yes |
| `Bloc` | no | yes | no | no | no |
| `Menu` | yes | no | no | no | no |
| `GlobalXeo` | no | yes | no | no | no |

## Conventions

| Convention | Rule |
|---|---|
| Naming | `camelCase` everywhere |
| PK | `id` bigint auto-increment |
| Booleans | `isXxx()` (never `hasXxx()`) |
| Dates | `dateCreate`, `dateUpdate` (`DateTimeImmutable`, auto-managed) |
| FK | `userId`, `slugId`, `typeId` |
| Queries | `Model::query()` returns a `ScopedQuery` |
| Relations | `getXxxQuery()` returns `ActiveQueryInterface`, `getXxx()` returns the result |

## Models

### Main entities

| Model | Table | Description |
|---|---|---|
| `Content` | `contents` | Editorial content (page, article…). Tree-structured, typed, multilingual. |
| `Tag` | `tags` | Taxonomy (category, tag). Tree-structured, typed. |
| `Bloc` | `blocs` | Reusable content brick. Carries dynamic properties. |
| `Menu` | `menus` | Navigation item. Tree-structured, linked to a host and a language. |

### Support

| Model | Table | Description |
|---|---|---|
| `Slug` | `slugs` | URL (host + path). Can be a redirect (targetUrl + httpCode). |
| `Host` | `hosts` | Domain. `id=1` = wildcard. Carries siteName, siteDescription. |
| `Type` | `types` | Content/tag type. Carries the SSR handler and allowed schemas. |
| `Language` | `languages` | Available language. PK = string (e.g. `"fr"`). `main` flag. |
| `ElasticSchema` | `elasticSchemas` | JSON Schema for dynamic properties. 4 families (Common, Page, Bloc, Xeo). |
| `Author` | `authors` | Author (schema.org Person). Linked to Content and Tag. |
| `Xeo` | `xeos` | Per-URL SEO (title, description, OG, Twitter, JSON-LD…). |
| `GlobalXeo` | `globalXeos` | Global SEO per host + kind (Robots, Sitemap config…). |
| `Sitemap` | `sitemaps` | Per-URL sitemap configuration (frequency, priority). |
| `Parameter` | `parameters` | Key-value parameters per domain. Composite PK (domain, name). |
| `TranslationGroup` | `translationGroups` | Translation group linking Contents together. |

### Pivots

| Pivot | Links | Ordered |
|---|---|---|
| `ContentBloc` | Content ↔ Bloc | yes (`order`) |
| `TagBloc` | Tag ↔ Bloc | yes (`order`) |
| `XeoBloc` | Xeo ↔ Bloc | yes (`order`) |
| `ContentTag` | Content ↔ Tag | no |
| `ContentAuthor` | Content ↔ Author | yes (`order`) |
| `TagAuthor` | Tag ↔ Author | yes (`order`) |
| `TypeElasticSchema` | Type ↔ ElasticSchema | no |
| `SchemaSchema` | ElasticSchema ↔ ElasticSchema (regular → xeo) | no |

## Services

| Service | Role |
|---|---|
| `HandlerDescriptor` | Path → SSR handler resolution. CMS routing entry point. |
| `PreviewManager` | Session-based preview mode. Date simulation for pre-publication. |
| `SlugGenerator` | Automatic slug generation based on tree position. |
| `JsonLdBuilder` | JSON-LD structured data generation from Xeo + GlobalXeo. |
| `FileService` | File management (`@bltmp/` → `@blfs/`). Bloc and entity file processing. |
| `ElasticMdService` | Markdown export/import for Content and Tag. Public rendering via `renderMarkdown()`. |

## Helpers

| Helper | Role |
|---|---|
| `Element` | Lightweight CMS element reference. Route ↔ Content/Tag resolution (`dcore-c-{id}`, `dcore-t-{id}`). |
| `Xeo` | SEO data object built from a Slug's Xeo model. Used for template rendering. |
| `XeoOg` / `XeoTwitter` | Open Graph and Twitter Card data containers (used by `Xeo`). |
| `QueryCache` | Per-table cache dependency factory. |

## Data

| Class | Role |
|---|---|
| `ActiveQueryPaginator` | Page-based paginator for `ActiveQuery`. Implements `PaginatorInterface`. |

## Attributes

| Attribute | Target | Role |
|---|---|---|
| `#[Exportable]` | Property, Method | Marks a property/method for JSON export. Supports `name`, `format`, `base64`, `fields`. |

## Cross-cutting concepts

Each concept is documented in a dedicated file:

- [Tree structure](tree-structure.md) — Parent-child hierarchy (Content, Tag, Menu)
- [Dynamic properties](dynamic-properties.md) — Flexible JSON Schema (Content, Tag, Bloc, GlobalXeo)
- [Blocs](blocs.md) — Attaching and ordering blocs (Content, Tag)
- [Tags](tags.md) — Associating tags (Content)
- [Links and URLs](links-urls.md) — Slugs, Link PSR-13, CMS routing, Element helper
- [Queries and visibility](queries-visibility.md) — Query scopes, preview, caching, pagination
- [SEO and metadata](seo-metadata.md) — Xeo, GlobalXeo, sitemap, robots, JSON-LD
- [Authors](authors.md) — Author management (Content, Tag)
- [Translations](translations.md) — Translation groups (Content)
