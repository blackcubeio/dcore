<?php

declare(strict_types=1);

/**
 * FileFormatter.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models\Formatters;

use Blackcube\Dcore\Interfaces\FormatterInterface;
use Blackcube\Dcore\Services\FileService;
use Blackcube\Injector\Injector;

/**
 * Formats a file link to/from its base64 data URI representation used in dumps.
 * - load = export: file link(s) → {name, data: dataUri} object(s).
 * - save = import: data URI object(s) → write the file(s) → return the @blfs/ link(s).
 * Single value or comma-separated list. FileService is resolved from the container.
 *
 * On save(), $parameters['parents'] gives the storage path segments
 * (e.g. ['contents', 12, 'blocs', 34] → @blfs/contents/12/blocs/34/<file>).
 */
class FileFormatter implements FormatterInterface
{
    public static function load(mixed $value, array $parameters = []): mixed
    {
        if (is_string($value) === false || $value === '') {
            $result = $value;
        } else {
            $fileService = Injector::get(FileService::class);
            $links = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            $encoded = [];
            foreach ($links as $link) {
                $encoded[] = self::encode($fileService, $link);
            }
            $result = count($encoded) === 1 ? $encoded[0] : $encoded;
        }

        return $result;
    }

    public static function save(mixed $value, array $parameters = []): mixed
    {
        $parents = $parameters['parents'] ?? [];

        if (is_array($value) === true && isset($value['data']) === true) {
            $result = self::decode((string) $value['data'], $parents, $value['name'] ?? null);
        } elseif (is_array($value) === true && isset($value[0]) === true) {
            $paths = [];
            foreach ($value as $fileObj) {
                $path = self::decode((string) ($fileObj['data'] ?? ''), $parents, $fileObj['name'] ?? null);
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
            $result = implode(', ', $paths);
        } elseif (is_string($value) === true && str_starts_with($value, 'data:') === true) {
            $result = self::decode($value, $parents);
        } else {
            $result = $value;
        }

        return $result;
    }

    private static function encode(FileService $fileService, string $link): ?array
    {
        if (self::isFileLink($fileService, $link) === false || $fileService->fileExists($link) === false) {
            $result = null;
        } else {
            $content = $fileService->read($link);
            $result = [
                'name' => basename($link),
                'data' => 'data:'.$fileService->mimeType($link).';base64,'.base64_encode($content),
            ];
        }

        return $result;
    }

    private static function decode(string $dataUri, array $parents, ?string $filename = null): ?string
    {
        $result = null;

        if (preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUri, $matches) === 1) {
            $content = base64_decode($matches[2], true);
            if ($content !== false) {
                $name = ($filename === null || $filename === '') ? bin2hex(random_bytes(8)) : $filename;
                $fileService = Injector::get(FileService::class);
                $path = $fileService->getDefaultAlias().'/'.implode('/', $parents).'/'.$name;
                $fileService->write($path, $content);
                $result = $path;
            }
        }

        return $result;
    }

    private static function isFileLink(FileService $fileService, string $value): bool
    {
        return $fileService->canHandle($value);
    }
}
