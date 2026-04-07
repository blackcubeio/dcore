<?php

declare(strict_types=1);

/**
 * di.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\Dcore\Interfaces\JsonLdBuilderInterface;
use Blackcube\Dcore\Interfaces\PreviewContextInterface;
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;
use Blackcube\Dcore\Interfaces\SlugGeneratorInterface;
use Blackcube\Dcore\Services\HazeltreeSlugGenerator;
use Blackcube\Dcore\Services\JsonLdBuilder;
use Blackcube\Dcore\Services\PreviewContext;
use Blackcube\Dcore\Services\PreviewManager;

/** @var array $params */

return [
    SlugGeneratorInterface::class => HazeltreeSlugGenerator::class,
    PreviewManagerInterface::class => PreviewManager::class,
    PreviewContextInterface::class => PreviewContext::class,
    JsonLdBuilderInterface::class => JsonLdBuilder::class,
];
