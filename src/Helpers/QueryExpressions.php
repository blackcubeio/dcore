<?php

declare(strict_types=1);

/**
 * QueryExpressions.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Models\ElasticHazeltreeQuery;
use Blackcube\Dcore\Models\ElasticQuery;
use Blackcube\Dcore\Models\HazeltreeQuery;
use Blackcube\Dcore\Models\ScopableQuery;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\QueryExpressionBuilder;

/**
 * Single home of the query classes the query builder must know how to embed
 * as sub-queries (['in', 'id', $xxxQuery]). Defined once here so every
 * consumer (dboard, mcp, graphql, ssr, apps, test helpers) inherits the same
 * list: the dcore bootstrap registers it on the application connection, the
 * test MysqlHelpers call register() on theirs.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class QueryExpressions
{
    /**
     * @var array<class-string, class-string>
     */
    public const BUILDERS = [
        ActiveQuery::class => QueryExpressionBuilder::class,
        ScopableQuery::class => QueryExpressionBuilder::class,
        ElasticQuery::class => QueryExpressionBuilder::class,
        ElasticHazeltreeQuery::class => QueryExpressionBuilder::class,
        HazeltreeQuery::class => QueryExpressionBuilder::class,
    ];

    public static function register(ConnectionInterface $connection): void
    {
        $connection->getQueryBuilder()->setExpressionBuilders(self::BUILDERS);
    }
}
