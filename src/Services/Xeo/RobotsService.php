<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\GlobalXeo;
use Blackcube\Dcore\Entities\Host;

/**
 * Generates robots.txt content from GlobalXeo data.
 * Returns null if no active Robots entry exists for the host.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RobotsService
{
    public function generate(Host $host): ?string
    {
        $globalXeo = GlobalXeo::query()
            ->andWhere([
                'hostId' => $host->getId(),
                'kind' => 'Robots',
            ])
            ->active()
            ->one();

        $robots = null;
        if ($globalXeo !== null) {
            $robots = $globalXeo->rawData ?? '';
        }

        return $robots;
    }
}
