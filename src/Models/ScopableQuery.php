<?php

declare(strict_types=1);

/**
 * ScopableQuery.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\BatchedEachQueryTrait;
use Blackcube\ActiveRecord\BuildFormulaeTrait;
use Blackcube\ActiveRecord\FormulaeExpressionInterface;
use Blackcube\ActiveRecord\QualifyColumnTrait;
use Blackcube\ActiveRecord\ScopableTrait;
use Blackcube\ActiveRecord\ScopableQueryInterface;
use Blackcube\Dcore\Data\EmptyDataReader;
use Blackcube\Dcore\Helpers\QueryCache;
use Blackcube\Dcore\Models\Scopes\ActiveScope;
use Blackcube\Dcore\Models\Scopes\AtDateScope;
use Blackcube\Dcore\Models\Scopes\AvailableScope;
use Blackcube\Dcore\Models\Scopes\CacheScope;
use Blackcube\Dcore\Models\Scopes\LanguageScope;
use Blackcube\Dcore\Models\Scopes\PublishableScope;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeInterface;
use Blackcube\Injector\Injector;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\Dependency;
use Yiisoft\Db\QueryBuilder\QueryBuilderInterface;
use Yiisoft\Db\Query\DataReaderInterface;
use Yiisoft\Db\Query\QueryInterface;

/**
 * Base scoped query with column qualification, caching, and dynamic scopes.
 *
 * Subclasses add elastic and/or hazeltree capabilities:
 *   ElasticQuery            — JSON virtual columns
 *   HazeltreeQuery          — tree navigation
 *   ElasticHazeltreeQuery   — both
 *
 * Registered scopes: active, language, atDate, available, publishable, cache.
 */
class ScopableQuery extends ActiveQuery implements ScopableQueryInterface, FormulaeExpressionInterface
{
    use ScopableTrait;
    use QualifyColumnTrait;
    use BuildFormulaeTrait;
    use BatchedEachQueryTrait {
        BatchedEachQueryTrait::each as batchedEach;
    }

    /**
     * Create a query instance for the current class and register dcore scopes.
     */
    public static function create(ActiveRecordInterface|string $modelClass): static
    {
        $query = new static($modelClass);
        $query->registerDcoreScopes();
        return $query;
    }

    /**
     * Auto-detect model capabilities and create the appropriate query subclass.
     */
    public static function createFor(string|ActiveRecordInterface $modelClass): self
    {
        $className = is_string($modelClass) === true ? $modelClass : $modelClass::class;
        $implements = class_implements($className);

        $hasElastic = isset($implements[ElasticInterface::class]) === true;
        $hasHazeltree = isset($implements[HazeltreeInterface::class]) === true;

        if ($hasElastic && $hasHazeltree) {
            return ElasticHazeltreeQuery::create($className);
        }
        if ($hasElastic === true) {
            return ElasticQuery::create($className);
        }
        if ($hasHazeltree === true) {
            return HazeltreeQuery::create($className);
        }
        return self::create($className);
    }

    private function registerDcoreScopes(): static
    {
        $this->addScope(ActiveScope::class)
             ->addScope(LanguageScope::class)
             ->addScope(AtDateScope::class)
             ->addScope(AvailableScope::class)
             ->addScope(PublishableScope::class)
             ->addScope(CacheScope::class);
        return $this;
    }

    public function prepare(QueryBuilderInterface $builder): QueryInterface
    {
        if ($this->getState('deferPublishable', false)) {
            $this->publishable();
            $this->setState('deferPublishable', false);
        }
        return parent::prepare($builder);
    }

    private function getCache(): ?CacheInterface
    {
        return Injector::has(CacheInterface::class)
            ? Injector::get(CacheInterface::class)
            : null;
    }

    private function cacheKey(string $method, string $rawSql): string
    {
        $this->ensureTableName();
        $table = str_replace(['{{%', '}}'], '', $this->tableName);

        return 'dcore.'.$table.'.'.sha1($method.':'.$rawSql);
    }

    private function dependency(): Dependency
    {
        $dependency = $this->getState('cacheDependency');
        if ($dependency instanceof Dependency) {
            return $dependency;
        }

        $this->ensureTableName();

        return QueryCache::forTable($this->tableName);
    }

    public function one(): array|ActiveRecordInterface|null
    {
        $cache = $this->getState('cacheEnabled', false) ? $this->getCache() : null;

        if ($cache === null) {
            return parent::one();
        }

        if ($this->shouldEmulateExecution()) {
            return null;
        }

        $command = $this->createCommand();
        $row = $cache->getOrSet(
            $this->cacheKey('one', $command->getRawSql()),
            fn () => $command->queryOne(),
            $this->getState('cacheTtl'),
            $this->dependency(),
        );

        return $row !== null ? $this->populate([$row])[0] : null;
    }

    public function all(): array
    {
        $cache = $this->getState('cacheEnabled', false) ? $this->getCache() : null;

        if ($cache === null) {
            return parent::all();
        }

        if ($this->shouldEmulateExecution()) {
            return [];
        }

        $command = $this->createCommand();
        $rows = $cache->getOrSet(
            $this->cacheKey('all', $command->getRawSql()),
            fn () => $command->queryAll(),
            $this->getState('cacheTtl'),
            $this->dependency(),
        );

        return $this->index($rows);
    }

    public function each(int $batchSize = 100): DataReaderInterface
    {
        // Building the command runs prepare(), which lets yii flag the query for
        // emulation when a via relation has an empty pivot (it adds WHERE 1=0 and
        // calls emulateExecution()). all() and one() already honor that flag;
        // each() does not, so we replicate the guard here to avoid running the
        // useless WHERE 1=0 statement.
        $this->createCommand();
        if ($this->shouldEmulateExecution() === true) {
            return new EmptyDataReader();
        }

        return $this->batchedEach($batchSize);
    }

    public function scalar(): bool|int|string|float|null
    {
        $cache = $this->getState('cacheEnabled', false) ? $this->getCache() : null;

        if ($cache === null) {
            return parent::scalar();
        }

        if ($this->shouldEmulateExecution()) {
            return null;
        }

        $command = $this->createCommand();
        return $cache->getOrSet(
            $this->cacheKey('scalar', $command->getRawSql()),
            fn () => $command->queryScalar(),
            $this->getState('cacheTtl'),
            $this->dependency(),
        );
    }
}
