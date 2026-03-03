# Queries and Visibility — Query scopes and preview

All models use `ScopedQuery` via `Model::query()`. This query automatically detects model capabilities and exposes chainable filtering scopes.

## Basic scopes

### `active(bool $active = true)`

Filters by `active` field. Available if the model has an `active` property.

```php
Content::query()->active()->all();       // active
Content::query()->active(false)->all();  // inactive
```

### `language(string $languageId)`

Filters by language. Available if the model has a `languageId` property.

```php
Content::query()->language('fr')->all();
```

### `atDate(string $date)`

Sets a reference date for `available()` and `publishable()`. Does NOT filter on its own.

```php
Content::query()->atDate('2025-06-15')->available()->all();
```

## Composed scopes

### `available(bool $available = true)`

Filters available records: active AND within their publication window (`dateStart` ≤ now ≤ `dateEnd`).

```php
Content::query()->available()->all();
```

Behavior:
- If the model has no dates (Tag, Menu…) → equivalent to `active()`.
- `dateStart` and `dateEnd` accept `null` (= no bound).

### `publishable(bool $publishable = true)`

Like `available()`, but also checks that **all ancestors** in the tree are active and within their dates. Only available on tree-structured models (Content, Tag, Menu).

```php
Content::query()->publishable()->all();  // self + all ancestors OK
```

On a non-tree model, `publishable()` behaves like `available()`.

## Combining

Scopes combine freely:

```php
Content::query()
    ->language('fr')
    ->publishable()
    ->roots()            // tree filter
    ->orderBy(['name' => SORT_ASC])
    ->all();
```

## Preview mode

The `PreviewManager` allows viewing unpublished content.

### Configuration

```php
ScopedQuery::setPreviewManager(PreviewManagerInterface $previewManager);
```

Called at bootstrap. The `PreviewManager` checks an HMAC-signed state in session.

### Scope behavior in preview mode

| Situation | `available()` / `publishable()` |
|---|---|
| Preview OFF | Normal filter (active + dates) |
| Preview ON, no simulated date | No filter (everything is visible) |
| Preview ON, with simulated date | Filters by dates only (no `active` filter) |

```php
$previewManager->isActive(): bool
$previewManager->getSimulateDate(): ?string  // e.g. '2025-12-25 00:00:00'
```

## Tree queries

`ScopedQuery` integrates tree filters. See [tree-structure.md](tree-structure.md) for details.

```php
Content::query()->roots()->active()->all();
Content::query()->forNode($parent)->children()->publishable()->all();
```

## Elastic queries

`ScopedQuery` extends `ElasticQuery`, which allows filtering on dynamic JSON properties. Virtual JSON columns are accessible in `andWhere()` conditions.
