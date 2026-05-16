<?php

declare(strict_types=1);

/**
 * LlmMenu.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\Hazeltree\HazeltreeInterface;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeTrait;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * LlmMenu model - LLM navigation with tree structure.
 * Uses HazeltreeTrait for tree structure (limited to 3 levels: root / category / data).
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class LlmMenu extends BaseLlmMenu implements HazeltreeInterface
{
    use HazeltreeTrait;
    use ScopedQueryTrait;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return HazeltreeQuery::create($modelClass ?? static::class);
    }
}
