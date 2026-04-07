# Blocs — Attaching and ordering blocs

Content and Tag can carry ordered blocs. A bloc is a content brick (text, image, video…) whose structure is defined by an `ElasticSchema`.

## Applicable models

| Model | Can carry blocs |
|---|---|
| `Content` | yes |
| `Tag` | yes |

## The Bloc model

A `Bloc` carries dynamic properties via `ElasticInterface` (see [dynamic-properties.md](dynamic-properties.md)).

```php
$bloc = new Bloc();
$bloc->setElasticSchemaId($schema->getId());
$bloc->setActive(true);
$bloc->save();

// Read dynamic properties
$bloc->getElasticValues(): array
$bloc->getData(): array  // alias for export
```

### Bloc relations

```php
$bloc->getContents(): array   // Contents this bloc is attached to
$bloc->getTags(): array       // Tags this bloc is attached to
$bloc->getElasticSchemaQuery() // associated ElasticSchema
$bloc->getXeosQuery()         // Xeos this bloc is attached to
```

## Attaching a bloc

```php
$content->attachBloc($bloc);           // appends at last position
$content->attachBloc($bloc, 3);        // inserts at position 3 (1-based)
```

- If the bloc is already attached, the call is ignored (idempotent).
- Insertion automatically shifts subsequent blocs.
- Position `0` or out of bounds = appends at end.
- Transactional.

## Detaching a bloc

```php
$content->detachBloc($bloc);
```

- Deletes the pivot AND the bloc itself (blocs are not shared).
- Remaining blocs are automatically reordered (the gap is filled).
- If the bloc is not attached, the call is ignored.
- Transactional.

## Moving a bloc

```php
$content->moveBloc($bloc, 2);  // moves to position 2
$content->moveBlocUp($bloc);   // moves up one position
$content->moveBlocDown($bloc); // moves down one position
```

- `moveBloc()`: position normalized between 1 and total count. If already at the right position, noop.
- `moveBlocUp()` / `moveBlocDown()`: swaps with neighbor. Noop if already at top/bottom.
- Transactional.

## Reordering

```php
$content->reorderBlocs();
```

Renumbers sequentially (1, 2, 3…). Useful after import or manual deletion.

## Counting

```php
$content->getBlocCount(): int
```

## Reading ordered blocs

```php
$content->getBlocs(): array            // Bloc[] ordered by position
$content->getBlocsQuery(): ActiveQueryInterface  // customizable query
```

The `getBlocsQuery()` query automatically applies `ORDER BY order ASC` via the pivot table.

## Cascade on deletion

When a Content or Tag is deleted (`delete()`), its orphaned blocs (not attached to another Content/Tag) are deleted in cascade.
