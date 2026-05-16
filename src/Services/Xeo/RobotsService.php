<?php

declare(strict_types=1);

/**
 * RobotsService.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\GlobalXeo;
use Blackcube\Dcore\Entities\Host;

/**
 * Generates robots.txt content from GlobalXeo data.
 * Returns null if no active Robots entry exists for the host.
 */
final class RobotsService
{
    public function generate(string $hostname): ?string
    {
        $host = $this->resolveHost($hostname);
        if ($host === null) {
            return null;
        }

        $globalXeo = GlobalXeo::query()
            ->andWhere(['hostId' => $host->getId(), 'kind' => 'Robots'])
            ->active()
            ->one();

        if ($globalXeo === null) {
            return null;
        }

        return $globalXeo->rawData ?? '';
    }

    private function resolveHost(string $hostname): ?Host
    {
        $host = Host::query()
            ->andWhere(['name' => $hostname])
            ->active()
            ->one();

        if ($host !== null) {
            return $host;
        }

        return Host::query()
            ->andWhere(['id' => 1])
            ->active()
            ->one();
    }
}
