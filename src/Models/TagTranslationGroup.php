<?php

declare(strict_types=1);

/**
 * TagTranslationGroup.php
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
 * TagTranslationGroup model - Tag translation grouping.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class TagTranslationGroup extends BaseTagTranslationGroup
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
}
