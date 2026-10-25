# blackcube/dcore — LLM index

**Role:** the data layer of the Blackcube CMS: 25 ActiveRecord models with their migrations (contents, tags, blocs, menus, llm menus, slugs, hosts, types, languages, elastic schemas, authors, xeos, global xeos, sitemaps, parameters, translation groups, pivots), each in two flavours (admin `Models\`, front `Entities\`); a `ScopableQuery` with visibility scopes, tree navigation, Elastic virtual columns and per-table cached results; the services around routing, slugs, files, SEO (per-URL Xeo, JSON-LD, sitemap, robots, llms.txt, markdown) and JSON / markdown import-export. Consumed by `blackcube/dboard` (admin), `blackcube/ssr` (front), `blackcube/mcp`, `blackcube/graphql`.

## Package card

| | |
|---|---|
| Namespace | `Blackcube\Dcore` |
| Models | `Models\BaseXxx` (abstract: columns, relations, export attributes) + `Models\Xxx` (traits, scopes, business methods) for Author, Bloc, Content, ContentAuthor, ContentBloc, ContentTag, ContentTranslationGroup, ElasticSchema, GlobalXeo, Host, Language, LlmMenu, Menu, Parameter, SchemaSchema, Sitemap, Slug, Tag, TagAuthor, TagBloc, TagTranslationGroup, Type, TypeElasticSchema, Xeo, XeoBloc; `Entities\Xxx extends Models\Xxx` for each; `Models\Link` (PSR-13); `Models\Formatters\DateFormatter`, `FileFormatter` |
| Queries | `Models\ScopableQuery`, `ElasticQuery`, `HazeltreeQuery`, `ElasticHazeltreeQuery`; scopes `Models\Scopes\ActiveScope`, `LanguageScope`, `AtDateScope`, `AvailableScope`, `PublishableScope`, `CacheScope`; `Data\ActiveQueryPaginator`, `Data\EmptyDataReader` |
| Traits | `Traits\ScopedQueryTrait`, `ModelKindTrait`, `LinkTrait`, `OrderedManagementTrait`, `TagManagementTrait` |
| Helpers | `Helpers\Element`, `Xeo`, `XeoOg`, `XeoTwitter`, `QueryCache`, `ReusableDependency`, `QueryExpressions`, `HostHelper`, `ParameterHelper`, `SearchHelper`, `ContentHelper` |
| Services | `Services\HandlerDescriptor`, `HandlerDescriptorFactory`, `PreviewManager`, `PreviewContext`, `HazeltreeSlugGenerator`, `FileService`, `MapperService`, `ImportService`, `ElasticMdService`, `XeoRefreshService`, `JsonLdBuilder`, `Services\Xeo\LlmsService`, `MdService`, `RobotsService`, `SitemapService` |
| Attributes, enums, interfaces | `Attributes\Exportable`, `Importable`, `ResetQueryCacheOnWrite`; `Enums\ElasticSchemaKind`, `JsonLdKind`, `ModelKind`; `Interfaces\FormatterInterface`, `JsonLdBuilderInterface`, `PreviewContextInterface`, `PreviewManagerInterface`, `SlugGeneratorInterface`, `SsrRouteProviderInterface` |
| Migrations, i18n | `Migrations\M000000000000CreateHosts` … `M000000000024CreateLlmMenus` (25 tables); `i18n/schemas/{de,en,es,fr,it,pt}.php` labels of the built-in schemas |
| Requires | PHP ^8.2, `blackcube/active-record` ^1.0, `blackcube/fileprovider` ^1.0, `blackcube/injector` ^1.0, `psr/http-message` ^2.0, `psr/http-server-handler` ^1.0, `psr/link` ^2.0, `psr/log` ^3.0, `yiisoft/active-record` ^1.1, `yiisoft/aliases` ^3.1, `yiisoft/cache` ^3.2, `yiisoft/data` ^2.0, `yiisoft/db` ^2.1, `yiisoft/db-migration` ^2.2, `yiisoft/db-mysql` ^2.0, `yiisoft/log` ^2.2, `yiisoft/log-target-file` ^3.1. MySQL / MariaDB. |
| Yii config | config-plugin: `config/common/params.php` (empty), `config/common/di.php` (the four interface bindings), `config/common/bootstrap.php` (`QueryExpressions::register()` on the connection). |
| License | BSD-3-Clause |

## Rules

- **Models are admin, Entities are front.** `Entities\X extends Models\X` with `$modelKind = ModelKind::Entity` and `query()` = `parent::query()->publishable()->cache(ttl: 3600)` (`src/Entities/Content.php:26-35`). A relation loaded from an Entity is publishable too: `ScopedQueryTrait::createRelationQuery()` sets `deferPublishable`, applied in `ScopableQuery::prepare()` (`src/Traits/ScopedQueryTrait.php:35-45`, `src/Models/ScopableQuery.php:105-112`). An admin tool reads and writes through Models; Models never cache.
- `fqcn()` keeps the namespace: a relation declared in `BaseX` through `$this->fqcn(Y::class)` resolves to `Entities\Y` from an Entity and to `Models\Y` from a Model (`src/Traits/ScopedQueryTrait.php:49-57`). The same mechanism forces any subclassing namespace to carry the whole set of models.
- Every `query()` is a `ScopableQuery` with the six dcore scopes (`ScopableQuery::create()`, `src/Models/ScopableQuery.php:64-69`); `createFor()` picks the Elastic / Hazeltree / both subclass from the interfaces of the model (`:74-92`).
- Visibility: `active()` filters the flag, `available()` adds the date window, `publishable()` adds every ancestor; preview mode (a `PreviewManagerInterface` in `Injector`) removes the `active` filter and may simulate a date (`src/Models/Scopes/AvailableScope.php:37-97`, `PublishableScope.php:40-124`).
- Cache: `cache(ttl:)` stores `one()`, `all()` and `scalar()` under `dcore.<table>.<sha1(method:rawSql)>` with a per-table `MAX(dateUpdate)` dependency; `each()` is never cached; without a `CacheInterface` in `Injector` the query runs uncached (`src/Models/ScopableQuery.php:114-224`). Every final Model carries `#[ResetQueryCacheOnWrite]` so a write in the same request resets the reusable dependency (`src/Attributes/ResetQueryCacheOnWrite.php`).
- Tree order is `natural()` / `reverse()`; moves go through `saveInto()` / `saveBefore()` / `saveAfter()` of `blackcube/active-record`.
- Blocs are owned (deleted when detached), authors and tags are shared; attach / detach / move run in a transaction (`src/Traits/OrderedManagementTrait.php`, `TagManagementTrait.php`).
- `Content::delete()` and `Tag::delete()` cascade: subtree, slug, orphan blocs, empty translation group, in one transaction (`src/Models/Content.php:311-381`, `Tag.php:298`).
- Element routes are `dcore-c-<id>` / `dcore-t-<id>` (`Helpers\Element`); `Element::createFromRoute()` builds an Entity-kind element by default, pass `ModelKind::Model` for admin reads (`src/Helpers/Element.php:51-59`).
- `dateCreate` / `dateUpdate` are set by `#[DefaultDateTimeOnInsert]` / `#[SetDateTimeOnUpdate]` repeated on every final Model, since PHP attributes are not inherited (`src/Models/Content.php:33-43`).
- `Injector::init($container)` is a prerequisite: scopes, cache, dependencies, formatters, markdown and `CacheFile` all go through `Blackcube\Injector\Injector`.

## Parts

| File | Content | Read when |
|---|---|---|
| `queries.md` | `ScopableQuery` and its subclasses, the six scopes and their SQL, reference date and preview, cache keys and dependencies, emulated execution, pagination, relevance search, subquery builders. | before writing a query |
| `models.md` | Base / final / Entity layering, capabilities per model, tables and pivots, the Content and Tag API (blocs, tags, authors, translations, cascade delete), schemas and types, hosts, languages, parameters, menus. | before touching a model |
| `links.md` | Slug and `Link`, `LinkTrait`, `Element` routes and batch URL resolution, host resolution, `HandlerDescriptorFactory` and the SSR route provider, slug generation. | before resolving a URL or a route |
| `xeo.md` | Xeo, GlobalXeo, Sitemap models, the `Xeo` helper, `JsonLdBuilder`, `XeoRefreshService`, robots / sitemap / markdown / llms services. | before rendering SEO data |
| `files.md` | `FileService`: storage layout per entity, temp → permanent moves, duplication, deletion, file fields of a schema. | before saving a file-carrying bloc |
| `mapper.md` | `#[Exportable]` / `#[Importable]`, `MapperService` export / import / describe, formatters, `ImportService` JSON import, `ElasticMdService` markdown export / render / import, `ContentHelper`. | before exporting, importing or rendering markdown |
| `integration.md` | config-plugin, what the container must provide, migrations, consumers, tests. | before plugging the package in |
| `extension.md` | What extends (subclassing a namespace, scopes, bindings, formatters, attributes, data-driven schemas) and what does not. | before extending |
