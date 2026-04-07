<?php

declare(strict_types=1);

/**
 * QueryCache.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

/**
 * Per-table cache dependency factory.
 *
 * Each table gets its own ReusableDependency tracking MAX(dateUpdate).
 * ScopableQuery calls forTable() automatically at execution time.
 */
class QueryCache
{
    /** @var array<string, ReusableDependency> */
    private static array $cache = [];

    /**
     * Get or create a cache dependency for a single table.
     *
     * @param string $tableName Raw table name (e.g. '{{%contents}}')
     */
    public static function forTable(string $tableName): ReusableDependency
    {
        $clean = str_replace(['{{%', '}}'], '', $tableName);

        return self::$cache[$clean] ??= new ReusableDependency(
            'dcore.dep.' . $clean,
            $tableName,
        );
    }
}
