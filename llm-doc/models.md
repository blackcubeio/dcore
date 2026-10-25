# dcore — Models

## Three layers

| Layer | Content | Example |
|---|---|---|
| `Models\BaseX` (abstract, extends `Yiisoft\ActiveRecord\ActiveRecord`) | `MagicRelationsTrait`, `MagicPropertiesTrait`, `EventsTrait`, `ModelKindTrait` (+ `LinkTrait` on slugged models), typed protected columns with `getX()` / `setX()` / `isX()`, relation queries `getXxxQuery(): ActiveQueryInterface`, `#[Exportable]` / `#[Importable]` on members | `src/Models/BaseAuthor.php:33-38`, `BaseContent.php:60-315` |
| `Models\X` | `#[DefaultDateTimeOnInsert(null, 'dateCreate')]`, `#[DefaultDateTimeOnInsert(null, 'dateUpdate')]`, `#[SetDateTimeOnUpdate(null, 'dateUpdate')]`, `#[ResetQueryCacheOnWrite]`; the composite trait of `blackcube/active-record` (`HazeltreeElasticTrait` for Content and Tag, `ElasticTrait` for Bloc and GlobalXeo, `HazeltreeTrait` for Menu and LlmMenu), `ScopedQueryTrait`, `OrderedManagementTrait` / `TagManagementTrait`, `query()`, business methods | `src/Models/Content.php:40-54` |
| `Entities\X extends Models\X` | `protected ModelKind $modelKind = ModelKind::Entity`, `query()` = `parent::query()->publishable()->cache(ttl: 3600)` | `src/Entities/Content.php:26-35` |

Date attributes are not inherited: each final Model repeats them (`src/Models/Content.php:33-43`). No `MagicComposeActiveRecordTrait`: the composite traits dispatch the magic methods themselves.

## Capabilities

| Model | Table | Tree | Elastic | Blocs | Tags | Authors | Slug |
|---|---|---|---|---|---|---|---|
| `Content` | `contents` | yes | yes | yes | yes | yes | yes |
| `Tag` | `tags` | yes | yes | yes | — | yes | yes |
| `Bloc` | `blocs` | — | yes | — | — | — | — |
| `Menu` | `menus` | yes (4 levels) | — | — | — | — | — |
| `LlmMenu` | `llmMenus` | yes (3 levels) | — | — | — | — | — |
| `GlobalXeo` | `globalXeos` | — | yes | — | — | — | — |
| `Xeo` | `xeos` | — | — | yes (SEO blocs) | — | — | — |

Support models: `Slug` (`slugs`), `Host` (`hosts`, `id = 1` generic), `Type` (`types`), `Language` (`languages`, string PK), `ElasticSchema` (`elasticSchemas`), `Author` (`authors`), `Sitemap` (`sitemaps`), `Parameter` (`parameters`, PK `domain` + `name`), `ContentTranslationGroup`, `TagTranslationGroup`. Pivots: `ContentBloc`, `TagBloc`, `XeoBloc`, `ContentAuthor`, `TagAuthor` (ordered, `order` column), `ContentTag`, `TypeElasticSchema`, `SchemaSchema` (regular schema → xeo schema, with a JSON `mapping`).

## Content

Columns (`src/Models/BaseContent.php:66-175`): `id`, `name`, `slugId`, `languageId`, `translationGroupId`, `typeId`, `active`, `dateStart`, `dateEnd`, `dateCreate`, `dateUpdate`, plus the Hazeltree columns (`path`, `left`, `right`, `level`) and the Elastic storage (`elasticSchemaId`, JSON column). Relations: `getSlugQuery()`, `getTypeQuery()`, `getLanguageQuery()`, `getContentBlocsQuery()`, `getBlocsQuery()` (ordered by the pivot `order`), `getContentTagsQuery()`, `getTagsQuery()`, `getContentTranslationGroupQuery()`, `getTranslationsQuery()` (the other members of the group, empty query without group), `getContentAuthorsQuery()`, `getAuthorsQuery()` (ordered) (`:185-315`).

`Models\Content` (`src/Models/Content.php`): `getTreePath()` (exported as `path`), `getElasticSchemaId()` / `setElasticSchemaId()`, `getData()` (the Elastic values, exported as `data`), the bloc and author methods below, `attachTag()` / `detachTag()` / `hasTag()` / `syncTags()` / `getTagCount()`, `linkTranslation()` / `unlinkTranslation()`, `delete()`. `Models\Tag` has the same surface without tags, on `TagBloc`, `TagAuthor`, `TagTranslationGroup` (`src/Models/Tag.php`).

## Ordered pivots (`OrderedManagementTrait`)

