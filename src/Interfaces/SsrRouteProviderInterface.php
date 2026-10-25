<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * Provides the SSR routes available to the CMS: their names, and the handler behind a route.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface SsrRouteProviderInterface
{
    /**
     * @return string[] Available SSR route names
     */
    public function getAvailableRoutes(): array;

    /**
     * Get handler info for a route.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getHandlerInfo(string $route): ?array;

    /**
     * Get error handler info by route name.
     *
     * @return array{class: string, mode: string, method: ?string, expects: array}|null
     */
    public function getErrorHandlerInfoByRoute(string $route): ?array;
}
