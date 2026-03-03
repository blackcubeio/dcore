<?php

declare(strict_types=1);

/**
 * FileSaveService.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Models\Bloc;
use Blackcube\FileProvider\FileProvider;

/**
 * Service for processing file fields in blocs.
 * Moves files from @bltmp/ to @blfs/{entity}/{entityId}/blocs/{blocId}/
 */
final class FileSaveService
{
    private const TMP_PREFIX = '@bltmp/';
    private const FS_PREFIX = '@blfs/';
    private const FILE_FORMATS = ['file', 'files'];

    public function __construct(
        private FileProvider $fileProvider,
    ) {}

    /**
     * Process bloc files: move @bltmp/ -> @blfs/{entity}/{entityId}/blocs/{blocId}/
     */
    public function processBlocFiles(Bloc $bloc, string $entityType, int $entityId): void
    {
        $fileProperties = $this->getFileProperties($bloc);
        if ($fileProperties === []) {
            return;
        }

        $modified = false;

        foreach ($fileProperties as $propertyName) {
            $currentValue = $bloc->$propertyName;
            if ($currentValue === null || $currentValue === '') {
                continue;
            }

            $newValue = $this->processFileValue(
                $currentValue,
                $entityType,
                $entityId,
                $bloc->getId()
            );

            if ($newValue !== $currentValue) {
                $bloc->$propertyName = $newValue;
                $modified = true;
            }
        }

        if ($modified) {
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
     * @param string $basePath Base path without @blfs/ prefix (e.g. 'tags/3/seo/5')
     */
    public function processRegularFiles(object $model, array $attributes, string $basePath): void
    {
        foreach ($attributes as $attribute) {
            $getter = 'get' . ucfirst($attribute);
            $setter = 'set' . ucfirst($attribute);

            if (!method_exists($model, $getter) || !method_exists($model, $setter)) {
                continue;
            }

            $currentValue = $model->$getter();
            if ($currentValue === null || $currentValue === '') {
                continue;
            }

            $newValue = $this->processRegularFileValue($currentValue, $basePath);

            if ($newValue !== $currentValue) {
                $model->$setter($newValue);
            }
        }
    }

    /**
     * Process entity files (direct elastic properties): move @bltmp/ -> @blfs/{entityType}/{entityId}/
     * Works with any object using ElasticTrait (Tag, Content, etc.)
     *
     * @param object $entity Entity with getSchema() method (ElasticTrait)
     * @param string $entityType Entity type (e.g., 'tags', 'contents')
     * @param int $entityId Entity ID
     */
    public function processEntityFiles(object $entity, string $entityType, int $entityId): void
    {
        $fileProperties = $this->getEntityFileProperties($entity);
        if ($fileProperties === []) {
            return;
        }

        $modified = false;

        foreach ($fileProperties as $propertyName) {
            $currentValue = $entity->$propertyName;
            if ($currentValue === null || $currentValue === '') {
                continue;
            }

            $newValue = $this->processEntityFileValue(
                $currentValue,
                $entityType,
                $entityId
            );

            if ($newValue !== $currentValue) {
                $entity->$propertyName = $newValue;
                $modified = true;
            }
        }

        if ($modified) {
            $entity->save();
        }
    }

    /**
     * Delete all bloc files from @blfs/
     */
    public function deleteBlocFiles(Bloc $bloc, string $entityType, int $entityId): void
    {
        $directory = self::FS_PREFIX . $entityType . '/' . $entityId . '/blocs/' . $bloc->getId();

        if ($this->fileProvider->directoryExists($directory)) {
            $this->fileProvider->deleteDirectory($directory);
        }
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

        // Files to delete: in initial but not in final, and must be @blfs/ files
        $toDelete = array_diff($initialFiles, $finalFiles);

        foreach ($toDelete as $file) {
            if (str_starts_with($file, self::FS_PREFIX) && $this->fileProvider->fileExists($file)) {
                $this->fileProvider->delete($file);
            }
        }
    }

    /**
     * Flatten file values array into a list of individual file paths.
     *
     * @param array<string, string|null> $values
     * @return string[]
     */
    private function flattenFileValues(array $values): array
    {
        $files = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $files[] = $part;
            }
        }

        return $files;
    }

