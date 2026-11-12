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
 * Add-on of the test suite whose second migration throws until the test says otherwise.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class BrokenAddon implements AddonInterface
{
    public static function getName(): string
    {
        return 'Broken';
    }

    public static function getPackage(): string
    {
        return 'blackcube/dcore';
    }

    public static function getMigrationsNamespace(): ?string
    {
        return 'Blackcube\\Dcore\\Tests\\Support\\Addons\\BrokenMigrations';
    }
}
