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
 * Tests for JsonLdBuilder — global Organization node.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class JsonLdBuilderCest
{
    use DatabaseCestTrait;

    public function testOrganizationCarriesNameAndUrl(ModelsTester $I): void
    {
        $this->createOrganizationGlobalXeo();
        $slug = $this->createSlug();
        $this->createActiveXeo($slug->getId());

        $jsonLds = (new JsonLdBuilder())->build($slug->getId(), 'example.test');

        $I->assertNotEmpty($jsonLds);
        $organization = null;
        foreach ($jsonLds[0]['@graph'] as $node) {
            if ($node['@type'] === 'Organization') {
                $organization = $node;
            }
        }
        $I->assertNotNull($organization);
        $I->assertSame('Blackcube', $organization['name']);
        $I->assertSame('https://example.test', $organization['url']);
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
