<?php

declare(strict_types=1);

/**
 * SlugGeneratorInterface.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Tag;

interface SlugGeneratorInterface
{
    public function getElementSlug(Content|Tag  $element): string;
}
