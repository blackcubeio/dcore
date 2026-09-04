# Authors — Author management

Authors are associated with Content and Tag with ordering. Designed for JSON-LD Article (E-E-A-T / schema.org Person).

## Author model

### Identity

| Method | Type |
|---|---|
| `getId()` | `?int` |
| `getFirstname()` | `string` |
| `getLastname()` | `string` |
| `getEmail()` | `?string` |
| `isActive()` | `bool` |

### Schema.org Person

| Method | Type | schema.org property |
|---|---|---|
| `getJobTitle()` | `?string` | `jobTitle` |
| `getWorksFor()` | `?string` | `worksFor` |
| `getKnowsAbout()` | `?string` | `knowsAbout` |
| `getSameAs()` | `?string` | `sameAs` (social profile URL) |
| `getUrl()` | `?string` | `url` (personal website) |
| `getImage()` | `?string` | `image` (file path) |

### Relations

```php
$author->getContentsQuery()  // associated Content[]
$author->getTagsQuery()      // associated Tag[]
```

## Attaching an author

```php
$content->attachAuthor($author);          // appends at last position
$content->attachAuthor($author, 2);       // inserts at position 2
```

- Idempotent: if already attached, noop.
- Same API on Tag.

## Detaching an author

```php
$content->detachAuthor($author);
```

- The author is NOT deleted (authors are shared across contents).
- Order is automatically compacted.

## Checking presence

```php
$content->hasAuthor($author): bool
```

## Reordering

```php
$content->moveAuthor($author, 3);    // moves to position 3
$content->moveAuthorUp($author);     // moves up one position
$content->moveAuthorDown($author);   // moves down one position
$content->reorderAuthors();          // compacts to 1, 2, 3…
```

## Counting

```php
$content->getAuthorCount(): int
```

## Reading authors of a Content / Tag

```php
$content->getAuthorsQuery(): ActiveQueryInterface  // ordered by order ASC
$tag->getAuthorsQuery(): ActiveQueryInterface      // ordered by order ASC
```
