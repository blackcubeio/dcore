# dcore — Extension

## Extension points

| Point | Where | Use |
|---|---|---|
| Own model layer | a namespace holding `X extends Blackcube\Dcore\Models\X` (or `Entities\X`) **for every model**: `fqcn()` resolves every relation into the namespace of `static::class` (`src/Traits/ScopedQueryTrait.php:49-57`), so a lone subclass breaks its relations | application-specific methods, another `ModelKind` policy, another Entity TTL |
| Visibility policy of an Entity | override `query()` in the subclass | other TTL, other scopes |
| New scope | a `ScopeInterface` class added with `addScope()` on a `ScopableQuery` subclass returned by `query()` | application filters |
| Slug generation | bind `Interfaces\SlugGeneratorInterface` to another class | other path rules |
| Preview state | bind `Interfaces\PreviewContextInterface` (`getData(): ?array`, `getJwt(): ?string`) | preview driven by something else than the session |
| JSON-LD | bind `Interfaces\JsonLdBuilderInterface` | other graphs |
| Formatters | a `FormatterInterface` class named in `#[Exportable(format:)]` / `#[Importable(format:)]`, or substituted through `MapperService` `formatterMap` | other value encodings |
| Export shape | `#[Exportable]` / `#[Importable]` on subclass members, or at class level with `property:` / `method:` for inherited ones | more or fewer fields in `export()` / `import()` / `describe()` |
| Data-driven structure | `ElasticSchema` rows (kinds, JSON Schema, `mdMapping`), `Type` rows (handler, allowed schemas), `SchemaSchema` mappings, `GlobalXeo` rows | no code |
| Routing | an `Interfaces\SsrRouteProviderInterface` implementation given to `HandlerDescriptorFactory` | other handler registries |
| Ordered pivots on a new model | `OrderedManagementTrait` with `getOrderedConfig()`; `TagManagementTrait` with `getTagPivotClass()` / `getTagPivotFkColumn()` | new ordered relations |

## Not extensible

| Element | Reason |
|---|---|
| The six dcore scopes and their names | `private registerDcoreScopes()` (`src/Models/ScopableQuery.php:94-103`) |
| Cache key and dependency format | `private cacheKey()` / `dependency()`; `ReusableDependency` hash `dcore.dep.<table>` |
| `fqcn()` namespace mapping | private static cache in `ScopedQueryTrait` |
| Route format `dcore-{type}-{id}` | `private static` regex and template in `Helpers\Element` (`src/Helpers/Element.php:32-38`) |
| Preview session key | `PreviewManager::SESSION_KEY = '_preview'`, read once |
| Entity TTL `3600` | literal in every `Entities\*::query()` |
| SEO bloc schema names (`Hero`, `FAQ`, `Image`, `Video`) and GlobalXeo kinds (`Organization`, `WebSite`, `Robots`, `Sitemap`) | literals in `JsonLdBuilder`, `RobotsService`, `SitemapService`; `Enums\JsonLdKind` holds three of them |
| Cascade delete of Content / Tag | the body of `delete()` |
| Translation rules | `linkTranslation()` / `unlinkTranslation()` bodies |
| Markdown grammar | `private` parsing methods of `ElasticMdService`, delimiters `:::start:` / `:::end` |
| Storage layout | `FileService::buildEntityPath()` (static), the `blocs/`, `xeos/`, `xeo/` segments |
| HTML subset of `ContentHelper` | `private const` tag lists |
