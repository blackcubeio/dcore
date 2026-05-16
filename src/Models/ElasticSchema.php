<?php

declare(strict_types=1);

/**
 * ElasticSchema.php
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

/**
 * ElasticSchema model for BLAMS.
 * Extends the base ElasticSchema from yii3-elastic with relations and new fields.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticSchema extends BaseElasticSchema
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
}
