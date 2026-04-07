<?php

declare(strict_types=1);

/**
 * ModelKindTrait.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Enums\ModelKind;

/**
 * Identifies whether an ActiveRecord instance is a Model or an Entity.
 * Applied on Base* classes (default: Model), overridden in Entities (Entity).
 */
trait ModelKindTrait
{
    protected ModelKind $modelKind = ModelKind::Model;

    public function isEntityKind(): bool
    {
        return $this->modelKind === ModelKind::Entity;
    }
}
