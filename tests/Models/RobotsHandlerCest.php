<?php

declare(strict_types=1);

/**
 * RobotsHandlerCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Handlers\RobotsHandler;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Dcore\Tests\Support\MysqlHelper;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use HttpSoft\Message\StreamFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * Tests for RobotsHandler — generation only, no routing.
 */
final class RobotsHandlerCest
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';
    private const ROBOTS_CONTENT = "User-agent: *\nDisallow: /admin\nDisallow: /api\nSitemap: https://localhost/sitemap.xml";

    private ConnectionInterface $db;
    private Migrator $migrator;
    private MigrationService $service;
    private RobotsHandler $handler;

    public function _before(ModelsTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();

        ConnectionProvider::set($this->db);

        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        $container = new Container($containerConfig);
        $injector = new Injector($container);

        $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        $this->service = new MigrationService($this->db, $injector, $this->migrator);
        $this->service->setSourceNamespaces([self::NAMESPACE]);

        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $migration = $this->service->makeRevertibleMigration($class);
            $this->migrator->down($migration);
        }

        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }

        $this->handler = new RobotsHandler(new ResponseFactory(), new StreamFactory());
    }

    public function _after(ModelsTester $I): void
    {
        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $migration = $this->service->makeRevertibleMigration($class);
            $this->migrator->down($migration);
        }
    }

    // ========================================
    // No GlobalXeo → 404
    // ========================================

    public function testNoGlobalXeoReturns404(ModelsTester $I): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/robots.txt');
        $response = $this->handler->handle($request);

        $I->assertEquals(404, $response->getStatusCode());
    }

    // ========================================
    // GlobalXeo inactive → 404
    // ========================================

    public function testInactiveGlobalXeoReturns404(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(false);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/robots.txt');
        $response = $this->handler->handle($request);

        $I->assertEquals(404, $response->getStatusCode());
    }

    // ========================================
    // Active GlobalXeo → 200 text/plain
    // ========================================

    public function testActiveGlobalXeoReturns200(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/robots.txt');
        $response = $this->handler->handle($request);

        $I->assertEquals(200, $response->getStatusCode());
        $I->assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
    }

    // ========================================
    // Body contains robots.txt directives
    // ========================================

    public function testBodyContainsDirectives(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/robots.txt');
        $response = $this->handler->handle($request);

        $body = (string) $response->getBody();
        $I->assertStringContainsString('User-agent:', $body);
        $I->assertStringContainsString('Disallow: /admin', $body);
        $I->assertStringContainsString('Disallow: /api', $body);
        $I->assertStringContainsString('Sitemap:', $body);
    }

    // ========================================
    // Host fallback to wildcard (id=1)
    // ========================================

    public function testUnknownHostFallsBackToWildcard(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://unknown.example.com/robots.txt');
        $response = $this->handler->handle($request);

        $I->assertEquals(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $I->assertStringContainsString('User-agent:', $body);
    }

    // ========================================
    // Helpers
    // ========================================

    private function createRobotsGlobalXeo(bool $active): void
    {
        // Find RawData elastic schema (builtin, inserted by migration)
        $schema = ElasticSchema::query()->andWhere(['name' => 'RawData'])->one();

        $globalXeo = new GlobalXeo();
        $globalXeo->setHostId(1);
        $globalXeo->setName('Robots');
        $globalXeo->setKind('Robots');
        $globalXeo->setElasticSchemaId($schema->getId());
        $globalXeo->setActive($active);
        $globalXeo->rawData = self::ROBOTS_CONTENT;
        $globalXeo->save();
    }
}
