<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * A database upgrade a module declares in its params ('blackcube/dcore' => 'upgrades' => [class]).
 * The `upgrade` command applies the ones the upgrades table does not hold yet.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface UpgradeInterface
{
    /**
     * Applies the upgrade. Idempotent: applied again, it leaves the base unchanged.
     */
    public function apply(): void;
}
