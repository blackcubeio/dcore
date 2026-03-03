# Tags — Associating tags

Content can carry tags. Unlike blocs, tags are shared across contents and unordered.

## Applicable model

Only `Content` carries tags.

## Attaching a tag

```php
$content->attachTag($tag);
```

- Creates the `ContentTag` pivot.
- If already attached, noop (idempotent).

## Detaching a tag

```php
$content->detachTag($tag);
```

- Deletes the pivot only. The tag is NOT deleted (shared).
- If not attached, noop.

## Checking presence

```php
$content->hasTag($tag): bool
```

## Bulk synchronization

```php
$content->syncTags([$tag1, $tag2, $tag3]);
```

- Attaches missing tags.
- Detaches tags no longer in the list.
- Accepts a `Tag[]`.

## Counting

```php
$content->getTagCount(): int
```

## Reading tags

```php
$content->getTags(): array             // Tag[]
$content->getTagsQuery(): ActiveQueryInterface  // customizable query
```

## Inverse relations

On the Tag side:

```php
$tag->getContents(): array             // Content[] associated with this tag
$tag->getContentsQuery(): ActiveQueryInterface
```

## Difference with blocs

| | Blocs | Tags |
|---|---|---|
| Shared | no (1 bloc = 1 parent) | yes (1 tag = N contents) |
| Ordered | yes (`order` column) | no |
| Deletion on detach | bloc deleted | tag kept |
| Carrier models | Content, Tag | Content only |
