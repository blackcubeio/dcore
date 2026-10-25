<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\BatchedEachQueryTrait;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeQueryInterface;
use Blackcube\ActiveRecord\Hazeltree\BaseHazeltreeQueryTrait;

/**
 * Query with tree navigation support.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HazeltreeQuery extends ScopableQuery implements HazeltreeQueryInterface
{
    use BaseHazeltreeQueryTrait;
}