`getOrderedConfig(string $type)` gives `pivotClass`, `fkColumn`, `entityIdColumn`, `owned` (`src/Models/Content.php:98-115`): `bloc` → `ContentBloc`, owned; `author` → `ContentAuthor`, not owned. `Xeo` uses the trait for its SEO blocs through `XeoBloc` (`src/Models/Xeo.php:50-56`).

| Method | Behaviour (`src/Traits/OrderedManagementTrait.php`) |
|---|---|
| `attachBloc(Bloc $bloc, int $position = 0)`, `attachAuthor(Author $author, int $position = 0)` | idempotent; position 1-based, `0` or beyond the end → appended; the following rows are shifted; transactional (`:50-93`) |
| `detachBloc()`, `detachAuthor()` | pivot deleted, the entity itself deleted when owned (blocs), the gap closed; nothing when not attached (`:102-137`) |
| `moveBloc($bloc, int $position)`, `moveAuthor()` | clamped to `[1, count]`, rows between shifted (`:167-213`) |
| `moveBlocUp()` / `moveBlocDown()`, `moveAuthorUp()` / `Down()` | swap with the neighbour, noop at the ends (`:221-319`) |
| `reorderBlocs()`, `reorderAuthors()` | renumber 1, 2, 3… (`:327-356`) |
| `getBlocCount()`, `getAuthorCount()`, `hasAuthor()` | counts and presence |

## Tags (`TagManagementTrait`, Content only)

`attachTag(Tag)` idempotent, `detachTag(Tag)` deletes the pivot only, `hasTag(Tag)`, `syncTags(Tag[])` attaches the missing and detaches the others (`syncTags([])` clears), `getTagCount()` (`src/Traits/TagManagementTrait.php:51-160`). Tags are shared and unordered.

## Translations

`linkTranslation(int|string|self $target)` (`src/Models/Content.php:151-214`): `InvalidArgumentException('Target Content not found')` for an unknown id, `LogicException` when both have the same language, when both already belong to a group, or when the language already exists in the group; otherwise joins the existing group or creates a `ContentTranslationGroup`; transactional. `unlinkTranslation(int|string|Language|self|null $target = null)` (`:227-300`): `null` = self, an id, a language code, a `Language` or a `Content`; `InvalidArgumentException` when self has no group, the target is missing or in another group; when one member remains the group is dissolved (last member unlinked, group deleted). Same API on `Tag` with `TagTranslationGroup` (`src/Models/Tag.php:134`, `:214`).

## Cascade delete

`Content::delete(): int` (`src/Models/Content.php:311-381`): collects the descendant ids by `left` / `right`, the bloc ids of the whole subtree, then in one transaction: `parent::delete()` (the subtree), the slug, every bloc left without any content or tag, the translation group when empty. `Tag::delete()` mirrors it (`src/Models/Tag.php:298`).

## Schemas and types

`ElasticSchema` (`src/Models/BaseElasticSchema.php:52-159`): `getKind(): ElasticSchemaKind` (`Common` everywhere but xeo, `Page` contents and tags, `Bloc` blocs, `Xeo` SEO blocs and GlobalXeo), `getMdMapping()` (markdown template, see `mapper.md`), `isBuiltin()`, `isHidden()`, `getOrder()`, `isActive()`; relations `getTypesQuery()`, `getTypeElasticSchemasQuery()`, `getSchemasSchemasQuery()` (regular → xeo mappings), `getXeoSchemasSchemasQuery()`. `Type` (`src/Models/BaseType.php:55-135`): `getName()`, `getHandler()` (the SSR route name), `isContentAllowed()`, `isTagAllowed()`, `getElasticSchemasQuery()` (allowed schemas). `SchemaSchema`: `regularElasticSchemaId`, `xeoElasticSchemaId`, `mapping` JSON `{"mapping": {"<source field>": "<xeo field>"}}` read by `XeoRefreshService`.

## Hosts, languages, parameters, menus

- `Host` (`src/Models/BaseHost.php:53-122`): `name`, `active`, `siteName`, `siteAlternateName`, `siteDescription`, `getGlobalXeosQuery()`; `HostHelper::findByHostname(string)` returns the active host with that name, else the generic `id = 1`, in one query (`src/Helpers/HostHelper.php:28-45`).
- `Language`: string PK (`fr`, `en`), `name`, `isMain()`, `isActive()`.
- `Parameter` (`src/Models/BaseParameter.php:50-80`): `domain`, `name`, `value`; `ParameterHelper::get(string $domain, string $name): ?string` loads the whole table once per process (`src/Helpers/ParameterHelper.php:26-43`).
- `Menu`: tree, `hostId`, `languageId`, `route` (an element route or null), `queryString`. `LlmMenu` (`src/Models/BaseLlmMenu.php:55-139`): tree, `name`, `description`, `contentId` or `tagId`, `getContentQuery()`, `getTagQuery()`; rendered by `LlmsService` (`xeo.md`).
