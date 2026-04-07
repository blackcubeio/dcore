<?php

declare(strict_types=1);

/**
 * Menu.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
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
 * Menu model - Navigation with tree structure.
 * Uses HazeltreeTrait for tree structure (limited to 4 levels).
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class Menu extends BaseMenu implements HazeltreeInterface
{
    use HazeltreeTrait;
    use ScopedQueryTrait;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return HazeltreeQuery::create($modelClass ?? static::class);
    }
}
