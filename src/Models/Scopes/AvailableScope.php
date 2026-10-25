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
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;
use Blackcube\Injector\Injector;
use Yiisoft\Db\Expression\Expression;

/**
 * Filter by active + dateStart/dateEnd range.
 *
 * Preview mode: bypasses active filter, optionally simulates a date.
 * Falls back to ActiveScope if the model has no date columns.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class AvailableScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'available';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        $available = $parameters?->value('available') ?? true;

        $preview = Injector::has(PreviewManagerInterface::class)
            ? Injector::get(PreviewManagerInterface::class)
            : null;
        $previewActive = $preview !== null && $preview->isActive() === true;

        if ($previewActive === false || $preview->getSimulateDate() !== null) {
            $simulateDate = $previewActive ? $preview->getSimulateDate() : null;
            $hasDates = method_exists($modelClass, 'getDateStart') === true && method_exists($modelClass, 'getDateEnd') === true;

            if ($previewActive === false) {
                $query->active(active: $available);
            }

            if ($hasDates === true) {
                $referenceDate = $query->getState('referenceDate');
                $dateExpr = $simulateDate !== null
                    ? new Expression(':refDate', [':refDate' => $simulateDate])
                    : ($referenceDate !== null
                        ? new Expression(':refDate', [':refDate' => $referenceDate])
                        : new Expression('NOW()'));

                if ($available) {
                    $query->andWhere([
                        'or',
                        ['dateStart' => null],
                        [
                            '<=',
                            'dateStart',
                            $dateExpr,
                        ],
                    ])->andWhere([
                        'or',
                        ['dateEnd' => null],
                        [
                            '>=',
                            'dateEnd',
                            $dateExpr,
                        ],
                    ]);
                } else {
                    $query->andWhere([
                        'or',
                        [
                            '>',
                            'dateStart',
                            $dateExpr,
                        ],
                        [
                            '<',
                            'dateEnd',
                            $dateExpr,
                        ],
                    ]);
                }
            }
        }
    }
}
