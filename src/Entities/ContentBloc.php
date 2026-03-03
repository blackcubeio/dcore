<?php

declare(strict_types=1);

namespace Blackcube\Dcore\Entities;

use Blackcube\Dcore\Models\ContentBloc as BaseContentBloc;
use Blackcube\Dcore\Models\ScopedQuery;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class ContentBloc extends BaseContentBloc
{
    public static function query(
        ActiveRecordInterface|Closure|string|null $modelClass = null
    ): ActiveQueryInterface {
        return (new ScopedQuery($modelClass ?? static::class))->publishable();
    }
}
