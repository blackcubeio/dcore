<?php

declare(strict_types=1);

/**
 * RobotsServiceCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Services\Xeo\RobotsService;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for RobotsService — generation only.
 */
final class RobotsHandlerCest
{
    use DatabaseCestTrait;

    private const ROBOTS_CONTENT = "User-agent: *\nDisallow: /admin\nDisallow: /api\nSitemap: https://localhost/sitemap.xml";

    private ?RobotsService $robotsService = null;

    private function ensureService(): RobotsService
    {
        return $this->robotsService ??= new RobotsService();
    }

    // ========================================
    // No GlobalXeo → null
    // ========================================

    public function testNoGlobalXeoReturnsNull(ModelsTester $I): void
    {
        $result = $this->ensureService()->generate('localhost');
        $I->assertNull($result);
    }

    // ========================================
    // GlobalXeo inactive → null
    // ========================================

    public function testInactiveGlobalXeoReturnsNull(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(false);

        $result = $this->ensureService()->generate('localhost');
        $I->assertNull($result);
    }

    // ========================================
    // Active GlobalXeo → string content
    // ========================================

    public function testActiveGlobalXeoReturnsContent(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $result = $this->ensureService()->generate('localhost');
        $I->assertNotNull($result);
        $I->assertIsString($result);
    }

    // ========================================
    // Body contains robots.txt directives
    // ========================================

    public function testBodyContainsDirectives(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $result = $this->ensureService()->generate('localhost');

        $I->assertNotNull($result);
        $I->assertStringContainsString('User-agent:', $result);
        $I->assertStringContainsString('Disallow: /admin', $result);
        $I->assertStringContainsString('Disallow: /api', $result);
        $I->assertStringContainsString('Sitemap:', $result);
    }

    // ========================================
    // Host fallback to wildcard (id=1)
    // ========================================

    public function testUnknownHostFallsBackToWildcard(ModelsTester $I): void
    {
        $this->createRobotsGlobalXeo(true);

        $result = $this->ensureService()->generate('unknown.example.com');

        $I->assertNotNull($result);
        $I->assertStringContainsString('User-agent:', $result);
    }

    // ========================================
    // Helpers
    // ========================================

    private function createRobotsGlobalXeo(bool $active): void
    {
        $schema = ElasticSchema::query()->andWhere(['name' => 'RawData'])->one();

        $globalXeo = GlobalXeo::query()
            ->andWhere(['hostId' => 1, 'name' => 'Robots'])
            ->one();

        if ($globalXeo === null) {
            $globalXeo = new GlobalXeo();
            $globalXeo->setHostId(1);
            $globalXeo->setName('Robots');
            $globalXeo->setKind('Robots');
            $globalXeo->setElasticSchemaId($schema->getId());
        }

        $globalXeo->setActive($active);
        $globalXeo->rawData = self::ROBOTS_CONTENT;
        $globalXeo->save();
    }
}
