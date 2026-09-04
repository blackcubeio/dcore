<?php

declare(strict_types=1);

/**
 * ContentTranslationGroup.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Entities;

use Blackcube\Dcore\Enums\ModelKind;
use Blackcube\Dcore\Models\ContentTranslationGroup as BaseContentTranslationGroup;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

class ContentTranslationGroup extends BaseContentTranslationGroup
{
    protected ModelKind $modelKind = ModelKind::Entity;

    public static function query(
        ActiveRecordInterface|Closure|string|null $modelClass = null
    ): ActiveQueryInterface {
        return parent::query($modelClass)->publishable()->cache(ttl: 3600);
    }
}
