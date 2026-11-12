<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Entities;

use Blackcube\Dcore\Enums\ModelKind;
use Blackcube\Dcore\Models\Addon as BaseAddon;

/**
 * Front-office Addon: the registry carries no publication state, a row is an active add-on,
 * read as is.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class Addon extends BaseAddon
{
    protected ModelKind $modelKind = ModelKind::Entity;
}
