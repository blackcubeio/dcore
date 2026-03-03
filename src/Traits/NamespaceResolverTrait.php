<?php

declare(strict_types=1);

/**
 * NamespaceResolverTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

/**
 * NamespaceResolverTrait - Resolves FQCN to current namespace.
 * When Entities\Content extends Models\Content, relations resolve to Entities\ namespace.
 */
trait NamespaceResolverTrait
{
    protected function resolve(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        $shortName = end($parts);
        return substr(static::class, 0, strrpos(static::class, '\\')) . '\\' . $shortName;
    }
}