    /**
     * Get property names that have format: file or files.
     *
     * @return string[]
     */
    private function getFileProperties(Bloc $bloc): array
    {
        $schema = $bloc->getSchema();
        if ($schema === null) {
            return [];
        }

        $properties = $schema->getProperties();
        if ($properties === null) {
            return [];
        }

        $fileProperties = [];
        foreach ($properties as $name => $property) {
            $format = $property->format ?? null;
            if ($format !== null && in_array($format, self::FILE_FORMATS, true)) {
                $fileProperties[] = $name;
            }
        }

        return $fileProperties;
    }

    /**
     * Process a file value (single or comma-separated multiple).
     */
    private function processFileValue(
        string $value,
        string $entityType,
        int $entityId,
        int $blocId
    ): string {
        $files = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $finalFiles = [];

        foreach ($files as $file) {
            $finalFiles[] = $this->processSingleFile($file, $entityType, $entityId, $blocId);
        }

        return implode(', ', $finalFiles);
    }

    /**
     * Process a single file path (for blocs).
     */
    private function processSingleFile(
        string $file,
        string $entityType,
        int $entityId,
        int $blocId
    ): string {
        // Already in @blfs/ -> keep as is
        if (!str_starts_with($file, self::TMP_PREFIX)) {
            return $file;
        }

        // Extract filename
        $filename = basename($file);

        // Build target path: @blfs/tags/42/blocs/123/filename.ext
        $targetPath = self::FS_PREFIX . $entityType . '/' . $entityId . '/blocs/' . $blocId . '/' . $filename;

        // Move file
        if ($this->fileProvider->fileExists($file)) {
            $this->fileProvider->move($file, $targetPath);
            return $targetPath;
        }

        // File doesn't exist in tmp, return original
        return $file;
    }

    /**
     * Get property names that have format: file or files (for entities with ElasticTrait).
     *
     * @param object $entity Entity with getSchema() method
     * @return string[]
     */
    private function getEntityFileProperties(object $entity): array
    {
        if (!method_exists($entity, 'getSchema')) {
            return [];
        }

        $schema = $entity->getSchema();
        if ($schema === null) {
            return [];
        }

        $properties = $schema->getProperties();
        if ($properties === null) {
            return [];
        }

        $fileProperties = [];
        foreach ($properties as $name => $property) {
            $format = $property->format ?? null;
            if ($format !== null && in_array($format, self::FILE_FORMATS, true)) {
                $fileProperties[] = $name;
            }
        }

        return $fileProperties;
    }

    /**
     * Process a file value for entity (single or comma-separated multiple).
     */
    private function processEntityFileValue(
        string $value,
        string $entityType,
        int $entityId
    ): string {
        $files = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $finalFiles = [];

        foreach ($files as $file) {
            $finalFiles[] = $this->processEntitySingleFile($file, $entityType, $entityId);
        }

        return implode(', ', $finalFiles);
    }

    /**
     * Process a single file path (for entity direct properties, no bloc subfolder).
     */
    private function processEntitySingleFile(
        string $file,
        string $entityType,
        int $entityId
    ): string {
        // Already in @blfs/ -> keep as is
        if (!str_starts_with($file, self::TMP_PREFIX)) {
            return $file;
        }

        // Extract filename
        $filename = basename($file);

        // Build target path: @blfs/tags/42/filename.ext (no blocs subfolder)
        $targetPath = self::FS_PREFIX . $entityType . '/' . $entityId . '/' . $filename;

        // Move file
        if ($this->fileProvider->fileExists($file)) {
            $this->fileProvider->move($file, $targetPath);
            return $targetPath;
        }

        // File doesn't exist in tmp, return original
        return $file;
    }

    /**
     * Process a file value for regular model (single or comma-separated multiple).
     */
    private function processRegularFileValue(string $value, string $basePath): string
    {
        $files = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $finalFiles = [];

        foreach ($files as $file) {
            $finalFiles[] = $this->processRegularSingleFile($file, $basePath);
        }

        return implode(', ', $finalFiles);
    }

    /**
     * Process a single file path (for regular models with basePath).
     */
    private function processRegularSingleFile(string $file, string $basePath): string
    {
        // Already in @blfs/ -> keep as is
        if (!str_starts_with($file, self::TMP_PREFIX)) {
            return $file;
        }

        // Extract filename
        $filename = basename($file);

        // Build target path: @blfs/{basePath}/filename.ext
        $targetPath = self::FS_PREFIX . $basePath . '/' . $filename;

        // Move file
        if ($this->fileProvider->fileExists($file)) {
            $this->fileProvider->move($file, $targetPath);
            return $targetPath;
        }

        // File doesn't exist in tmp, return original
        return $file;
    }
}
