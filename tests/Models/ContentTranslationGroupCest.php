<?php

declare(strict_types=1);

/**
 * ContentTranslationGroupCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\ContentTranslationGroup;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class ContentTranslationGroupCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $group = new ContentTranslationGroup();
        $group->save();

        $I->assertNotNull($group->getId());
        $I->assertNotNull($group->getDateCreate());
        $I->assertNotNull($group->getDateUpdate(), 'dateUpdate should be set automatically on insert');
    }

    public function testUpdate(ModelsTester $I): void
    {
        $group = new ContentTranslationGroup();
        $group->save();

        $id = $group->getId();

        // Force update by touching the record
        $group->save();

        $reloaded = ContentTranslationGroup::query()->andWhere(['id' => $id])->one();
        $I->assertNotNull($reloaded->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $group = new ContentTranslationGroup();
        $group->save();

        $id = $group->getId();
        $group->delete();

        $found = ContentTranslationGroup::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }
}
