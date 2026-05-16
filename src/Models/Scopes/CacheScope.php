<?php

declare(strict_types=1);

/**
 * CacheScope.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models\Scopes;

use Blackcube\ActiveRecord\ScopeInterface;
use Blackcube\ActiveRecord\ScopableQueryInterface;
use Blackcube\ActiveRecord\ScopeParametersInterface;

/**
 * Enable query-level caching via state.
 *
 * Stores cacheEnabled/cacheTtl/cacheDependency in query state.
 * ScopableQuery::one()/all()/scalar() read these to cache results.
 */
class CacheScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'cache';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        $query->setState('cacheEnabled', true);
        $query->setState('cacheTtl', $parameters?->value('ttl'));
        $query->setState('cacheDependency', $parameters?->value('dependency'));
    }
}
