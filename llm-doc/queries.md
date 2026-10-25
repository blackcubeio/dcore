# dcore — Queries

## Classes

`Models\ScopableQuery extends Yiisoft\ActiveRecord\ActiveQuery` implements `ScopableQueryInterface`, `FormulaeExpressionInterface`, with `ScopableTrait`, `QualifyColumnTrait`, `BuildFormulaeTrait`, `BatchedEachQueryTrait` of `blackcube/active-record` (`src/Models/ScopableQuery.php:52-59`).

| Factory | Behaviour |
|---|---|
| `ScopableQuery::create(ActiveRecordInterface|string $modelClass): static` | `new static($modelClass)` + the six dcore scopes (`:64-69`) |
| `ScopableQuery::createFor(string|ActiveRecordInterface $modelClass): self` | `ElasticHazeltreeQuery` when the model implements `ElasticInterface` and `HazeltreeInterface`, `ElasticQuery` for Elastic only, `HazeltreeQuery` for Hazeltree only, `ScopableQuery` otherwise (`:74-92`) |
| `Model::query()` (`ScopedQueryTrait`) | `ScopableQuery::create(static::class)` (`src/Traits/ScopedQueryTrait.php:30-33`); `Content`, `Tag`, `Bloc`, `Menu`, `LlmMenu` override it with their subclass (`src/Models/Content.php:51-54`, `Bloc.php:44`, `Menu.php:40`, `LlmMenu.php:40`) |
| relation queries | `createRelationQuery()` uses `createFor()` and flags `deferPublishable` on an Entity (`src/Traits/ScopedQueryTrait.php:35-45`) |

Subclasses: `ElasticQuery` (`BaseElasticQueryTrait`, `BuildElasticFormulaeTrait`), `HazeltreeQuery` (`BaseHazeltreeQueryTrait`), `ElasticHazeltreeQuery` (all three). Tree navigation (`roots()`, `forNode()`, `children()`, `parent()`, `siblings()`, `next()`, `previous()`, `includeDescendants()`, `includeAncestors()`, `includeSelf()`, `excludingSelf()`, `excludingDescendants()`, `natural()`, `reverse()`) and Elastic virtual columns come from `blackcube/active-record`: read its llm-doc.

## Scopes

Registered by `registerDcoreScopes()` (`:94-103`), called with named arguments.

| Scope | Effect |
|---|---|
| `active(bool $active = true)` | `andWhere(['active' => $active])`; skipped silently when the model has no `active` property (`src/Models/Scopes/ActiveScope.php:33-40`) |
| `language(string $languageId)` | `andWhere(['languageId' => …])`; skipped without a `languageId` property (`LanguageScope.php:33-41`) |
| `atDate(string $date)` | stores `referenceDate` in the query state, no filter by itself (`AtDateScope.php:34-40`) |
| `available(bool $available = true)` | preview off: `active($available)`, then when the model has `getDateStart()` and `getDateEnd()`, the window `dateStart IS NULL OR dateStart <= ref` and `dateEnd IS NULL OR dateEnd >= ref` (`$available` false: outside the window); preview on without simulated date: nothing; preview on with a simulated date: the window only, against that date (`AvailableScope.php:37-97`) |
| `publishable(bool $publishable = true)` | model without `HazeltreeInterface`: `available($publishable)`; Hazeltree model: `NOT EXISTS` (or `EXISTS` when false) a row `ancestors` of the same table with `ancestors.left <= t.left AND ancestors.right >= t.right` and (`active = 0` when preview is off) or (`dateStart > ref`) or (`dateEnd < ref`); the node is inside its own range, so one condition covers the node and its ancestors; preview on without simulated date: no condition (`PublishableScope.php:40-124`) |
| `cache(?int $ttl = null, ?Dependency $dependency = null)` | sets `cacheEnabled`, `cacheTtl`, `cacheDependency` in the state (`CacheScope.php:34-40`) |

