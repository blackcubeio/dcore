<?php

declare(strict_types=1);

/**
 * QueryCacheCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Entities\XeoBloc as EntityXeoBloc;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Injector\Injector;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependency\Dependency;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Reproduces the intra-request stale read: an Entity query (cache scope, ttl
 * 3600) primed before a write keeps serving the pre-write result within the
 * same request, because the per-table ReusableDependency (MAX(dateUpdate)) is
 * evaluated once per request and reused. Seen live on the xeo refresh
 * (dboard, session 2026-07-01): the freshly created xeo blocs read back as 0
 * until the next request.
 */
final class QueryCacheCest
{
    use DatabaseCestTrait;

    private function bindCache(): void
    {
        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
                CacheInterface::class => new Cache(new ArrayCache()),
            ]);
        Injector::init(new Container($containerConfig));
    }

    private function unbindCache(): void
    {
        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        Injector::init(new Container($containerConfig));
        Dependency::resetReusableData();
    }

    public function entityQuerySeesTheWritesOfTheSameRequest(ModelsTester $I): void
    {
        $I->wantTo('check a cached Entity query re-read after a write within the same request sees the written row');

        $this->bindCache();
        try {
            $slug = new Slug();
            $slug->setPath('/xeo-cache-'.uniqid());
            $slug->save();

            $xeo = new Xeo();
            $xeo->setSlugId($slug->getId());
            $xeo->save();
            $xeoId = $xeo->getId();

            $schema = new ElasticSchema();
            $schema->setName('xeo-cache-schema-'.uniqid());
            $schema->setSchema('{"type":"object"}');
            $schema->save();

            $bloc = new Bloc();
            $bloc->setElasticSchemaId($schema->getId());
            $bloc->save();

            $primed = EntityXeoBloc::query()->andWhere(['xeoId' => $xeoId])->all();
            $I->assertCount(0, $primed, 'no pivot row before the write');

            $xeo->attachBloc($bloc, 0);

            $reRead = EntityXeoBloc::query()->andWhere(['xeoId' => $xeoId])->all();
            $I->assertCount(1, $reRead, 'the pivot row written within the same request must be visible');
        } finally {
            $this->unbindCache();
        }
    }
}
