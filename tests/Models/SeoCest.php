<?php

declare(strict_types=1);

/**
 * SeoCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class SeoCest
{
    use DatabaseCestTrait;

    private function createSlug(): Slug
    {
        $slug = new Slug();
        $slug->setPath('/seo-test-' . uniqid());
        $slug->save();
        return $slug;
    }

    public function testInsert(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $seo = new Xeo();
        $seo->setSlugId($slug->getId());
        $seo->setTitle('Page Title');
        $seo->setDescription('Page description');

        $seo->save();

        $I->assertNotNull($seo->getId());
        $I->assertNotNull($seo->getDateCreate());
    }

    public function testFullSeo(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $seo = new Xeo();
        $seo->setSlugId($slug->getId());
        $seo->setTitle('Full SEO');
        $seo->setImage('/images/og.jpg');
        $seo->setDescription('Full description');
        $seo->setNoindex(true);
        $seo->setNofollow(true);
        $seo->setOg(true);
        $seo->setOgType('article');
        $seo->setTwitter(true);
        $seo->setTwitterCard('summary_large_image');
        $seo->setActive(true);
        $seo->save();

        $found = Xeo::query()->andWhere(['id' => $seo->getId()])->one();

        $I->assertTrue($found->isNoindex());
        $I->assertTrue($found->isNofollow());
        $I->assertTrue($found->isOg());
        $I->assertEquals('article', $found->getOgType());
        $I->assertTrue($found->isTwitter());
        $I->assertEquals('summary_large_image', $found->getTwitterCard());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $seo = new Xeo();
        $seo->setSlugId($slug->getId());
        $seo->setTitle('Original');
        $seo->save();

        $seo->setTitle('Updated');
        $seo->save();

        $I->assertEquals('Updated', $seo->getTitle());
        $I->assertNotNull($seo->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $slug = $this->createSlug();

        $seo = new Xeo();
        $seo->setSlugId($slug->getId());
        $seo->save();

        $id = $seo->getId();
        $seo->delete();

        $found = Xeo::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }
}
