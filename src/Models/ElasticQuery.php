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

/**
 * Query with elastic JSON virtual column support.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class ElasticQuery extends ScopableQuery implements ElasticQueryInterface
{
    use BaseElasticQueryTrait;
    use BuildElasticFormulaeTrait;
}
