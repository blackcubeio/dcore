# dcore — Links and routing

## Slug

`Models\Slug` (`src/Models/BaseSlug.php:55-165`): `hostId` (default 1), `path` (no leading slash, e.g. `fr/ia/savoir-faire`), `targetUrl` + `httpCode` for a redirect, `active`; relations `getHostQuery()`, `getXeoQuery()`, `getSitemapQuery()`, `getContentQuery()`, `getTagQuery()`; `getElement(): Content|Tag|null`; `getLink(): Link`: a redirect gives a `Link` to `targetUrl`, otherwise a `Link` on `//{host}/<path>` whose `{host}` is already replaced by the host name when `hostId > 1`.

## `Models\Link` (PSR-13, `src/Models/Link.php`)

`Link(string $rel = '', string $href = '')`, `BASE_HREF = '//{host}'`.

| Method | Behaviour |
|---|---|
| `getHref(): string` | the href with `//{host}` removed: a templated CMS link reads as `/<path>` (`:41-44`) |
| `withTemplate(string $template, string $value): static` | replaces `{<template>}`; `withTemplate('host', $hostname)->getHref()` gives `//<hostname>/<path>` (`:70-84`) |
| `isTemplated()` | contains `{` or `}` |
| `getRels()`, `withRel()`, `withoutRel()`, `getAttributes()`, `withAttribute()`, `withoutAttribute()`, `withHref()` | immutable withers |

`Traits\LinkTrait::getLink(): ?Link` on Content and Tag: the slug link with `hrefLang` = the model language (`src/Traits/LinkTrait.php:24-36`).

## Element routes (`Helpers\Element`, `src/Helpers/Element.php`)

Route format `dcore-c-<id>` (content) / `dcore-t-<id>` (tag), regex `/^dcore-(c|t)-(\d+)$/` (`:32-38`).

| Method | Behaviour |
|---|---|
| `Element::createFromRoute(string $route, ModelKind $modelKind = ModelKind::Entity): ?self` | parses only; `null` for another string (`:51-59`) |
| `Element::createFromModel(Content|Tag)` | kind from the class: `Models\` → `Model`, `Entities\` → `Entity` (`:97-119`) |
| `Element::createFromSlug(Slug)` | from `$slug->getElement()` |
| `getType()` (`content` / `tag`), `getId()`, `toRoute()` | |
| `getModel()`, `getModelQuery(): ?ScopableQuery` | lazy, through `Entities\Content::query()` or `Models\Content::query()` by kind (`:138-158`): an Entity-kind element hides unpublished rows |
| `Element::mapFromRoute()`, `mapToRoute(['type' => …, 'id' => …])` | pure string conversions |
| `Element::getLink(string $route): ?string` | the public href of the slug of the Entity (`:173-178`) |
| `Element::getUrlsFromRoutes(string|array|ActiveQuery $routes): array<string, ?string>` | batch: one query for contents, one for tags, `slug` eager-loaded, static cache per process, Entities only (`:189-240`) |
| `Element::extractUrl(object $source, array $fields): ?string` | first non-empty field: an element route resolved to its href, any other value returned as is (`:251-266`) |

## Handler resolution

`Services\HandlerDescriptorFactory(SsrRouteProviderInterface $routeProvider)` (`src/Services/HandlerDescriptorFactory.php`):

| Method | Behaviour |
|---|---|
| `fromElement(Content|Tag $element): ?HandlerDescriptor` | route = `Type::getHandler()` of the element; info = `getHandlerInfo($route)` else `getErrorHandlerInfoByRoute($route)` (`['class', 'mode', 'method', 'expects']`); `null` without handler or info; the descriptor data is lazy (`:51-69`) |
| `fromError(string $route, string $languageId): ?HandlerDescriptor` | the `Type` with that handler, then the first active Content (then Tag) of that type in the language, else in any language, then `fromElement()` (`:75-104`) |
| `simple(string $class, string $mode, array $expects = [], ?Closure $dataLoader = null)` | special routes (sitemap, robots, …) |

`HandlerDescriptor` (`src/Services/HandlerDescriptor.php`): `getClass()`, `getMode()` (`construct` / `method`), `getMethod()`, `getExpects()`, `getData()` (lazy, integer-indexed so the Yii injector matches by type). Data built from `expects` (`buildElementData()`, `:110-146`): `Entities\Content::class` / `Entities\Tag::class` (`RuntimeException` on a mismatch), `'Content|Tag'`, `Helpers\Element::class`, `Entities\Slug::class`. `Interfaces\SsrRouteProviderInterface`: `getAvailableRoutes(): array`, `getHandlerInfo(string $route): ?array`, `getErrorHandlerInfoByRoute(string $route): ?array`, implemented by `blackcube/ssr`. Special routes (`robots.txt`, `sitemap.xml`, `llms.txt`, `llms-full.txt`, `<path>.md`) and redirects are handled before any descriptor, by the ssr middleware.

## Slug generation

`Services\HazeltreeSlugGenerator::getElementSlug(Content|Tag $element): string` (`src/Services/HazeltreeSlugGenerator.php:28-104`), bound to `Interfaces\SlugGeneratorInterface`: `urlize(name)` (NFD transliteration, lowercase, `[^a-z0-9]+` → `-`) placed under the path of the nearest ancestor that has a slug, the ancestors without slug contributing their urlized name; made unique with a `-001`, `-002` suffix against the existing `slugs.path` values. The slug row itself is created by the caller.
