<?php

declare(strict_types=1);

/**
 * AtDateScope.php
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
 * Store a reference date in query state.
 *
 * Used by AvailableScope and PublishableScope to compare
 * dateStart/dateEnd against a specific date instead of NOW().
 */
class AtDateScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'atDate';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        $date = $parameters?->value('date');
        if ($date !== null) {
            $query->setState('referenceDate', $date);
        }
    }
}
