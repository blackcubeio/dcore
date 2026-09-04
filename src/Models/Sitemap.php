<?php

declare(strict_types=1);

/**
 * Sitemap.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\PopulatePropertyTrait;
use Blackcube\Dcore\Attributes\ResetQueryCacheOnWrite;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Sitemap model - Sitemap per slug.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
#[ResetQueryCacheOnWrite]
class Sitemap extends BaseSitemap
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
}
