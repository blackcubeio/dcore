<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

return [
    'blackcube/dcore' => [
        // id => class of the available add-ons; each add-on adds its own entry in its params.
        'addons' => [],
        // Upgrade classes; each module adds its own in its params, `upgrade` applies the pending ones.
        'upgrades' => [],
    ],
];
