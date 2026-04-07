# SEO and Metadata — Xeo, GlobalXeo, sitemap, robots

## Xeo — Per-URL SEO

Each `Slug` can have an associated `Xeo`. The Xeo carries all the page's SEO metadata.

### Properties

| Method | Type | Description |
|---|---|---|
| `getSlugId()` | `int` | Associated Slug |
| `getCanonicalSlugId()` | `?int` | Canonical Slug (if different) |
| `getTitle()` | `?string` | `<title>` tag |
| `getDescription()` | `?string` | Meta description |
| `getImage()` | `?string` | Image (file path) |
| `getKeywords()` | `?string` | Meta keywords |

### Robots

| Method | Type | Description |
|---|---|---|
| `isNoindex()` | `bool` | `<meta name="robots" content="noindex">` |
| `isNofollow()` | `bool` | `<meta name="robots" content="nofollow">` |

### Open Graph

| Method | Type | Description |
|---|---|---|
| `isOg()` | `bool` | Enable OG tags |
| `getOgType()` | `?string` | `og:type` (e.g. `"article"`) |

### Twitter Card

| Method | Type | Description |
|---|---|---|
| `isTwitter()` | `bool` | Enable Twitter Cards |
| `getTwitterCard()` | `?string` | Card type (e.g. `"summary_large_image"`) |

### JSON-LD (schema.org)

| Method | Type | Description |
|---|---|---|
| `getJsonldType()` | `string` | schema.org type (default: `"WebPage"`) |
| `isSpeakable()` | `bool` | Mark content as speakable |
| `isAccessibleForFree()` | `bool` | Free content (schema.org) |

### Relations

```php
$xeo->getSlugQuery()       // associated Slug
$xeo->getXeoBlocsQuery()   // XeoBloc pivots
$xeo->getBlocsQuery()      // attached SEO blocs (e.g. FAQ, breadcrumb JSON-LD)
```

### Activity

```php
$xeo->isActive(): bool  // default: false (opt-in)
```

## GlobalXeo — Global SEO per host

A `GlobalXeo` is a global SEO configuration for a given host, identified by a `kind`.

Composite PK: `(hostId, kind)`.

### Properties

| Method | Type | Description |
|---|---|---|
| `getHostId()` | `int` | FK to Host |
| `getName()` | `string` | Descriptive name |
| `getKind()` | `string` | Config type (e.g. `"Robots"`, `"DefaultXeo"`) |
| `isActive()` | `bool` | |

### Dynamic properties

`GlobalXeo` implements `ElasticInterface`. Its JSON content depends on the associated schema. For example, a GlobalXeo of kind `"Robots"` can store robots.txt directives in its dynamic properties.

```php
$globalXeo->getElasticSchemaId()
$globalXeo->getElasticValues()
$globalXeo->getHostQuery()            // associated Host
$globalXeo->getElasticSchemaQuery()   // associated ElasticSchema
```

## Sitemap

A `Sitemap` configures a Slug's presence in the XML sitemap.

### Properties

| Method | Type | Description |
|---|---|---|
| `getSlugId()` | `int` | Associated Slug |
| `getFrequency()` | `string` | Frequency (default: `"daily"`) |
| `getPriority()` | `float` | Priority 0.0–1.0 (default: 0.5) |
| `isActive()` | `bool` | |

### Relation

```php
$sitemap->getSlugQuery()  // associated Slug
```

The HTTP handlers serving `sitemap.xml`, `robots.txt`, `llms.txt`, `llms-full.txt`, `{slug}.md` and Slug redirects live in the `blackcube/ssr` package. See [ssr/docs/routing.md](https://github.com/blackcubeio/ssr/blob/devel/docs/routing.md) for their behaviour.

## Xeo helper — Template rendering

The `Xeo` helper builds a ready-to-use SEO data object from a Slug ID.

```php
$xeo = Xeo::fromSlugId($slugId): ?Xeo
```

### Properties

| Property | Type | Description |
|---|---|---|
| `$language` | `?string` | Language code (from Content) |
| `$canonicalLink` | `?Link` | Canonical Link (PSR-13) |
| `$title` | `?string` | Page title |
| `$description` | `?string` | Meta description |
| `$image` | `?string` | Image path |
| `$noIndex` | `?bool` | noindex directive |
| `$noFollow` | `?bool` | nofollow directive |
| `$keywords` | `?string` | Meta keywords |
| `$alternates` | `array` | Hreflang alternates `[['language' => string, 'link' => Link], ...]` |
| `$jsonLds` | `?array` | JSON-LD arrays (from `JsonLdBuilder`) |
| `$twitter` | `?XeoTwitter` | Twitter Card data |
| `$og` | `?XeoOg` | Open Graph data |

### Language and hreflang

When the Slug points to a `Content` with a `languageId`, `Xeo` populates:
- `$language` — the content's language code (e.g. `"fr"`, `"en"`)
- `$alternates` — the current page plus all translations from the same `TranslationGroup`, each with its language code and `Link`

These are populated **independently of the Xeo model** — a page can have language/hreflang data without having SEO metadata configured. `fromSlugId()` returns a `Xeo` instance whenever the Slug exists, even if no active Xeo model is attached.

### XeoOg / XeoTwitter

Simple data containers:

```php
$xeo->og->type;       // ?string, default 'website'
$xeo->twitter->type;   // ?string, default 'summary'
```

## JsonLdBuilder — Structured data

Generates JSON-LD arrays (schema.org) for a given Slug.

```php
$builder->build(int $slugId, string $host): array
```

Returns an array of JSON-LD objects. Supported types:

| Type | Source |
|---|---|
| Organization | GlobalXeo (kind = Organization) |
| WebSite | GlobalXeo (kind = WebSite) |
| Page type (WebPage, Article…) | Xeo `jsonldType` + Hero bloc |
| FAQ | FAQ blocs attached to Xeo |
| ImageObject | Image blocs attached to Xeo |
| VideoObject | Video blocs attached to Xeo |

Host resolution: exact match on hostname, then fallback to `hostId=1`.

## JsonLdKind enum

| Value | Description |
|---|---|
| `Organization` | Organization structured data |
| `Robots` | Robots.txt configuration |
| `WebSite` | WebSite structured data |