Reference date: the simulated preview date, else the `atDate()` state, else `NOW()`. Preview: `Injector::has(PreviewManagerInterface::class)` decides; `PreviewManager` reads `PreviewContextInterface::getData()` once per instance, session key `PreviewManager::SESSION_KEY = '_preview'` holding `['active' => bool, 'simulateDate' => ?string]` (`src/Services/PreviewManager.php:25-64`, `PreviewContext.php:35-44`).

## Cache

`one()`, `all()`, `scalar()` (`src/Models/ScopableQuery.php:144-224`): when `cacheEnabled` and `Injector` holds a `Yiisoft\Cache\CacheInterface`, the raw SQL is cached through `getOrSet()` with:

| Element | Value |
|---|---|
| key | `dcore.<table>.<sha1('<method>:<rawSql>')>` (`cacheKey()`, `:121-130`) |
| TTL | `cacheTtl` state |
| dependency | `cacheDependency` state, else `QueryCache::forTable($tableName)`: one `ReusableDependency` per table, hash key `dcore.dep.<table>`, data `MAX(dateUpdate)` of the table, evaluated once per request (`src/Helpers/QueryCache.php:33-44`, `ReusableDependency.php:40-77`) |

`#[ResetQueryCacheOnWrite]` on every final Model calls `Dependency::resetReusableData()` after insert, update, upsert and delete, so a cached Entity re-read in the same request sees the write (`src/Attributes/ResetQueryCacheOnWrite.php:34-51`). `each()` is never cached; it returns `EmptyDataReader` when yii flags the query for emulated execution (empty `via` pivot), as `one()` / `all()` return `null` / `[]` (`:190-203`). Batch size of `each()`: 100.

Entities: every `Entities\*::query()` is `publishable()->cache(ttl: 3600)`; Models are never cached.

## Subqueries

`yiisoft/db` embeds only the query classes registered as expression builders: `QueryExpressions::register($connection)` registers `ActiveQuery`, `ScopableQuery`, `ElasticQuery`, `ElasticHazeltreeQuery`, `HazeltreeQuery` (`src/Helpers/QueryExpressions.php:37-48`); the dcore bootstrap does it on the application connection, test helpers call it on theirs. A query class outside this list used as `['in', 'id', $query]` throws `NotSupportedException`.

## Pagination

`Data\ActiveQueryPaginator implements Yiisoft\Data\Paginator\PaginatorInterface` (`src/Data/ActiveQueryPaginator.php`): `new ActiveQueryPaginator($query)`, `withPageSize(int)` (≥ 1), `withCurrentPage(int)` (≥ 1), `withToken(?PageToken)`; `read()` yields `limit(pageSize)->offset()->each()` when the page exists, `readOne()`; `getTotalItems()` = `$query->count()` memoized, `getTotalPages()` ≥ 1, `getCurrentPageSize()`, `isOnFirstPage()`, `isOnLastPage()`, `isPaginationRequired()` (more than one page), `nextPage()` / `previousPage()` (`?static`), `getNextToken()` / `getPreviousToken()`; sorting and filtering are not supported (`isSortable()` false, `withSort()` / `getFilter()` / `withFilter()` throw `LogicException`).

## Relevance search

`Helpers\SearchHelper::contentQuery(string $search, string $modelClass = Entities\Content::class)` and `tagQuery()` (`src/Helpers/SearchHelper.php:140-250`): relevance = occurrence count of `:search` (case-insensitive) in `name` plus the text fields (`type: string` without format or with `textarea` / `wysiwyg`) of the active schemas of kinds Page + Common, plus, through a correlated subquery on the bloc pivot, the same count in the bloc fields of kinds Bloc + Common; `addSelect(['<table>.*', 'relevance' => …])`, `HAVING relevance > 0`, `ORDER BY relevance DESC`, no pagination. The model class decides the visibility: an Entity applies `publishable()`, a Model returns every row. The schema field lists are computed once per process.
