<?php

declare(strict_types=1);

/**
 * ScopedQueryTrait.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Models\ScopableQuery;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * ScopedQueryTrait - Provides ScopableQuery factory, clean relation queries,
 * and FQCN resolution to current namespace.
 *
 * When Entities\Content extends Models\Content, fqcn() resolves to Entities\ namespace.
 */
trait ScopedQueryTrait
{
    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return ScopableQuery::create($modelClass ?? static::class);
    }

    protected function createRelationQuery(
        ActiveRecordInterface|string $modelClass,
        array $link,
        bool $multiple,
    ): ActiveQueryInterface {
        $query = ScopableQuery::createFor($modelClass)->primaryModel($this)->link($link)->multiple($multiple);
        if ($this->isEntityKind() === true) {
            $query->setState('deferPublishable', true);
        }
        return $query;
    }

    private static array $scopedFqcns = [];

    protected function fqcn(string $fqcn): string
    {
        if (isset(self::$scopedFqcns[$fqcn]) === false) {
            $parts = explode('\\', $fqcn);
            $shortName = end($parts);
            self::$scopedFqcns[$fqcn] = substr(static::class, 0, strrpos(static::class, '\\')).'\\'.$shortName;
        }
        return self::$scopedFqcns[$fqcn];
    }
}
