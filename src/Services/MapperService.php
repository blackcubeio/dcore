<?php

declare(strict_types=1);

/**
 * MapperService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Tag;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Yiisoft\ActiveRecord\ActiveQueryInterface;

/**
 * Single home for the attribute-driven mapping system.
 * Maps models to/from array format through the Exportable / Importable attributes,
 * using reflection to discover annotated methods, properties and class-level targets.
 *
 * - export(): model → array (recurses relations, files inlined as base64).
 * - import(): array → model (feeds the Importable setters; save() is left to the caller).
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class MapperService
{
    /**
     * @param array<class-string, class-string> $formatterMap Per-app substitution
     *        of base formatters, e.g. [FileFormatter::class => App\FileFormatter::class].
     */
    public function __construct(
        private FileService $fileService,
        private array $formatterMap = [],
    ) {}

    /**
     * Exports a model to array format using Exportable attributes.
     *
     * @param object $model The model to export
     * @return array<string, mixed> The exported data
     * @throws \InvalidArgumentException If model class is not supported
     */
    public function export(object $model): array
    {
        if ($model instanceof Content) {
            $data = ['elementType' => 'content'];
        } elseif ($model instanceof Tag) {
            $data = ['elementType' => 'tag'];
        } else {
            throw new \InvalidArgumentException('Unsupported model class: '.$model::class);
        }

        return array_merge($data, $this->exportModel($model));
    }

    /**
     * Imports array data into a model using Importable attributes.
     * No database write happens here: only the setters are called, save() is the caller's job.
     *
     * @param object $model The model to hydrate
     * @param array<string, mixed> $data The source data
     */
    public function import(object $model, array $data): void
    {
        $reflection = new ReflectionClass($model);

        foreach ($this->getImportableMethods($reflection) as $method => $attribute) {
            $name = $attribute->name ?? $this->normalizeName($method);
            if (array_key_exists($name, $data) === true) {
                $model->$method($this->processImportValue($data[$name], $attribute));
            }
        }

        foreach ($this->getImportableProperties($reflection) as $property => $attribute) {
            $name = $attribute->name ?? $property;
            if (array_key_exists($name, $data) === true) {
                $model->$property = $this->processImportValue($data[$name], $attribute);
            }
        }

        foreach ($this->getClassImportables($reflection) as $attribute) {
            if ($attribute->method !== null) {
                $name = $attribute->name ?? $this->normalizeName($attribute->method);
                if (array_key_exists($name, $data) === true) {
                    $model->{$attribute->method}($this->processImportValue($data[$name], $attribute));
                }
            } else {
                $name = $attribute->name ?? $attribute->property;
                if (array_key_exists($name, $data) === true) {
                    $model->{$attribute->property} = $this->processImportValue($data[$name], $attribute);
                }
            }
        }
    }

    /**
     * Describes the shape of a model from its Exportable/Importable attributes,
     * without reading any value — same reflection as export()/import(). Lets a
     * caller (e.g. the MCP info.model) derive the field map instead of hardcoding it.
     * The type is read from the member signature (getter return type, setter first
     * parameter, or property type) and the description from the attribute, so
     * neither can drift from the code.
     *
     * @param class-string $modelClass
     * @return array{
     *     fields: array<string, array{read: bool, write: bool, type: string|null, format: string|null, description: string|null}>,
     *     relations: array<string, array{read: bool, fields: array<string>|null, description: string|null}>
     * }
     */
    public function describe(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $fields = [];
        $relations = [];

        foreach ($this->getExportableMethods($reflection) as $method => $attribute) {
            $name = $attribute->name ?? $this->normalizeName($method);
            if (str_ends_with($method, 'Query') === true) {
                $relations[$name]['read'] = true;
                $relations[$name]['fields'] = $attribute->fields;
                $relations[$name]['description'] = $attribute->description;
            } else {
                $fields[$name]['read'] = true;
                $fields[$name]['format'] = $attribute->format;
                $fields[$name]['description'] = $attribute->description;
                $fields[$name]['type'] = $this->typeFor($reflection, $method, null);
            }
        }

        foreach ($this->getExportableProperties($reflection) as $property => $attribute) {
            $name = $attribute->name ?? $property;
            $fields[$name]['read'] = true;
            $fields[$name]['format'] = $attribute->format;
            $fields[$name]['description'] = $attribute->description;
            $fields[$name]['type'] = $this->typeFor($reflection, null, $property);
        }

        foreach ($this->getClassExportables($reflection) as $attribute) {
            $accessor = $attribute->method ?? $attribute->property ?? '';
            $name = $attribute->name ?? $this->normalizeName($accessor);
            if ($attribute->method !== null && str_ends_with($attribute->method, 'Query') === true) {
                $relations[$name]['read'] = true;
                $relations[$name]['fields'] = $attribute->fields;
                $relations[$name]['description'] = $attribute->description;
            } else {
                $fields[$name]['read'] = true;
                $fields[$name]['format'] = $attribute->format;
                $fields[$name]['description'] = $attribute->description;
                $fields[$name]['type'] = $this->typeFor($reflection, $attribute->method, $attribute->property);
            }
        }

        foreach ($this->getImportableMethods($reflection) as $method => $attribute) {
            $name = $attribute->name ?? $this->normalizeName($method);
            $fields[$name]['write'] = true;
            if (($fields[$name]['description'] ?? null) === null) {
                $fields[$name]['description'] = $attribute->description;
            }
            if (($fields[$name]['type'] ?? null) === null) {
                $fields[$name]['type'] = $this->typeFor($reflection, $method, null);
            }
        }

        foreach ($this->getImportableProperties($reflection) as $property => $attribute) {
            $name = $attribute->name ?? $property;
            $fields[$name]['write'] = true;
            if (($fields[$name]['description'] ?? null) === null) {
                $fields[$name]['description'] = $attribute->description;
            }
            if (($fields[$name]['type'] ?? null) === null) {
                $fields[$name]['type'] = $this->typeFor($reflection, null, $property);
            }
        }

        foreach ($this->getClassImportables($reflection) as $attribute) {
            $accessor = $attribute->method ?? $attribute->property ?? '';
            $name = $attribute->name ?? $this->normalizeName($accessor);
            $fields[$name]['write'] = true;
            if (($fields[$name]['description'] ?? null) === null) {
                $fields[$name]['description'] = $attribute->description;
            }
            if (($fields[$name]['type'] ?? null) === null) {
                $fields[$name]['type'] = $this->typeFor($reflection, $attribute->method, $attribute->property);
            }
        }

        foreach ($fields as $name => $field) {
            $fields[$name] = [
                'read' => $field['read'] ?? false,
                'write' => $field['write'] ?? false,
                'type' => $field['type'] ?? null,
                'format' => $field['format'] ?? null,
                'description' => $field['description'] ?? null,
            ];
        }
        foreach ($relations as $name => $relation) {
            $relations[$name] = [
                'read' => $relation['read'] ?? false,
                'fields' => $relation['fields'] ?? null,
                'description' => $relation['description'] ?? null,
            ];
        }

        return ['fields' => $fields, 'relations' => $relations];
    }

    /**
     * Human readable type of a member: the getter return type, the first
     * parameter type for a setter, or the property type. Date types collapse
     * to "datetime", class types to their short name, nullability is rendered
     * with a leading "?".
     */
    private function typeFor(ReflectionClass $reflection, ?string $method, ?string $property): ?string
    {
        $typeLabel = null;
        $reflectionType = null;
        if ($method !== null && $reflection->hasMethod($method) === true) {
            $reflectionMethod = $reflection->getMethod($method);
            if ($reflectionMethod->getNumberOfParameters() > 0) {
                $reflectionType = $reflectionMethod->getParameters()[0]->getType();
            } else {
                $reflectionType = $reflectionMethod->getReturnType();
            }
        } elseif ($property !== null && $reflection->hasProperty($property) === true) {
            $reflectionType = $reflection->getProperty($property)->getType();
        }
        if ($reflectionType instanceof ReflectionNamedType) {
            $typeName = $reflectionType->getName();
            if (is_a($typeName, DateTimeInterface::class, true) === true) {
                $typeName = 'datetime';
            } elseif ($reflectionType->isBuiltin() === false) {
                $backslashPosition = strrpos($typeName, '\\');
                if ($backslashPosition !== false) {
                    $typeName = substr($typeName, $backslashPosition + 1);
                }
            }
            $nullablePrefix = ($reflectionType->allowsNull() === true && $typeName !== 'mixed') ? '?' : '';
            $typeLabel = $nullablePrefix.$typeName;
        } elseif ($reflectionType !== null) {
            $typeLabel = (string) $reflectionType;
        }
        return $typeLabel;
    }

    /**
     * Exports a model using reflection to discover Exportable attributes.
     *
     * @param object $model The model to export
     * @param array<string>|null $fields When set, only these member names are exported.
     *                                    Applied upstream so a relation outside the
     *                                    whitelist is never traversed (recursion guard).
     * @return array<string, mixed> The exported data
     */
    protected function exportModel(object $model, ?array $fields = null): array
    {
        $data = [];
        $reflection = new ReflectionClass($model);

        foreach ($this->getExportableMethods($reflection) as $method => $attribute) {
            $name = $attribute->name ?? $this->normalizeName($method);
            if ($fields === null || in_array($name, $fields, true) === true) {
                $data[$name] = $this->processExportValue($model->$method(), $attribute);
            }
        }

        foreach ($this->getExportableProperties($reflection) as $property => $attribute) {
            $name = $attribute->name ?? $property;
            if ($fields === null || in_array($name, $fields, true) === true) {
                $data[$name] = $this->processExportValue($model->$property, $attribute);
            }
        }

        foreach ($this->getClassExportables($reflection) as $attribute) {
            if ($attribute->method !== null) {
                $name = $attribute->name ?? $this->normalizeName($attribute->method);
            } else {
                $name = $attribute->name ?? $attribute->property;
            }
            if ($fields === null || in_array($name, $fields, true) === true) {
                $value = $attribute->method !== null ? $model->{$attribute->method}() : $model->{$attribute->property};
                $data[$name] = $this->processExportValue($value, $attribute);
            }
        }

        return $data;
    }

    /**
     * @param ReflectionClass $reflection
     * @return array<string, Exportable> Method name => Exportable attribute
     */
    private function getExportableMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Exportable::class);
            if (empty($attributes) === false) {
                $methods[$method->getName()] = $attributes[0]->newInstance();
            }
        }

        return $methods;
    }

    /**
     * @param ReflectionClass $reflection
     * @return array<string, Exportable> Property name => Exportable attribute
     */
    private function getExportableProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(Exportable::class);
            if (empty($attributes) === false) {
                $properties[$property->getName()] = $attributes[0]->newInstance();
            }
        }

        return $properties;
    }

    /**
     * @param ReflectionClass $reflection
     * @return Exportable[]
     */
    private function getClassExportables(ReflectionClass $reflection): array
    {
        $exportables = [];

        foreach ($reflection->getAttributes(Exportable::class) as $attribute) {
            $exportables[] = $attribute->newInstance();
        }

        return $exportables;
    }

    /**
     * @param ReflectionClass $reflection
     * @return array<string, Importable> Method name => Importable attribute
     */
    private function getImportableMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Importable::class);
            if (empty($attributes) === false) {
                $methods[$method->getName()] = $attributes[0]->newInstance();
            }
        }

        return $methods;
    }

    /**
     * @param ReflectionClass $reflection
     * @return array<string, Importable> Property name => Importable attribute
     */
    private function getImportableProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(Importable::class);
            if (empty($attributes) === false) {
                $properties[$property->getName()] = $attributes[0]->newInstance();
            }
        }

        return $properties;
    }

    /**
     * @param ReflectionClass $reflection
     * @return Importable[]
     */
    private function getClassImportables(ReflectionClass $reflection): array
    {
        $importables = [];

        foreach ($reflection->getAttributes(Importable::class) as $attribute) {
            $importables[] = $attribute->newInstance();
        }

        return $importables;
    }

    /**
     * Normalize an accessor name to its key: strips the get/set/is prefix (and a
     * trailing Query for relations), lower-cased first letter. Same derivation in
     * both directions — getLabel, setLabel, isLabel all yield 'label'.
     */
    private function normalizeName(string $methodName): string
    {
        if (str_starts_with($methodName, 'get') === true) {
            $name = substr($methodName, 3);
            if (str_ends_with($name, 'Query') === true) {
                $name = substr($name, 0, -5);
            }
            $name = lcfirst($name);
        } elseif (str_starts_with($methodName, 'set') === true) {
            $name = lcfirst(substr($methodName, 3));
        } elseif (str_starts_with($methodName, 'is') === true) {
            $name = lcfirst(substr($methodName, 2));
        } else {
            $name = $methodName;
        }

        return $name;
    }

    /**
     * Process a value for export.
     */
    private function processExportValue(mixed $value, Exportable $attribute): mixed
    {
        if ($value === null) {
            $result = null;
        } elseif ($value instanceof ActiveQueryInterface) {
            $result = $this->processActiveQuery($value, $attribute->fields);
        } elseif ($attribute->format !== null) {
            $formatter = $this->formatterFor($attribute->format);
            $result = $formatter::load($value);
        } elseif (is_array($value) === true) {
            $result = $this->processArrayWithFiles($value);
        } else {
            $result = $value;
        }

        return $result;
    }

    /**
     * Process a value for import.
     */
    private function processImportValue(mixed $value, Importable $attribute): mixed
    {
        if ($value === null) {
            $result = null;
        } elseif ($attribute->format !== null) {
            $formatter = $this->formatterFor($attribute->format);
            $result = $formatter::save($value);
        } else {
            $result = $value;
        }

        return $result;
    }

    /**
     * Returns the formatter class to actually use: the app-provided substitution
     * if one is registered in formatterMap for this base formatter, the base one otherwise.
     *
     * @param string $formatter The base formatter FQCN declared on the attribute
     * @return string The formatter FQCN to invoke
     */
    private function formatterFor(string $formatter): string
    {
        return $this->formatterMap[$formatter] ?? $formatter;
    }

    /**
     * Process an ActiveQuery for export (one or many, with optional field whitelist).
     *
     * @param array<string>|null $fields Limit exported fields to this list
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function processActiveQuery(ActiveQueryInterface $query, ?array $fields = null): array|null
    {
        $reflection = new ReflectionClass($query);
        $multipleProperty = null;

        while ($reflection) {
            if ($reflection->hasProperty('multiple') === true) {
                $multipleProperty = $reflection->getProperty('multiple');
                $multipleProperty->setAccessible(true);
                break;
            }
            $reflection = $reflection->getParentClass();
        }

        $isMany = $multipleProperty !== null && $multipleProperty->getValue($query) === true;

        if ($isMany === true) {
            $items = [];
            foreach ($query->each() as $item) {
                $items[] = $this->exportModel($item, $fields);
            }
            $result = $items;
        } else {
            $item = $query->one();
            if ($item === null) {
                $result = null;
            } else {
                $result = $this->exportModel($item, $fields);
            }
        }

        return $result;
    }

    /**
     * Process an array and convert any file paths to base64 (recursive).
     *
     * @param array<string, mixed> $data The array to process
     * @return array<string, mixed> The processed array
     */
    private function processArrayWithFiles(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value) === true && $this->isFilePath($value) === true) {
                $result[$key] = $this->processFileString($value);
            } elseif (is_array($value) === true) {
                $result[$key] = $this->processArrayWithFiles($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if a string value contains a known file prefix.
     */
    private function isFilePath(string $value): bool
    {
        return $this->fileService->canHandle($value);
    }

    /**
     * Process a file string (single or comma-separated multiple files).
     *
     * @return array{name: string, data: string}|array<int, array{name: string, data: string}|null>|null
     */
    private function processFileString(string $value): array|string|null
    {
        $files = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (count($files) === 1) {
            $result = $this->exportFile($files[0]);
        } else {
            $encoded = [];
            foreach ($files as $file) {
                $encoded[] = $this->exportFile($file);
            }
            $result = $encoded;
        }

        return $result;
    }

    /**
     * Encode a file to base64 data URI with its original filename.
     *
     * @param string $filePath The file path (can use aliases like @blfs/)
     * @return array{name: string, data: string}|string|null The file object or null if not found
     */
    protected function exportFile(string $filePath): array|string|null
    {
        if ($this->isFilePath($filePath) === false) {
            $result = null;
        } elseif ($this->fileService->fileExists($filePath) === false) {
            $result = null;
        } else {
            $content = $this->fileService->read($filePath);
            $mimeType = $this->fileService->mimeType($filePath);
            $result = [
                'name' => basename($filePath),
                'data' => 'data:'.$mimeType.';base64,'.base64_encode($content),
            ];
        }

        return $result;
    }
}
