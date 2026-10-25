# dcore — Export, import, markdown

## Attributes

`#[Exportable(?string $name, ?string $format, ?array $fields, ?string $property, ?string $method, ?string $description)]` and `#[Importable(?string $name, ?string $format, ?string $property, ?string $method, ?string $description)]` (`src/Attributes/Exportable.php:26-45`, `Importable.php:26-43`), on a public property, a public method, or on the class to target an inherited member (`property:` / `method:`). `name` defaults to the property name or the accessor name without `get` / `set` / `is` and without a trailing `Query` (`MapperService::normalizeName()`, `src/Services/MapperService.php:409-426`). `format` is a `FormatterInterface` class (`static load()` on export, `static save()` on import). `fields` whitelists the members of a relation.

## `Services\MapperService(FileService $fileService, array $formatterMap = [])` (`src/Services/MapperService.php`)

| Method | Behaviour |
|---|---|
| `export(object $model): array` | `Content` or `Tag` only (`InvalidArgumentException` otherwise); `['elementType' => 'content'|'tag'] + exportModel()` (`:54-65`) |
| `exportModel()` (protected) | every `#[Exportable]` method, property and class-level target; a value that is an `ActiveQueryInterface` is exported as one array or a list (relation `multiple`), recursively with its `fields` whitelist; a `format` runs the formatter; an array is scanned for file paths, each replaced by `{name, data: 'data:<mime>;base64,…'}` (`:268-300`, `:431-593`) |
| `import(object $model, array $data): void` | calls every `#[Importable]` setter / property / class-level target whose key is present in `$data`, through the formatter when set; `save()` is the caller's (`:74-105`) |
| `describe(string $modelClass): array` | `fields` → `['read', 'write', 'type', 'format', 'description']`, `relations` → `['read', 'fields', 'description']`, types read from the signatures (`DateTimeInterface` → `datetime`, class → short name, `?` for nullable); the source of the MCP `info.model` (`:121-219`) |

`formatterMap` substitutes a formatter class per application (`[FileFormatter::class => App\FileFormatter::class]`). Formatters shipped: `Models\Formatters\DateFormatter` (`Y-m-d H:i:s` both ways), `Models\Formatters\FileFormatter` (`load()`: path → `{name, data}` data URI, or a list for `a, b`; `save()`: `{name, data}`, a list, or a bare data URI → file written under `<default alias>/<parents…>/<name>` with `$parameters['parents']`, random hex name when none).

## `Services\ImportService` (JSON import, `src/Services/ImportService.php`)

Constructor `(ConnectionInterface $db, FileService $fileService, MapperService $mapper, ?LoggerInterface $logger = null)`.

| Step | Method |
|---|---|
| 1 | `parseJson(string $json)`: `['valid', 'elementType', 'data', 'error']`; `elementType` must be `content` or `tag` (`:59-92`) |
| 2 | `checkExistence(array $data)`: by `id` and by `slug.path` + `slug.hostId` (`:99-133`) |
| 3 | `validateReferences(array $data, array $corrections = [])`: `missing` for `languageId`, `typeId`, `hostId`, `authors`, bloc and xeo bloc `elasticSchemaId`; `warnings` for unknown tags (`:140-292`) |
| 4 | `execute(array $data, string $mode, ?string $targetPath, array $corrections)`: transaction 1 — in `overwrite` mode the existing model (by id, else by slug path) is cleaned (blocs, SEO blocs, tags, author pivots); slug, xeo (`canonical: true` sets `canonicalSlugId` to the slug itself), sitemap saved through the mapper; model created (`setActive(false)`, placed with `saveInto($target)` or `saveAfter()` when a node with the JSON `path` already exists) or updated; blocs and xeo blocs created with their Elastic values minus file values; tags and authors attached. Transaction 2 — base64 files decoded and written to `<contents|tags>/<id>[/blocs/<blocId>]/<name>` (random name from the mime type when none) and the xeo image to `<contents|tags>/<id>/xeo/<xeoId>/`; a failure here returns `success: false` with the model saved without its files (`:303-447`) |

Helpers: `isSlugPathTaken(string $path, int $hostId)`, `getTreeTargets(string $elementType)` (`natural()` order, `path` / `name` / `level`), `getReferenceOptions()` (active languages, types, hosts, authors, schemas as `id => label`).

## `Services\ElasticMdService` (markdown, `src/Services/ElasticMdService.php`)

Templates live in `ElasticSchema::mdMapping`. Grammar: `:::start:<schema name lowercased>` / `:::end` delimiters, `{field}` required, `{?field}` optional (the line is dropped when empty), `{field:rich}` rich text (HTML ↔ markdown), `{#field}` heading prefix (N × `#` from a numeric field), `{a} OR {b}` first non-empty, `![{alt}]({image})`, `[{label}]({url})` or `[{label}]({route}{url})`, code fences ```` ```{language} ```` … ```` ``` ```` around `{code}`.

| Method | Behaviour |
|---|---|
| `export(Content|Tag $model, string $prompt = ''): string` | `RuntimeException` without Type; YAML front matter (`entity`, `lang`, `type`, `article`, `prompt`), the LLM instructions, the article dictionary (the model schema template, when its `mdMapping` is set), the bloc dictionary (the Type's allowed schemas with a template, kind ≠ Xeo, in `order`), then `# Current content` with the article values and each bloc rendered (`:53-85`) |
| `renderMarkdown(Content|Tag $model, string $scheme, string $hostname): string` | public markdown: article then blocs, delimiters stripped, `![…](@blfs/…)` images turned into absolute `CacheFile` URLs (dropped when the file has no URL), internal links resolved through `Element::getLink()` or made absolute (`:99-133`) |
| `import(Content|Tag $model, string $markdown): array` | `['success', 'errors', 'warnings', 'blocsCreated']`; front matter checked against the model (`entity`, `type`, `article` → errors; `lang` → warning); blocks parsed after the last `# Current content` heading (or from the first `:::start:`), code fences respected; the block named like the model schema fills the article properties (once); other blocks match a schema of the Type by lowercase name among kinds Bloc / Common, unknown ones are ignored with a warning; then `persistImport()`: every existing bloc detached, article values set, one active bloc created and attached per block, single transaction, failure logged and returned as an error (`:553-638`, `:1188-1221`) |

Block parsing (`markdownBlockToData()`, `:951-1076`) walks the template slots in order: headings (level captured into the `{#field}` source), images (`alt | src` kept in the alt field when the src is not a storage path), links (a `/`-leading URL goes to a `route` field when the template has one), code fences, free text up to the next structural slot (converted to HTML when it carries markdown and the field is `wysiwyg`).

## `Helpers\ContentHelper` (`src/Helpers/ContentHelper.php`)

`cleanTitle(string)` (tags → spaces, entities decoded, markdown markers and control characters dropped, whitespace collapsed), `cleanHeadingLine(string)`, `containsKnownHtml(string)` (`p`, `strong`, `em`, `u`, `s`, `a`, `ul`, `ol`, `li`, `br`, `mark`, `code`, `del`, `ins`), `htmlToMarkdown(string)` (that subset, Quill list blocks normalised: `**`, `*`, `++` for `u` / `ins`, `~~` for `s` / `del`, `==` for `mark`, `` ` ``, `- ` items, `[text](href)`, two-space line breaks), `markdownToHtml(string)` (the inverse: paragraphs, `- ` lists, inline markers, links with `target="_blank"`).
