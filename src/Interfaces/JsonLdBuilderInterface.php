<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * Builds JSON-LD structured data arrays for a given slug.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface JsonLdBuilderInterface
{
    /**
     * Build JSON-LD arrays for a slug on a given host.
     *
     * @param int $slugId
     * @param string $host Hostname (used for GlobalXeo resolution)
     * @return array<int, array<string, mixed>> Array of JSON-LD objects
     */
    public function build(int $slugId, string $host): array;
}
