<?php

declare(strict_types=1);

/**
 * ScopedQuery.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Interfaces\PreviewManagerInterface;
use Blackcube\Elastic\ElasticQuery;
use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeQueryTrait;
use Yiisoft\Db\Expression\Expression;

/**
 * Generic scoped query with automatic capability detection.
 *
 * Extends ElasticQuery to support JSON virtual columns.
 * Detects model capabilities and adapts available scopes:
 * - hasActive: model has 'active' property
 * - hasDates: model has getDateStart() and getDateEnd() methods
 * - hasHazeltree: model implements HazeltreeInterface
 */
class ScopedQuery extends ElasticQuery
{
    use HazeltreeQueryTrait;

    private static ?PreviewManagerInterface $previewManager = null;

    private ?string $tableName = null;
    private ?bool $isActive = null;
    private ?bool $hasLanguage = null;
    private ?bool $hasDates = null;
    private ?bool $hasHazeltree = null;
    private ?string $referenceDate = null;

    /**
     * Set preview manager (called at bootstrap, static context).
     */
    public static function setPreviewManager(PreviewManagerInterface $previewManager): void
    {
        self::$previewManager = $previewManager;
    }

    /**
     * Lazy initialization of capabilities.
     */
    private function ensureCapabilities(): void
    {
        if ($this->tableName !== null) {
            return;
        }

        $model = $this->getModel();
        $modelClass = $model::class;
        $this->tableName = $model->tableName();

        // Detect active property
        $this->isActive = property_exists($modelClass, 'active');

        // Detect language property
        $this->hasLanguage = property_exists($modelClass, 'languageId');

        // Detect date methods
        $this->hasDates = method_exists($modelClass, 'getDateStart')
            && method_exists($modelClass, 'getDateEnd');

        // Detect Hazeltree interface
        $this->hasHazeltree = $model instanceof HazeltreeInterface;
    }

    /**
     * Filter by active status.
     * Available if model has 'active' property.
     *
     * @param bool $active true for active records, false for inactive
     */
    public function active(bool $active = true): static
    {
        $this->ensureCapabilities();

        if (!$this->isActive) {
            return $this;
        }

        return $this->andWhere(["{$this->tableName}.[[active]]" => $active]);
    }

    /**
     * Filter by language.
     * Available if model has 'languageId' property.
     *
     * @param string $languageId Language ID (ex: 'fr', 'en')
     */
    public function language(string $languageId): static
    {
        $this->ensureCapabilities();

        if (!$this->hasLanguage) {
            return $this;
        }

        return $this->andWhere(["{$this->tableName}.[[languageId]]" => $languageId]);
    }

    /**
     * Set reference date for available() and publishable().
     * Does NOT filter by itself.
     */
    public function atDate(string $date): static
    {
        $this->referenceDate = $date;
        return $this;
    }

    /**
     * Filter by availability (within valid dates).
     *
     * Preview-aware:
     * - preview off → active + dates with NOW/referenceDate
     * - preview on, no simulateDate → no filter (everything visible)
     * - preview on, with simulateDate → dates with simulateDate only (no active filter)
     *
     * @param bool $available true for available records, false for unavailable
     */
    public function available(bool $available = true): static
    {
        $this->ensureCapabilities();

        $preview = self::$previewManager;
        $previewActive = $preview !== null && $preview->isActive();

        // Preview on, no simulated date → everything visible
        if ($previewActive && $preview->getSimulateDate() === null) {
            return $this;
        }

        // Determine date expression
        $simulateDate = $previewActive ? $preview->getSimulateDate() : null;

        if (!$this->hasDates && !$previewActive) {
            return $this->active($available);
        }

        if (!$this->hasDates) {
            // Preview with simulateDate but model has no dates → no filter
            return $this;
        }

        // Normal mode: add active filter. Preview mode: skip active.
        if (!$previewActive) {
            $this->active($available);
        }

        $dateExpr = $simulateDate !== null
            ? new Expression(':refDate', [':refDate' => $simulateDate])
            : ($this->referenceDate !== null
                ? new Expression(':refDate', [':refDate' => $this->referenceDate])
                : new Expression('NOW()'));

        if ($available) {
            return $this->andWhere([
                'or',
                ["{$this->tableName}.[[dateStart]]" => null],
                ['<=', "{$this->tableName}.[[dateStart]]", $dateExpr],
            ])->andWhere([
                'or',
                ["{$this->tableName}.[[dateEnd]]" => null],
                ['>=', "{$this->tableName}.[[dateEnd]]", $dateExpr],
            ]);
        }

        return $this->andWhere([
            'or',
            ['>', "{$this->tableName}.[[dateStart]]", $dateExpr],
            ['<', "{$this->tableName}.[[dateEnd]]", $dateExpr],
        ]);
    }

    /**
     * Filter by publishable status (record and all ancestors are active and available).
     *
     * Preview-aware (same logic as available):
     * - preview off → ancestors active + in dates
     * - preview on, no simulateDate → no filter
     * - preview on, with simulateDate → ancestors in dates only (no active check)
     *
     * @param bool $publishable true for publishable records, false for non-publishable
     */
    public function publishable(bool $publishable = true): static
    {
        $this->ensureCapabilities();

        if (!$this->hasHazeltree) {
            return $this->available($publishable);
        }

        $preview = self::$previewManager;
        $previewActive = $preview !== null && $preview->isActive();

        // Preview on, no simulated date → everything visible
        if ($previewActive && $preview->getSimulateDate() === null) {
            return $this;
        }

        $simulateDate = $previewActive ? $preview->getSimulateDate() : null;

        $dateRef = $simulateDate !== null
            ? new Expression(':pubDate', [':pubDate' => $simulateDate])
            : ($this->referenceDate !== null
                ? new Expression(':pubDate', [':pubDate' => $this->referenceDate])
                : new Expression('NOW()'));

        $query = $this
            ->innerJoin(
                "{$this->tableName} ancestors",
                "ancestors.[[left]] <= {$this->tableName}.[[left]] AND ancestors.[[right]] >= {$this->tableName}.[[right]]"
            )
            ->groupBy("{$this->tableName}.[[id]]");

        if ($publishable) {
            // Normal mode: check ancestors active. Preview: skip active check.
            if (!$previewActive) {
                $query = $query->andHaving(['=', new Expression('MIN(ancestors.[[active]])'), 1]);
            }

            if ($this->hasDates) {
                $query = $query
                    ->andHaving([
                        'or',
                        ['IS', new Expression('MAX(ancestors.[[dateStart]])'), null],
                        ['<=', new Expression('MAX(ancestors.[[dateStart]])'), $dateRef],
                    ])
                    ->andHaving([
                        'or',
                        ['IS', new Expression('MIN(ancestors.[[dateEnd]])'), null],
                        ['>=', new Expression('MIN(ancestors.[[dateEnd]])'), $dateRef],
                    ]);
            }

            return $query;
        }

        // Not publishable
        if ($this->hasDates) {
            $conditions = [
                'or',
                ['>', new Expression('MAX(ancestors.[[dateStart]])'), $dateRef],
                ['<', new Expression('MIN(ancestors.[[dateEnd]])'), $dateRef],
            ];
            if (!$previewActive) {
                $conditions[] = ['=', new Expression('MIN(ancestors.[[active]])'), 0];
            }
            $query = $query->andHaving($conditions);
        } elseif (!$previewActive) {
            $query = $query->andHaving(['=', new Expression('MIN(ancestors.[[active]])'), 0]);
        }

        return $query;
    }
}
