<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Services\JsonLdBuilder;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for JsonLdBuilder — global Organization and WebSite nodes.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class JsonLdBuilderCest
{
    use DatabaseCestTrait;

    /**
     * The builder reads the global entries once per process: both entries are
     * written before the first build, and checked in one test.
     */
    public function testOrganizationAndWebSiteCarryNameAndUrl(ModelsTester $I): void
    {
        $this->createOrganizationGlobalXeo();
        $this->createWebSiteGlobalXeo();
        $slug = $this->createSlug();
        $this->createActiveXeo($slug->getId());

        $jsonLds = (new JsonLdBuilder())->build($slug->getId(), 'example.test');

        $I->assertNotEmpty($jsonLds);
        $organization = null;
        $webSite = null;
        foreach ($jsonLds[0]['@graph'] as $node) {
            if ($node['@type'] === 'Organization') {
                $organization = $node;
            }
            if ($node['@type'] === 'WebSite') {
                $webSite = $node;
            }
        }
        $I->assertNotNull($organization);
        $I->assertSame('Blackcube', $organization['name']);
        $I->assertSame('https://example.test', $organization['url']);
        $I->assertNotNull($webSite);
        $I->assertSame('Blackcube', $webSite['name']);
        $I->assertSame('https://example.test', $webSite['url']);
    }

    private function createWebSiteGlobalXeo(): void
    {
        $schema = ElasticSchema::query()->andWhere(['name' => 'WebSite'])->one();

        $globalXeo = new GlobalXeo();
        $globalXeo->setHostId(1);
        $globalXeo->setName('Blackcube');
        $globalXeo->setKind('WebSite');
        $globalXeo->setElasticSchemaId($schema->getId());
        $globalXeo->setActive(true);
        $globalXeo->save();
    }

    private function createOrganizationGlobalXeo(): void
    {
        $schema = ElasticSchema::query()->andWhere(['name' => 'Organization'])->one();

        $globalXeo = new GlobalXeo();
        $globalXeo->setHostId(1);
        $globalXeo->setName('Blackcube');
        $globalXeo->setKind('Organization');
        $globalXeo->setElasticSchemaId($schema->getId());
        $globalXeo->setActive(true);
        $globalXeo->organizationType = 'Organization';
        $globalXeo->save();
    }

    private function createSlug(): Slug
    {
        $slug = new Slug();
        $slug->setPath('/jsonld-test-' . uniqid());
        $slug->save();
        return $slug;
    }

    private function createActiveXeo(int $slugId): void
    {
        $xeo = new Xeo();
        $xeo->setSlugId($slugId);
        $xeo->setTitle('JSON-LD');
        $xeo->setActive(true);
        $xeo->save();
    }
}
