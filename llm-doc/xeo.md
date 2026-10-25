# dcore — SEO: Xeo, JSON-LD, sitemap, robots, markdown, llms

## Models

`Models\Xeo` (`src/Models/BaseXeo.php:67-294`), one per `Slug`: `slugId`, `canonicalSlugId` (`isCanonical()`), `title`, `description`, `image` (a `@blfs/` path), `keywords`, `noindex`, `nofollow`, `og` + `ogType`, `twitter` + `twitterCard`, `jsonldType` (default `WebPage`), `speakable`, `accessibleForFree`, `active` (opt-in: a Xeo is ignored until active); relations `getSlugQuery()`, `getXeoBlocsQuery()`, `getBlocsQuery()`; SEO blocs managed by `OrderedManagementTrait` (`attachBloc()`, `detachBloc()`, `moveBloc()`, …, `src/Models/Xeo.php:50-56`).

`Models\GlobalXeo` (`src/Models/BaseGlobalXeo.php:51-114`): PK `hostId` + `kind`, `name`, `active`, Elastic values; kinds in use: `Organization`, `WebSite`, `Robots` (`Enums\JsonLdKind`) and `Sitemap` (read by `SitemapService` as the string `'Sitemap'`). `Models\Sitemap`: `slugId`, `frequency` (default `daily`), `priority` (0.5), `active`.

## `Helpers\Xeo::from(int|Content|Tag): ?Xeo` (`src/Helpers/Xeo.php:44-118`)

Public properties: `language`, `alternates` (`[['language' => …, 'link' => Link], …]`), `canonicalLink`, `title`, `description`, `image`, `noIndex`, `noFollow`, `keywords`, `jsonLds` (always `null` here, filled by ssr), `twitter` (`XeoTwitter`, `type` default `summary`), `og` (`XeoOg`, `type` default `website`).

| Input | Result |
|---|---|
| slug id, slug missing | `null` |
| slug id, Xeo model missing or inactive | an instance with `language` and `alternates` only |
| slug id, active Xeo | the fields above, `canonicalLink` from `canonicalSlugId`, `og` / `twitter` when enabled |
| any slug of a Content with a language | `language`, `alternates` = the page itself plus every translation of the group that has a slug (`:92-112`) |
| Content or Tag | `language` only (error pages without slug) |

## `Services\JsonLdBuilder::build(int $slugId, string $host): array` (`src/Services/JsonLdBuilder.php`)

Empty unless the slug has an active Xeo. Base URL `https://<host name>` (the `Host` row when `hostId > 1`, the request host otherwise). One `{"@context": "https://schema.org", "@graph": […]}` with, in order:

| Node | Source |
|---|---|
| `Organization` (or `organizationType`) | GlobalXeo kind `Organization` of the host, fallback host 1: `logo` (through `CacheFile`), `email`, `telephone`, `address` fields, `vatID`, `iso6523Code`, `sameAs` (one URL per line), `contactPoint`, `foundingDate`, `numberOfEmployees` (`:169-242`) |
| `WebSite` | GlobalXeo kind `WebSite`: `Host` `siteName` / `siteAlternateName` / `siteDescription`, `inLanguage`, `SearchAction` when `hasSearchAction` + `searchTarget` (`:244-279`) |
| the page type (`jsonldType`) | `name` / `headline` / `description` / `image` from the first active SEO bloc whose schema is named `Hero` (`title`, `overline`, `description`, `image`), else from the Xeo fields; `image` scaled to 1200; `inLanguage`; `author` Person nodes from the element authors (`jobTitle`, `worksFor`, `knowsAbout`, `url`, `image` 96px, `sameAs`); `speakable` (`article`, `h1`, `h2`); `keywords` (one per line); `isAccessibleForFree` (`:284-349`) |
| `FAQPage` | active SEO blocs of schema `FAQ` (`name`, `acceptedAnswer`) |
| `ImageObject`, `VideoObject` | active SEO blocs of schemas `Image` (`name`, `caption`, `contentUrl`) and `Video` (`name`, `description`, `contentUrl`) |

Titles go through `ContentHelper::cleanTitle()`. GlobalXeos are loaded once per process (static cache, `:105-121`). Bound to `Interfaces\JsonLdBuilderInterface`.

## `Services\XeoRefreshService::refresh(Content|Tag $model, Xeo $xeo): int` (`src/Services/XeoRefreshService.php:45-134`)

Rebuilds the SEO blocs from the content, in one transaction: deletes the existing SEO blocs and their files; then for the page schema of the model and for the schema of each content bloc (in pivot order), one new bloc per `SchemaSchema` row whose `regularElasticSchemaId` matches, with the xeo schema of the row, inactive (`setActive(false)`), prefilled by the row `mapping` (`{"mapping": {"<source field>": "<target field>"}}`, empty sources skipped), its files duplicated under `<model path>/xeos/<xeoId>/blocs/<blocId>`, appended last. Returns the number created. Used by the dboard refresh actions and the mcp `xeo.refresh` tool.

## Services\Xeo

| Service | Behaviour |
|---|---|
| `RobotsService::generate(Host $host): ?string` | the `rawData` Elastic value of the active GlobalXeo kind `Robots` of the host; `null` without it (`src/Services/Xeo/RobotsService.php:26-42`) |
| `SitemapService::generate(Host $host, string $hostname, string $scheme): ?string` | every published Content then Tag (Entities) with an active slug and an active Sitemap row → `<url>` with `loc` = `<scheme>://<hostname>/<path>`, `lastmod` = `dateUpdate` (`Y-m-d`), `changefreq`, `priority`; the `<url>` entries of the GlobalXeo kind `Sitemap` `rawData` XML are added when their `loc` is new; `null` when no URL (`SitemapService.php:29-150`) |
| `MdService::generate(Host $host, string $hostname, string $path, string $scheme): ?string` | the active slug of the host at `path` (without `.md`) → `ElasticMdService::renderMarkdown()` of its element; `null` otherwise (`MdService.php:37-54`) |
| `LlmsService::generate(string $scheme, string $hostname, ?string $languageId = null)` / `generateFull()` | `null` without the parameter `PROJECT` / `NAME` or without a level-1 `LlmMenu`; root chosen by the language of its linked element, else the first root; output `# <name>`, `> <PROJECT/SUMMARY>`, then per node: level 2 → `## <name or linked title>`, every linked node with an active slug → `- [<name>](<scheme>://<hostname>/<path>.md): <description or linked title>`; full mode appends the rendered markdown of each linked element with its headings shifted two levels down, headings beyond `######` dropped (`LlmsService.php:49-291`) |

The HTTP handlers that serve these outputs (`robots.txt`, `sitemap.xml`, `llms.txt`, `llms-full.txt`, `<path>.md`) live in `blackcube/ssr`.
