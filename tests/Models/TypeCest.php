<?php

declare(strict_types=1);

/**
 * TypeCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class TypeCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $type = new Type();
        $type->setName('page');
        $type->setHandler('site/view');

        $type->save();

        $I->assertNotNull($type->getId());
        $I->assertTrue($type->isContentAllowed());
        $I->assertTrue($type->isTagAllowed());
        $I->assertNotNull($type->getDateCreate());
    }

    public function testUpdate(ModelsTester $I): void
    {
        $type = new Type();
        $type->setName('article');
        $type->save();

        $type->setContentAllowed(false);
        $type->setTagAllowed(false);
        $type->save();

        $I->assertFalse($type->isContentAllowed());
        $I->assertFalse($type->isTagAllowed());
        $I->assertNotNull($type->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $type = new Type();
        $type->setName('deletable');
        $type->save();

        $id = $type->getId();
        $type->delete();

        $found = Type::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }
}
