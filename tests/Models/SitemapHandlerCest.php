<?php

declare(strict_types=1);

/**
 * SitemapHandlerCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Handlers\SitemapHandler;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\Type;
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
 * Tests for SitemapHandler — XML generation only, no routing.
 */
final class SitemapHandlerCest
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';

    private ConnectionInterface $db;
    private Migrator $migrator;
    private MigrationService $service;
    private SitemapHandler $handler;

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

        $this->handler = new SitemapHandler(new ResponseFactory(), new StreamFactory());
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
    // No content → 404
    // ========================================

    public function testEmptyReturns404(ModelsTester $I): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/sitemap.xml');
        $response = $this->handler->handle($request);

        $I->assertEquals(404, $response->getStatusCode());
    }

    // ========================================
    // Content with active slug → XML with loc
    // ========================================

    public function testContentAppearsInSitemap(ModelsTester $I): void
    {
        $this->createContentWithSlug('mon-article', 'Mon Article');

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/sitemap.xml');
        $response = $this->handler->handle($request);

        $I->assertEquals(200, $response->getStatusCode());
        $I->assertStringContainsString('application/xml', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $I->assertStringContainsString('<urlset', $body);
        $I->assertStringContainsString('mon-article', $body);
        $I->assertStringContainsString('<loc>', $body);
    }

    // ========================================
    // Tag with active slug → appears in XML
    // ========================================

    public function testTagAppearsInSitemap(ModelsTester $I): void
    {
        $this->createTagWithSlug('ma-categorie', 'Ma Catégorie');

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/sitemap.xml');
        $response = $this->handler->handle($request);

        $I->assertEquals(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $I->assertStringContainsString('ma-categorie', $body);
    }

    // ========================================
    // Inactive content → absent from sitemap
    // ========================================

    public function testInactiveContentAbsentFromSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath('inactive-page');
        $slug->setActive(true);
        $slug->save();

        $content = new Content();
        $content->setName('Inactive');
        $content->setSlugId($slug->getId());
        $content->setActive(false);
        $content->save();

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/sitemap.xml');
        $response = $this->handler->handle($request);

        // Either 404 (no content at all) or 200 without this slug
        $body = (string) $response->getBody();
        $I->assertStringNotContainsString('inactive-page', $body);
    }

    // ========================================
    // Valid XML structure
    // ========================================

    public function testValidXmlStructure(ModelsTester $I): void
    {
        $this->createContentWithSlug('page-a', 'Page A');
        $this->createContentWithSlug('page-b', 'Page B');

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/sitemap.xml');
        $response = $this->handler->handle($request);

        $body = (string) $response->getBody();
        $xml = simplexml_load_string($body);
        $I->assertNotFalse($xml, 'Sitemap must be valid XML');

        $urls = $xml->url;
        $I->assertGreaterThanOrEqual(2, count($urls));
    }

    // ========================================
    // Helpers
    // ========================================

    private function createContentWithSlug(string $path, string $name): Content
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath($path);
        $slug->setActive(true);
        $slug->save();

        $content = new Content();
        $content->setName($name);
        $content->setSlugId($slug->getId());
        $content->setActive(true);
        $content->save();

        return $content;
    }

    private function createTagWithSlug(string $path, string $name): Tag
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath($path);
        $slug->setActive(true);
        $slug->save();

        $tag = new Tag();
        $tag->setName($name);
        $tag->setSlugId($slug->getId());
        $tag->setActive(true);
        $tag->save();

        return $tag;
    }
}
