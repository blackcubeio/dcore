<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class MdService
{
    public function __construct(
        private readonly ElasticMdService $elasticMdService,
    ) {}

    /**
     * @param Host $host Resolved host
     * @param string $hostname Request hostname (used for URLs)
     * @param string $path Path WITHOUT .md suffix
     * @param string $scheme Request scheme (http/https)
     */
    public function generate(Host $host, string $hostname, string $path, string $scheme): ?string
    {
        $slug = Slug::query()
            ->andWhere([
                'hostId' => $host->getId(),
                'path' => $path,
            ])
            ->active()
            ->one();

        $markdown = null;
        $element = $slug?->getElement();
        if ($element !== null) {
            $markdown = $this->elasticMdService->renderMarkdown($element, $scheme, $hostname);
        }

        return $markdown;
    }
}
