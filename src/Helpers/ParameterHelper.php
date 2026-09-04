<?php

declare(strict_types=1);

/**
 * ParameterHelper.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Models\Parameter;

class ParameterHelper
{
    private static ?array $parameters = null;

    public static function get(string $domain, string $name): ?string
    {
        self::init();
        $value = self::$parameters[$domain][$name] ?? null;
        return $value;
    }

    private static function init(): void
    {
        if (self::$parameters === null) {
            self::$parameters = [];
            $parametersQuery = Parameter::query();
            /** @var Parameter $parameter */
            foreach ($parametersQuery->each() as $parameter) {
                self::$parameters[$parameter->getDomain()][$parameter->getName()] = $parameter->getValue();
            }
        }
    }
}
