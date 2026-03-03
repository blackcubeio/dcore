<?php

declare(strict_types=1);

namespace Blackcube\Dcore\Entities;

use Blackcube\Dcore\Models\Slug as BaseSlug;
use Blackcube\Dcore\Models\ScopedQuery;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class Slug extends BaseSlug
{
    public static function query(
        ActiveRecordInterface|Closure|string|null $modelClass = null
    ): ActiveQueryInterface {
        return (new ScopedQuery($modelClass ?? static::class))->publishable();
    }
}
