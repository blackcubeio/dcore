<?php

declare(strict_types=1);

/**
 * SsrRouteProviderInterface.php
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

interface SsrRouteProviderInterface
{
    /**
     * @return string[] Available SSR route names
     */
    public function getAvailableRoutes(): array;
}
