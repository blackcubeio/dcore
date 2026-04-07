<?php

declare(strict_types=1);

/**
 * PublishableScope.php
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
use Blackcube\Dcore\Interfaces\PreviewManagerInterface;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeInterface;
use Blackcube\Injector\Injector;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;

/**
 * Filter by publishability across the hazeltree ancestor chain.
 *
 * Excludes elements whose ancestors are inactive or outside date range.
 * Falls back to AvailableScope if the model has no hazeltree.
 */
class PublishableScope implements ScopeInterface
{
    public static function name(): string
    {
        return 'publishable';
    }

    public function process(ScopableQueryInterface $query, string $modelClass, ?ScopeParametersInterface $parameters = null): void
    {
        $publishable = $parameters?->value('publishable') ?? true;

        if (!is_subclass_of($modelClass, HazeltreeInterface::class)) {
            $query->available(available: $publishable);
            return;
        }

        $preview = Injector::has(PreviewManagerInterface::class)
            ? Injector::get(PreviewManagerInterface::class)
            : null;
        $previewActive = $preview !== null && $preview->isActive();

        if ($previewActive && $preview->getSimulateDate() === null) {
            return;
        }

        $simulateDate = $previewActive ? $preview->getSimulateDate() : null;
        $hasDates = method_exists($modelClass, 'getDateStart') && method_exists($modelClass, 'getDateEnd');

        $referenceDate = $query->getState('referenceDate');
        $dateRef = $simulateDate !== null
            ? new Expression(':pubDate', [':pubDate' => $simulateDate])
            : ($referenceDate !== null
                ? new Expression(':pubDate', [':pubDate' => $referenceDate])
                : new Expression('NOW()'));

        $ancestorConditions = [];

        if (!$previewActive) {
            $ancestorConditions[] = ['ancestors.[[active]]' => 0];
        }
        if ($hasDates) {
            $ancestorConditions[] = ['and',
                ['is not', 'ancestors.[[dateStart]]', null],
                ['>', 'ancestors.[[dateStart]]', $dateRef],
            ];
            $ancestorConditions[] = ['and',
                ['is not', 'ancestors.[[dateEnd]]', null],
                ['<', 'ancestors.[[dateEnd]]', $dateRef],
            ];
        }

        if (empty($ancestorConditions)) {
            return;
        }

        $tableName = (new $modelClass())->tableName();
        $from = $query->getFrom();
        $qualifier = (!empty($from) && is_string(array_key_first($from)))
            ? (string) array_key_first($from)
            : $tableName;

        $ancestorsQuery = (new Query(ConnectionProvider::get()))
            ->from(['ancestors' => $tableName])
            ->select(new Expression('1'))
            ->andWhere(new Expression(
                "ancestors.[[left]] <= {$qualifier}.[[left]] AND ancestors.[[right]] >= {$qualifier}.[[right]]"
            ))
            ->andWhere(count($ancestorConditions) === 1
                ? $ancestorConditions[0]
                : array_merge(['or'], $ancestorConditions)
            );

        $operator = $publishable ? 'not exists' : 'exists';

        $query->andWhere([$operator, $ancestorsQuery]);
    }
}
