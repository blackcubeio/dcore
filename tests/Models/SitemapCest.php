<?php

declare(strict_types=1);

/**
 * SitemapCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class SitemapCest
{
    use DatabaseCestTrait;

    private function createSlug(): Slug
    {
        $slug = new Slug();
        $slug->setPath('/sitemap-test-' . uniqid());
        $slug->save();
        return $slug;
    }

    public function testInsert(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());

        $sitemap->save();

        $I->assertNotNull($sitemap->getId());
        $I->assertEquals('daily', $sitemap->getFrequency());
        $I->assertEquals(0.5, $sitemap->getPriority());
        $I->assertNotNull($sitemap->getDateCreate());
    }

    public function testCustomValues(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->setFrequency('weekly');
        $sitemap->setPriority(0.8);
        $sitemap->setActive(true);
        $sitemap->save();

        $found = Sitemap::query()->andWhere(['id' => $sitemap->getId()])->one();

        $I->assertEquals('weekly', $found->getFrequency());
        $I->assertEquals(0.8, $found->getPriority());
        $I->assertTrue($found->isActive());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->save();

        $sitemap->setFrequency('monthly');
        $sitemap->setPriority(1.0);
        $sitemap->save();

        $I->assertEquals('monthly', $sitemap->getFrequency());
        $I->assertEquals(1.0, $sitemap->getPriority());
        $I->assertNotNull($sitemap->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->save();

        $id = $sitemap->getId();
        $sitemap->delete();

        $found = Sitemap::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }
}
