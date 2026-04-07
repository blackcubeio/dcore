# Dynamic Properties — Flexible JSON Schema

Content, Tag, Bloc and GlobalXeo carry dynamic properties defined by an `ElasticSchema`. Values are stored in a JSON column and exposed as native model properties.

## Applicable models

| Model | Interface |
|---|---|
| `Content` | `ElasticInterface` |
| `Tag` | `ElasticInterface` |
| `Bloc` | `ElasticInterface` |
| `GlobalXeo` | `ElasticInterface` |

## ElasticSchema

An `ElasticSchema` defines the structure of dynamic properties via a JSON schema (JSON Schema).

```php
$schema = new ElasticSchema();
$schema->setName('Article');
$schema->setSchema('{"type":"object","properties":{"subtitle":{"type":"string"},"image":{"type":"string"}},"required":[]}');
$schema->setKind(ElasticSchemaKind::Page);
$schema->setActive(true);
$schema->save();
```

### Schema families (`ElasticSchemaKind`)

| Kind | Usage |
|---|---|
| `Common` | Usable everywhere (blocs, contents, tags) except xeo |
| `Page` | Contents and tags only |
| `Bloc` | Blocs only |
| `Xeo` | SEO part only (GlobalXeo) |

### dcore-specific properties

| Method | Type | Description |
|---|---|---|
| `getKind()` | `ElasticSchemaKind` | Schema family |
| `setKind(ElasticSchemaKind\|string $kind)` | | |
| `getMdMapping()` | `?string` | Mapping for Markdown export/import |
| `isBuiltin()` | `bool` | System schema (non-deletable) |
| `isHidden()` | `bool` | Hidden in the interface |
| `getOrder()` | `int` | Display order |
| `isActive()` | `bool` | Active/inactive |

### Relations

```php
$schema->getTypesQuery()    // Types that allow this schema
$schema->getSchemasSchemasQuery()     // regular → xeo mappings
$schema->getXeoSchemasSchemasQuery()  // xeo ← regular mappings
```

## Associating a schema with a model

```php
$content->setElasticSchemaId($schema->getId());
$content->save();
```

The schema is associated with a Type. A Type declares allowed schemas:

```php
$type->getElasticSchemasQuery()->all(); // schemas allowed for this type
```

## Reading / writing dynamic properties

Properties defined in the JSON schema are directly accessible:

```php
// Read
$content->subtitle;    // direct access
$content->image;

// Write
$content->subtitle = 'My subtitle';
$content->save();
```

## `ElasticInterface` API

| Method | Return | Description |
|---|---|---|
| `getSchema()` | `?Schema` | Parsed JSON schema |
| `getElasticAttributes()` | `array` | Dynamic attribute names |
| `getElasticValues()` | `array` | Dynamic attribute values |
| `getElasticLabels()` | `array` | Labels for forms |
| `getElasticHints()` | `array` | Hints for forms |
| `getElasticPlaceholders()` | `array` | Placeholders for forms |
| `getPropertyLabel(string $property)` | `string` | Attribute label |
| `getPropertyHint(string $property)` | `string` | Attribute hint |
| `getPropertyPlaceholder(string $property)` | `string` | Attribute placeholder |
| `getElasticSchemaId()` | `mixed` | Associated schema ID |
| `setElasticSchemaId(mixed $id)` | `void` | Change the schema |

## Protection

```php
$content->protectElastic(true);  // prevents modifications to dynamic properties
$content->protectElastic(false);
```

## SchemaSchema mapping

`SchemaSchema` associates a "regular" schema (Page/Bloc) with a "xeo" schema (Xeo) with an optional mapping:

```php
$mapping = new SchemaSchema();
$mapping->setRegularElasticSchemaId($pageSchema->getId());
$mapping->setXeoElasticSchemaId($xeoSchema->getId());
$mapping->setMapping('{"title":"seoTitle"}'); // optional
$mapping->save();
```

## Interface

To type a parameter that accepts any model with dynamic properties:

```php
function process(ElasticInterface $model): void
```
