<?php

declare(strict_types=1);

/**
 * LinkTrait.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Models\Link;

/**
 * Minimal trait to extract Link.
 */
trait LinkTrait
{
    public function getLink(): ?Link
    {
        $link = null;
        if (method_exists($this, 'getSlugQuery') === true) {
            $slug = $this->getSlugQuery()->one();
            $link = $slug?->getLink();
            if($link !== null && method_exists($this, 'getLanguageId') === true) {
                /** @var Link $link */
                $link = $link->withAttribute('hrefLang', $this->getLanguageId());
            }
        }
        return $link;
    }
}
