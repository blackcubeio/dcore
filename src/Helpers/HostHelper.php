<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Entities\Host;

/**
 * Finds the Host that serves a hostname.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HostHelper
{
    /**
     * Host for a hostname: exact name match, else the generic host (id 1).
     * One query (name OR id=1), specific wins over generic.
     */
    public static function findByHostname(string $hostname): ?Host
    {
        $specific = null;
        $generic = null;
        $hostsQuery = Host::query()
            ->andWhere(['name' => $hostname])
            ->orWhere(['id' => 1])
            ->active();
        /** @var Host $host */
        foreach ($hostsQuery->each() as $host) {
            if ($host->getName() === $hostname) {
                $specific = $host;
            } else {
                $generic = $host;
            }
        }
        return $specific ?? $generic;
    }
}
