<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Addons;

/**
 * The interface every add-on implements. An add-on declares itself in its own params, under
 * 'blackcube/dcore' => 'addons' => [id => class]; dcore reads it to name the add-on, to find
 * its Composer package and its migrations.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface AddonInterface
{
    /**
     * Name shown to the administrator.
     */
    public static function getName(): string;

    /**
     * Composer package name (e.g. "blackcube/mcp"), used to read the installed version.
     */
    public static function getPackage(): string;

    /**
     * Namespace of the add-on migrations, null when the add-on has no table of its own.
     */
    public static function getMigrationsNamespace(): ?string;
}
