<?php

declare(strict_types=1);

/**
 * ReusableDependency.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Injector\Injector;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\Dependency;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;

/**
 * MAX(dateUpdate) dependency on one or more tables.
 *
 * Fully serializable (no closures, no model classes) — stores raw table names.
 * Uses direct DB queries to avoid Entity::query() recursion.
 * Always reusable (one evaluation per request).
 */
final class ReusableDependency extends Dependency
{
    /** @var string[] */
    private array $tableNames;

    /**
     * @param string $hashKey Unique key for reusable hash (e.g. 'dcore.dep.contents')
     * @param string ...$tableNames Table names (e.g. '{{%contents}}', '{{%blocs}}')
     */
    public function __construct(private readonly string $hashKey, string ...$tableNames)
    {
        $this->tableNames = $tableNames;
        $this->isReusable = true;
    }

    protected function generateDependencyData(CacheInterface $cache): mixed
    {
        $db = Injector::get(ConnectionInterface::class);

        if (count($this->tableNames) === 1) {
            return (new Query($db))
                ->select(new Expression('MAX([[dateUpdate]])'))
                ->from($this->tableNames[0])
                ->scalar();
        }

        $maxUpdate = new Expression('MAX([[dateUpdate]]) as [[date]]');
        $first = (new Query($db))->select($maxUpdate)->from($this->tableNames[0]);

        for ($i = 1, $count = count($this->tableNames); $i < $count; $i++) {
            $first = $first->union(
                (new Query($db))->select($maxUpdate)->from($this->tableNames[$i])
            );
        }

        return (new Query($db))
            ->select(new Expression('MAX([[date]])'))
            ->from(['sub' => $first])
            ->scalar();
    }

    protected function generateReusableHash(): string
    {
        return $this->hashKey;
    }
}
