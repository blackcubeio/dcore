# Links and URLs — Slugs, Link PSR-13, CMS routing

## Slug

A `Slug` represents a URL: a `Host` + a `path`. It can also be a redirect.

### Properties

| Method | Type | Description |
|---|---|---|
| `getId()` | `?int` | |
| `getHostId()` | `int` | FK to Host (default: 1 = wildcard) |
| `getPath()` | `string` | Relative path (e.g. `"blog/my-article"`) |
| `getTargetUrl()` | `?string` | Target URL if redirect |
| `getHttpCode()` | `?int` | HTTP code if redirect (301, 302…) |
| `isActive()` | `bool` | |

### Relations

```php
$slug->getHostQuery()     // associated Host
$slug->getContentQuery()  // Content linked to this URL
$slug->getTagQuery()      // Tag linked to this URL
$slug->getXeoQuery()      // Xeo (SEO) for this URL
$slug->getSitemapQuery()  // Sitemap configuration for this URL
```

### Polymorphism

```php
$slug->getElement(): Content|Tag|null  // returns the linked Content or Tag
```

### Generating a Link

```php
$link = $slug->getLink(): Link
```

- If the slug is a redirect (`targetUrl` + `httpCode` not null) → `Link` pointing to `targetUrl`.
- Otherwise → `Link` with protocol-relative URI template: `//{host}/path`.
- If `hostId > 1`, the `{host}` template is automatically resolved with the Host name.

### Accessing the Link from a Content or Tag

The path goes through the Slug:

```php
$link = $content->getSlug()?->getLink();  // ?Link (PSR-13)
$link = $tag->getSlug()?->getLink();      // ?Link (PSR-13)
```

## Link (PSR-13)

`Link` implements `Psr\Link\EvolvableLinkInterface`. Immutable (each method returns a clone).

### Creation

```php
$link = new Link('canonical', 'https://example.com/page');
$link = new Link('', '//{host}/blog/article');  // URI template
```

### Reading

```php
$link->getHref(): string        // URL or template
$link->isTemplated(): bool      // true if contains {xxx}
$link->getRels(): array          // e.g. ['canonical']
$link->getAttributes(): array    // e.g. ['hrefLang' => 'fr']
```

### Mutation (immutable)

```php
$link->withHref('https://...'): static
$link->withTemplate('host', 'example.com'): static  // replaces {host} with value
$link->withRel('canonical'): static
$link->withoutRel('canonical'): static
$link->withAttribute('hrefLang', 'fr'): static
$link->withoutAttribute('hrefLang'): static
```

## Host — Multi-domain

A `Host` represents a domain.

| Method | Type | Description |
|---|---|---|
| `getId()` | `?int` | `1` = wildcard |
| `getName()` | `string` | Domain name (e.g. `"example.com"`) |
| `isActive()` | `bool` | |
| `getSiteName()` | `?string` | Site name (schema.org) |
| `getSiteAlternateName()` | `?string` | Alternate name |
| `getSiteDescription()` | `?string` | Site description |

Resolution: exact match on hostname, then fallback to `id=1`.

## Type and SSR handler

A `Type` associates an SSR handler with a content/tag.

| Method | Type | Description |
|---|---|---|
| `getName()` | `string` | Type name |
| `getHandler()` | `?string` | SSR handler identifier |
| `isContentAllowed()` | `bool` | Allowed for Content |
| `isTagAllowed()` | `bool` | Allowed for Tag |
| `getElasticSchemasQuery()` | `ActiveQueryInterface` | Allowed bloc schemas |

## Element helper — Route resolution

`Element` is a lightweight unified reference to a CMS element (Content or Tag).

### Factory methods

```php
Element::createFromRoute('dcore-c-42'): ?Element   // from route string
Element::createFromModel($content): Element          // from Content or Tag
Element::createFromSlug($slug): ?Element             // from Slug
```

Route format: `dcore-c-{id}` (Content) or `dcore-t-{id}` (Tag).

### Reading

```php
$element->getType(): string            // 'content' or 'tag'
$element->getId(): int
$element->getModel(): Content|Tag|null // lazy-loaded
$element->getModelQuery(): ?ScopableQuery
$element->toRoute(): string            // e.g. 'dcore-c-42'
```

## SsrRouteProviderInterface

Interface for exposing available SSR routes:

```php
interface SsrRouteProviderInterface
{
    public function getAvailableRoutes(): array;
}
```

## HandlerDescriptor — CMS routing resolution

Entry point for resolving an HTTP path to a handler.

### Resolution by path

```php
HandlerDescriptor::findByPath(string $host, string $path): ?HandlerDescriptor
```

Resolution: host match (exact, fallback `id=1`) → active Slug lookup → CMS element (Content/Tag) → Type handler.

Special routes (`robots.txt`, `sitemap.xml`, `llms.txt`, `llms-full.txt`, `{slug}.md`) and redirects are intercepted upstream by the SSR middleware and never reach `HandlerDescriptor`.

### Resolution by error

```php
HandlerDescriptor::findByError(string $error, string $route): ?HandlerDescriptor
```

Looks for a Content/Tag whose Type carries handler `$route`, with `name == $error` or the first one found.

### Reading the result

```php
$descriptor->getClass(): string      // handler FQCN
$descriptor->getMode(): string       // 'construct' or 'method'
$descriptor->getMethod(): ?string    // method name if mode is 'method'
$descriptor->getExpects(): array     // types expected by the handler
$descriptor->getData(): array        // lazy-loaded data for injection
```

### Configuration

```php
HandlerDescriptor::setRouteResolver(Closure $resolver): void
```

The resolver must return `?array{class, mode, method, expects}` for a given handler identifier. Configured at application bootstrap.
