<?php

declare(strict_types=1);

/**
 * FileService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\FileProvider\Interfaces\FileProviderInterface;
use Swaggest\JsonSchema\Schema;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Service for file operations and processing file fields in blocs/entities.
 * Wraps FileProvider for all file access. Moves files from @bltmp/ to @blfs/.
 */
class FileService
{
    private const FILE_FORMATS = ['file', 'files'];

    /** @var array<int, string[]> schemaId => file field names (15-20 schemas max, request-scoped) */
    private static array $fileFieldsCache = [];

    public function __construct(
        private FileProviderInterface $fileProvider,
    ) {}

    public function fileExists(string $path): bool
    {
        return $this->fileProvider->fileExists($path);
    }

    public function read(string $path): string
    {
        return $this->fileProvider->read($path);
    }

    public function write(string $path, string $content): void
    {
        $this->fileProvider->write($path, $content);
    }

    public function delete(string $path): void
    {
        $this->fileProvider->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->fileProvider->directoryExists($path)) {
            $this->fileProvider->deleteDirectory($path);
        }
    }

    public function mimeType(string $path): string
    {
        return $this->fileProvider->mimeType($path);
    }

    public function isTempPath(string $path): bool
    {
        return $this->fileProvider->isTempPath($path);
    }

    public function canHandle(string $path): bool
    {
        return $this->fileProvider->canHandle($path);
    }

    public function getDefaultAlias(): string
    {
        return $this->fileProvider->getDefaultAlias();
    }

    public function getTempAlias(): string
    {
        return $this->fileProvider->getTempAlias();
    }

    /**
     * File-carrying field names of an ElasticSchema, by id. Lazy-loaded and cached
     * statically (few schemas exist), so the query + parse happen once per schema.
     *
     * @return string[]
     */
    public function fileFields(int $schemaId): array
    {
        if (array_key_exists($schemaId, self::$fileFieldsCache) === false) {
            $fileFields = [];
            $elasticSchema = ElasticSchema::query()->andWhere(['id' => $schemaId])->one();
            if ($elasticSchema !== null) {
                $jsonSchema = $elasticSchema->getSchema();
                if ($jsonSchema !== null) {
                    $decoded = json_decode($jsonSchema);
                    if ($decoded instanceof \stdClass) {
                        $fileFields = $this->fileFieldsFromSchema(Schema::import($decoded));
                    }
                }
            }
            self::$fileFieldsCache[$schemaId] = $fileFields;
        }
        return self::$fileFieldsCache[$schemaId];
    }

    /**
     * Build the filesystem path segment for an ActiveRecord entity.
     * Uses tableName() for the directory name and primaryKeyValues() for the path structure.
     *
     * Simple PK:    contents/42
     * Composite PK: globalxeos/hosts/1/kinds/Organization
     *
     * @return string Path segment without prefix (e.g. 'contents/42')
     */
    public static function buildEntityPath(ActiveRecord $entity): string
    {
        $tableName = $entity->tableName();
        $entityDir = strtolower(trim($tableName, '{}%'));

        $pkColumns = $entity->primaryKey();
        $pkValues = $entity->primaryKeyValues();

        if (count($pkColumns) === 1) {
            return $entityDir.'/'.reset($pkValues);
        }

        $segments = [$entityDir];
        foreach ($pkColumns as $column) {
            $fieldName = preg_replace('/Id$/', '', $column);
            $segments[] = strtolower($fieldName).'s';
            $segments[] = (string) $pkValues[$column];
        }

        return implode('/', $segments);
    }

    /**
     * Process bloc files: move @bltmp/ -> @blfs/{parentPath}/blocs/{blocId}/
     */
    public function processBlocFiles(Bloc $bloc, ActiveRecord $parentEntity): void
    {
        $fileProperties = $this->getFileProperties($bloc);
        if ($fileProperties === []) {
            return;
        }

        $basePath = self::buildEntityPath($parentEntity).'/'.self::buildEntityPath($bloc);
        $modified = false;

        foreach ($fileProperties as $propertyName) {
            $currentValue = $bloc->$propertyName;
            if ($currentValue !== null && $currentValue !== '') {
                $newValue = $this->processFileValues($currentValue, $basePath);
                if ($newValue !== $currentValue) {
                    $bloc->$propertyName = $newValue;
                    $modified = true;
                }
            }
        }

        if ($modified === true) {
            $bloc->save();
        }
    }

    /**
     * Process file fields on a regular model (without ElasticTrait).
     * Uses getter/setter methods to read/write attribute values.
     * Does NOT call save() - the caller is responsible for that.
     *
     * @param object $model The model with file properties (must have get{Attr}/set{Attr} methods)
     * @param array<string> $attributes List of attribute names to process (e.g. ['image'])
     * @param string $basePath Base path without @blfs/ prefix (e.g. 'tags/3/xeo/5')
     */
    public function processRegularFiles(object $model, array $attributes, string $basePath): void
    {
        foreach ($attributes as $attribute) {
            $getter = 'get'.ucfirst($attribute);
            $setter = 'set'.ucfirst($attribute);

            $hasAccessors = method_exists($model, $getter) === true && method_exists($model, $setter) === true;
            if ($hasAccessors === true) {
                $currentValue = $model->$getter();
                if ($currentValue !== null && $currentValue !== '') {
                    $newValue = $this->processFileValues($currentValue, $basePath);
                    if ($newValue !== $currentValue) {
                        $model->$setter($newValue);
                    }
                }
            }
        }
    }

    /**
     * Process entity files (direct elastic properties): move @bltmp/ -> @blfs/{entityPath}/
     * Works with any ActiveRecord using ElasticTrait (Tag, Content, GlobalXeo, etc.)
     */
    public function processEntityFiles(ActiveRecord $entity): void
    {
        $fileProperties = $this->getEntityFileProperties($entity);
        if ($fileProperties === []) {
            return;
        }

        $basePath = self::buildEntityPath($entity);
        $modified = false;

        foreach ($fileProperties as $propertyName) {
            $currentValue = $entity->$propertyName;
            if ($currentValue !== null && $currentValue !== '') {
                $newValue = $this->processFileValues($currentValue, $basePath);
                if ($newValue !== $currentValue) {
                    $entity->$propertyName = $newValue;
                    $modified = true;
                }
            }
        }

        if ($modified === true) {
            $entity->save();
        }
    }

    /**
     * Duplicate bloc files: copy @blfs/ source files to @blfs/{basePath}/{filename}
     * and update bloc properties to point to the new copies.
     * Saves the bloc if any file was duplicated.
     */
    public function duplicateBlocFiles(Bloc $bloc, string $basePath): void
    {
        $fileProperties = $this->getFileProperties($bloc);
        if ($fileProperties === []) {
            return;
        }

        $modified = false;

        foreach ($fileProperties as $propertyName) {
            $currentValue = $bloc->$propertyName;
            if ($currentValue !== null && $currentValue !== '') {
                $files = preg_split('/\s*,\s*/', $currentValue, -1, PREG_SPLIT_NO_EMPTY);
                $newFiles = [];

                foreach ($files as $file) {
                    $isStored = $this->fileProvider->canHandle($file) === true
                        && $this->fileProvider->isTempPath($file) === false
                        && $this->fileProvider->fileExists($file) === true;
                    if ($isStored === true) {
                        $filename = basename($file);
                        $targetPath = $this->fileProvider->getDefaultAlias().'/'.$basePath.'/'.$filename;
                        $this->fileProvider->copy($file, $targetPath);
                        $newFiles[] = $targetPath;
                        $modified = true;
                    } else {
                        $newFiles[] = $file;
                    }
                }

                $bloc->$propertyName = implode(', ', $newFiles);
            }
        }

        if ($modified === true) {
            $bloc->save();
        }
    }

    /**
     * Delete all bloc files from @blfs/
     */
    public function deleteBlocFiles(Bloc $bloc, ActiveRecord $parentEntity): void
    {
        $directory = $this->fileProvider->getDefaultAlias().'/'.self::buildEntityPath($parentEntity).'/'.self::buildEntityPath($bloc);
        $this->deleteDirectory($directory);
    }

    /**
     * Extract all file values from a bloc (for comparison before/after save).
     *
     * @return array<string, string|null> property name => file value(s)
     */
    public function extractFileValues(Bloc $bloc): array
    {
        $fileProperties = $this->getFileProperties($bloc);
        $values = [];

        foreach ($fileProperties as $propertyName) {
            $values[$propertyName] = $bloc->$propertyName;
        }

        return $values;
    }

    /**
     * Delete removed files by comparing initial and final values.
     * Only deletes @blfs/ files that were in initial but not in final.
     *
     * @param array<string, string|null> $initialValues
     * @param array<string, string|null> $finalValues
     */
    public function deleteRemovedFiles(array $initialValues, array $finalValues): void
    {
        $initialFiles = $this->flattenFileValues($initialValues);
        $finalFiles = $this->flattenFileValues($finalValues);

        $toDelete = array_diff($initialFiles, $finalFiles);

        foreach ($toDelete as $file) {
            if ($this->fileProvider->canHandle($file) === true && $this->fileProvider->isTempPath($file) === false && $this->fileProvider->fileExists($file) === true) {
                $this->fileProvider->delete($file);
            }
        }
    }

    /**
     * @param array<string, string|null> $values
     * @return string[]
     */
    private function flattenFileValues(array $values): array
    {
        $files = [];

        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                $parts = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parts as $part) {
                    $files[] = $part;
                }
            }
        }

        return $files;
    }

    /**
     * @return string[]
     */
    private function fileFieldsFromSchema(?Schema $schema): array
    {
        $fileFields = [];
        if ($schema !== null) {
            $properties = $schema->getProperties();
            if ($properties !== null) {
                foreach ($properties as $name => $property) {
                    $format = $property->format ?? null;
                    if ($format !== null && in_array($format, self::FILE_FORMATS, true) === true) {
                        $fileFields[] = $name;
                    }
                }
            }
        }
        return $fileFields;
    }

    /**
     * @return string[]
     */
    private function getFileProperties(Bloc $bloc): array
    {
        return $this->fileFieldsFromSchema($bloc->getSchema());
    }

    /**
     * Process file value(s): move @bltmp/ files to @blfs/{basePath}/
     * Handles both single files and comma-separated file lists.
     */
    private function processFileValues(string $value, string $basePath): string
    {
        $files = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $finalFiles = [];

        foreach ($files as $file) {
            if ($this->fileProvider->isTempPath($file) === true) {
                $filename = basename($file);
                $targetPath = $this->fileProvider->getDefaultAlias().'/'.$basePath.'/'.$filename;
                if ($this->fileProvider->fileExists($file) === true) {
                    $this->fileProvider->move($file, $targetPath);
                    $finalFiles[] = $targetPath;
                } else {
                    $finalFiles[] = $file;
                }
            } else {
                $finalFiles[] = $file;
            }
        }

        return implode(', ', $finalFiles);
    }

    /**
     * @return string[]
     */
    private function getEntityFileProperties(object $entity): array
    {
        $fileFields = [];
        if (method_exists($entity, 'getSchema') === true) {
            $fileFields = $this->fileFieldsFromSchema($entity->getSchema());
        }
        return $fileFields;
    }

}
