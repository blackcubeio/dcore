<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models\Scopes;

use Blackcube\ActiveRecord\ScopeInterface;
use Blackcube\ActiveRecord\ScopableQueryInterface;
use Blackcube\ActiveRecord\ScopeParametersInterface;

/**
 * Filter by languageId.
 *
 * Skips silently if the model has no 'languageId' property.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class LanguageScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'language';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        if (property_exists($modelClass, 'languageId') === true) {
            $languageId = $parameters?->value('languageId');
            if ($languageId !== null) {
                $query->andWhere(['languageId' => $languageId]);
            }
        }
    }
}
