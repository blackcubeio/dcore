<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Addons;

use Blackcube\Dcore\Addons\AddonInterface;

/**
 * Add-on of the test suite whose migration is not revertible: it cannot be deactivated.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class SealedAddon implements AddonInterface
{
    public static function getName(): string
    {
        return 'Sealed';
    }

    public static function getPackage(): string
    {
        return 'blackcube/dcore';
    }

    public static function getMigrationsNamespace(): ?string
    {
        return 'Blackcube\\Dcore\\Tests\\Support\\Addons\\SealedMigrations';
    }
}
