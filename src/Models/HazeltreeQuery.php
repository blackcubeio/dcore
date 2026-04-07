<?php

declare(strict_types=1);

/**
 * HazeltreeQuery.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\Hazeltree\HazeltreeQueryInterface;
use Blackcube\ActiveRecord\Hazeltree\BaseHazeltreeQueryTrait;

/**
 * Query with tree navigation support.
 */
class HazeltreeQuery extends ScopableQuery implements HazeltreeQueryInterface
{
    use BaseHazeltreeQueryTrait;
}
