<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\BatchedEachQueryTrait;
use Blackcube\ActiveRecord\Elastic\BuildElasticFormulaeTrait;
use Blackcube\ActiveRecord\Elastic\ElasticQueryInterface;
use Blackcube\ActiveRecord\Elastic\BaseElasticQueryTrait;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeQueryInterface;
use Blackcube\ActiveRecord\Hazeltree\BaseHazeltreeQueryTrait;

/**
 * Query with both elastic JSON virtual columns and tree navigation.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticHazeltreeQuery extends ScopableQuery implements ElasticQueryInterface, HazeltreeQueryInterface
{
    use BaseElasticQueryTrait;
    use BaseHazeltreeQueryTrait;
    use BuildElasticFormulaeTrait;
}
