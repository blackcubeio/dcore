<?php

declare(strict_types=1);

/**
 * Sitemap.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\PopulatePropertyTrait;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Sitemap model - Sitemap par slug.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class Sitemap extends BaseSitemap
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
}
