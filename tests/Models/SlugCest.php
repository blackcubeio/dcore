<?php

declare(strict_types=1);

/**
 * SlugCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class SlugCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/test-page');
        $slug->setActive(true);

        $slug->save();

        $I->assertNotNull($slug->getId());
        $I->assertEquals(1, $slug->getHostId(), 'Default hostId should be 1 (wildcard)');
        $I->assertNotNull($slug->getDateCreate());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/original');
        $slug->save();

        $slug->setPath('/updated');
        $slug->save();

        $I->assertEquals('/updated', $slug->getPath());
        $I->assertNotNull($slug->getDateUpdate());
    }

    public function testRedirect(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/old-page');
        $slug->setTargetUrl('/new-page');
        $slug->setHttpCode(301);
        $slug->save();

        $found = Slug::query()->andWhere(['id' => $slug->getId()])->one();

        $I->assertEquals('/new-page', $found->getTargetUrl());
        $I->assertEquals(301, $found->getHttpCode());
    }

    public function testDelete(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/to-delete');
        $slug->save();

        $id = $slug->getId();
        $slug->delete();

        $found = Slug::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }

    public function testUniqueHostPath(ModelsTester $I): void
    {
        $slug1 = new Slug();
        $slug1->setPath('/unique-path');
        $slug1->save();

        // Same path with same hostId should fail
        $slug2 = new Slug();
        $slug2->setPath('/unique-path');

        $I->expectThrowable(\Throwable::class, function () use ($slug2) {
            $slug2->save();
        });
    }
}
