# Tree Structure — Parent-child hierarchy

Content, Tag and Menu can be organized as trees.

## Applicable models

| Model | Interface | Typical depth |
|---|---|---|
| `Content` | `HazeltreeInterface` | unlimited |
| `Tag` | `HazeltreeInterface` | 2-3 levels |
| `Menu` | `HazeltreeInterface` | 4 levels |

## Creating a node

### Root node

```php
$content = new Content();
$content->setName('Home');
$content->save(); // no parent = automatic root
```

### Child of an existing node

```php
$child = new Content();
$child->setName('Subpage');
$child->saveInto($parent); // inserts as last child of $parent
```

### Before / after a node

```php
$node->saveBefore($sibling); // inserts just before $sibling
$node->saveAfter($sibling);  // inserts just after $sibling
```

All three methods return `bool` and accept a `HazeltreeInterface` or a string (path).

## Checking position

```php
$node->isRoot(): bool           // true if root node
$node->getLevel(): int          // level in the tree (1 = root)
$node->getSubtreeDepth(): int   // subtree depth (0 if leaf)
```

## Moving a node

Before moving, check feasibility:

```php
$node->canMove($targetPath): bool  // true if the move is possible
$node->wouldExceedMaxLevel($target, 'into', $maxLevel): bool
```

Depth control methods:

```php
$node->getMaxLevelIfMoveInto($target): int
$node->getMaxLevelIfMoveBefore($target): int
$node->getMaxLevelIfMoveAfter($target): int
```

Then move with `saveInto()`, `saveBefore()`, `saveAfter()` — same API as creation.

## Deleting a node

`$node->delete()` deletes the node and its descendants. For Content and Tag, deletion also cascades: orphaned blocs, slug, xeo, sitemap (see source code).

## Tree queries

`ScopedQuery` integrates tree filters. All methods are chainable.

### Basic selection

```php
Content::query()->roots()        // root nodes only
Content::query()->forNode($node) // positions the query on a node
```

### Relative navigation (requires `forNode()`)

```php
->children()              // direct children
->parent()                // direct parent
->siblings()              // siblings (same level)
->next()                  // next sibling
->previous()              // previous sibling
```

### Inclusion / exclusion

```php
->includeDescendants()    // include all descendants
->includeAncestors()      // include all ancestors
->includeSelf()           // include the node itself
->excludingSelf()         // exclude the current node
->excludingDescendants()  // exclude descendants
```

### Ordering

```php
->natural()  // natural tree order
->reverse()  // reverse order
```

### Combining with visibility scopes

```php
Content::query()
    ->roots()
    ->active()
    ->publishable()
    ->all();
```

## Protection

```php
$node->protectHazeltree(true);  // prevents tree modifications
$node->protectHazeltree(false); // re-enables them
```

## Interface

To type a parameter that accepts Content, Tag or Menu interchangeably:

```php
function process(HazeltreeInterface $node): void
```
