<?php

declare(strict_types=1);

/**
 * ElasticQuery.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\Elastic\BuildElasticFormulaeTrait;
use Blackcube\ActiveRecord\Elastic\ElasticQueryInterface;
use Blackcube\ActiveRecord\Elastic\BaseElasticQueryTrait;

/**
 * Query with elastic JSON virtual column support.
 */
class ElasticQuery extends ScopableQuery implements ElasticQueryInterface
{
    use BaseElasticQueryTrait;
    use BuildElasticFormulaeTrait;
}
