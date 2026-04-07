<?php

declare(strict_types=1);

/**
 * LanguageCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class LanguageCest
{
    use DatabaseCestTrait;

    public function testInsert(ModelsTester $I): void
    {
        $language = new Language();
        $language->setId('pt');
        $language->setName('Português');
        $language->setMain(true);
        $language->setActive(true);

        $language->save();

        $I->assertEquals('pt', $language->getId());
        $I->assertNotNull($language->getDateCreate(), 'dateCreate should be set automatically');
        $I->assertNotNull($language->getDateUpdate(), 'dateUpdate should be set automatically on insert');
    }

    public function testUpdate(ModelsTester $I): void
    {
        $language = new Language();
        $language->setId('nl');
        $language->setName('Nederlands');
        $language->setMain(false);
        $language->setActive(true);
        $language->save();

        $language->setName('Dutch');
        $language->save();

        $I->assertEquals('Dutch', $language->getName());
        $I->assertNotNull($language->getDateUpdate(), 'dateUpdate should be set on update');
    }

    public function testSearch(ModelsTester $I): void
    {
        // French is inserted by migration
        $french = Language::query()->andWhere(['id' => 'fr'])->one();

        $I->assertNotNull($french);
        $I->assertEquals('Français', $french->getName());
        $I->assertTrue($french->isActive());
        $I->assertTrue($french->isMain());
    }

    public function testDelete(ModelsTester $I): void
    {
        $language = new Language();
        $language->setId('ja');
        $language->setName('Japanese');
        $language->save();

        $language->delete();

        $found = Language::query()->andWhere(['id' => 'ja'])->one();
        $I->assertNull($found);
    }

    public function testQuery(ModelsTester $I): void
    {
        // Count active languages before any test operations
        $countBefore = Language::query()
            ->andWhere(['active' => true])
            ->count();

        // Verify French is in the active languages
        $french = Language::query()
            ->andWhere(['id' => 'fr'])
            ->andWhere(['active' => true])
            ->one();

        $I->assertNotNull($french, 'French should be active');
        $I->assertEquals('Français', $french->getName());

        // Verify the query functionality works
        $I->assertGreaterThanOrEqual(1, $countBefore, 'At least French should be active');
    }
}
