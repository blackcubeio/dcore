<?php

declare(strict_types=1);

/**
 * MdService.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Services\ElasticMdService;

/**
 * Generates markdown content for a CMS element identified by path.
 * Returns null if slug not found, inactive, or element missing.
 */
final class MdService
{
    public function __construct(
        private readonly ElasticMdService $elasticMdService,
    ) {}

    /**
     * @param string $hostname Request hostname
     * @param string $path Path WITHOUT .md suffix
     * @param string $scheme Request scheme (http/https)
     */
    public function generate(string $hostname, string $path, string $scheme): ?string
    {
        $host = $this->resolveHost($hostname);
        if ($host === null) {
            return null;
        }

        $slug = Slug::query()
            ->andWhere(['hostId' => $host->getId(), 'path' => $path])
            ->active()
            ->one();

        if ($slug === null) {
            return null;
        }

        $element = $slug->getElement();
        if ($element === null) {
            return null;
        }

        return $this->elasticMdService->renderMarkdown($element, $scheme, $hostname);
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
