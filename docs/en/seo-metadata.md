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

## HTTP Handlers

### `SitemapHandler`

Generates `/sitemap.xml`. Traverses Slugs with an active Sitemap, filters by active Content/Tag within their dates, and produces the XML.

### `RobotsHandler`

Generates `/robots.txt`. Reads the `GlobalXeo` of kind `"Robots"` for the current host.

### `RedirectHandler`

Redirects to `Slug::getTargetUrl()` with `Slug::getHttpCode()`.
