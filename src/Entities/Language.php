<?php

declare(strict_types=1);

namespace Blackcube\Dcore\Entities;

use Blackcube\Dcore\Enums\ModelKind;
use Blackcube\Dcore\Models\Language as BaseLanguage;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

final class Language extends BaseLanguage
{
    protected ModelKind $modelKind = ModelKind::Entity;

    public static function query(
        ActiveRecordInterface|Closure|string|null $modelClass = null
    ): ActiveQueryInterface {
        return parent::query($modelClass)->publishable()->cache(ttl: 3600);
    }
}
