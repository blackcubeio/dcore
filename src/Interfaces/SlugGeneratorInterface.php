<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Tag;

/**
 * Generates the slug path of a content or a tag.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface SlugGeneratorInterface
{
    public function getElementSlug(Content|Tag  $element): string;
}
