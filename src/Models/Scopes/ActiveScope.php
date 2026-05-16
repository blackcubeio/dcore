<?php

declare(strict_types=1);

/**
 * ActiveScope.php
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
 * Filter by active flag.
 *
 * Skips silently if the model has no 'active' property.
 */
class ActiveScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'active';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        if (!property_exists($modelClass, 'active')) {
            return;
        }

        $query->andWhere(['active' => $parameters?->value('active') ?? true]);
    }
}
