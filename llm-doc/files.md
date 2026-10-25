# dcore — Files

`Services\FileService(FileProviderInterface $fileProvider)` (`src/Services/FileService.php`) wraps `blackcube/fileprovider` (its llm-doc describes the aliases): `fileExists()`, `read()`, `write()`, `delete()`, `deleteDirectory()` (only when the directory exists), `mimeType()`, `isTempPath()`, `canHandle()`, `getDefaultAlias()`, `getTempAlias()` (`:41-91`).

## Storage layout

`FileService::buildEntityPath(ActiveRecord $entity): string` (`:127-147`): `<table>/<pk>` for a single key (`contents/42`, `blocs/7`, `xeos/5`), `<table>/<column>s/<value>/…` for a composite key (`globalxeos/hosts/1/kinds/Organization`). Table names are lowercased without `{{%` `}}`.

| File of | Path under the default alias (`@blfs/`) |
|---|---|
| an Elastic field of a Content / Tag / GlobalXeo | `<entity path>/<filename>` (`processEntityFiles()`) |
| a bloc of a Content / Tag | `<parent path>/blocs/<blocId>/<filename>` (`processBlocFiles()`), e.g. `contents/35/blocs/429/ai-blue-cubes.jpg` |
| a SEO bloc rebuilt by `XeoRefreshService` | `<model path>/xeos/<xeoId>/blocs/<blocId>/<filename>` |
| the Xeo image of a JSON import | `<contents|tags>/<id>/xeo/<xeoId>/<filename>` (`ImportService.php:404`) |

## File fields

A file-carrying field is a schema property with `format` `file` or `files` (`FILE_FORMATS`, `:29-32`). `fileFields(int $schemaId): string[]` reads the schema once per process (static cache, `:99-116`); `getFileProperties(Bloc)` and `getEntityFileProperties(entity)` read the model's own `getSchema()`. A value holds one path or several separated by `, `.

## Operations

| Method | Behaviour |
|---|---|
| `processBlocFiles(Bloc $bloc, ActiveRecord $parentEntity): void` | every `@bltmp/…` value of a file field is moved to `<parent>/blocs/<blocId>/<basename>`; values already stored or missing on disk are kept; `save()` when something changed (`:152-176`) |
| `processEntityFiles(ActiveRecord $entity): void` | same for the entity's own file fields, under `<entity path>/` (`:210-234`) |
| `processRegularFiles(object $model, array $attributes, string $basePath): void` | same through `get<Attr>()` / `set<Attr>()` on a plain model (a Xeo image), no `save()` (`:187-204`) |
| `duplicateBlocFiles(Bloc $bloc, string $basePath): void` | copies every stored (non-temp, existing) file to `<basePath>/<basename>` and rewrites the values; `save()` when something was copied (`:241-278`) |
| `deleteBlocFiles(Bloc $bloc, ActiveRecord $parentEntity): void` | deletes the directory `<parent>/blocs/<blocId>` (`:283-287`) |
| `extractFileValues(Bloc $bloc): array` | field → value snapshot (`:294-304`) |
| `deleteRemovedFiles(array $initialValues, array $finalValues): void` | deletes the stored files present before and absent after (`:313-325`) |

The rule of an upload: the form posts a `@bltmp/<name>` path (`blackcube/resumable`), the model is saved, then `processBlocFiles()` / `processEntityFiles()` moves the file under the entity. A value that is not a temp path is never moved.
