<?php

declare(strict_types=1);

/**
 * ContentTranslationCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Models\ContentTranslationGroup;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for Content translation management: linkTranslation(), unlinkTranslation(), getTranslationsQuery().
 */
final class ContentTranslationCest
{
    use DatabaseCestTrait;

    private Language $langFr;
    private Language $langEn;
    private Language $langEs;

    private Content $contentFr1;
    private Content $contentFr2;
    private Content $contentEn;
    private Content $contentEs;

    private function ensureTestData(): void
    {
        $this->langFr = Language::query()->andWhere(['id' => 'fr'])->one();
        if ($this->langFr === null) {
            $this->langFr = new Language();
            $this->langFr->setId('fr');
            $this->langFr->save();
        }

        $this->langEn = Language::query()->andWhere(['id' => 'en'])->one();
        if ($this->langEn === null) {
            $this->langEn = new Language();
            $this->langEn->setId('en');
            $this->langEn->save();
        }

        $this->langEs = Language::query()->andWhere(['id' => 'es'])->one();
        if ($this->langEs === null) {
            $this->langEs = new Language();
            $this->langEs->setId('es');
            $this->langEs->save();
        }

        $this->contentFr1 = new Content();
        $this->contentFr1->setLanguageId('fr');
        $this->contentFr1->setName('FR 1 '.uniqid());
        $this->contentFr1->save();

        $this->contentFr2 = new Content();
        $this->contentFr2->setLanguageId('fr');
        $this->contentFr2->setName('FR 2 '.uniqid());
        $this->contentFr2->save();

        $this->contentEn = new Content();
        $this->contentEn->setLanguageId('en');
        $this->contentEn->setName('EN '.uniqid());
        $this->contentEn->save();

        $this->contentEs = new Content();
        $this->contentEs->setLanguageId('es');
        $this->contentEs->setName('ES '.uniqid());
        $this->contentEs->save();
    }


    public function testLinkTwoOrphans(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs);

        $this->contentEn->refresh();
        $this->contentEs->refresh();

        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertEquals($this->contentEn->getTranslationGroupId(), $this->contentEs->getTranslationGroupId());

        $translations = $this->contentEn->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
        $I->assertEquals('es', $translations[0]->getLanguageId());

        $translations = $this->contentEs->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
        $I->assertEquals('en', $translations[0]->getLanguageId());
    }


    public function testLinkToExistingGroup(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentEn->refresh();
        $this->contentEs->refresh();

        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();

        $I->assertEquals($this->contentFr1->getTranslationGroupId(), $this->contentEn->getTranslationGroupId());

        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(2, $translations);

        $translationEn = $this->contentFr1->getTranslationsQuery()->language(languageId: 'en')->one();
        $I->assertNotNull($translationEn);
        $I->assertEquals('en', $translationEn->getLanguageId());
    }


    public function testLinkSameLanguageThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentFr1->linkTranslation($this->contentEn);

        $I->expectThrowable(\LogicException::class, function () {
            $this->contentFr2->linkTranslation($this->contentEn);
        });
    }


    public function testUnlinkById(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();

        $groupId = $this->contentFr1->getTranslationGroupId();

        $this->contentFr1->unlinkTranslation($this->contentEs->getId());

        $this->contentEs->refresh();
        $I->assertNull($this->contentEs->getTranslationGroupId());

        $this->contentFr1->refresh();
        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
    }


    public function testUnlinkByLanguageIdGroupDeleted(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        $this->contentFr1->unlinkTranslation('en');

        $this->contentEn->refresh();
        $I->assertNull($this->contentEn->getTranslationGroupId());

        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        $group = ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one();
        $I->assertNull($group);
    }


    public function testRelink(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->linkTranslation($this->contentEs);

        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();
        $I->assertNotNull($groupId);
        $I->assertCount(2, $this->contentFr1->getTranslationsQuery()->all());
    }


    public function testUnlinkByLanguageObject(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->linkTranslation($this->contentEs);
        $this->contentFr1->refresh();

        $this->contentFr1->unlinkTranslation($this->langEs);

        $this->contentEs->refresh();
        $I->assertNull($this->contentEs->getTranslationGroupId());

        $this->contentFr1->refresh();
        $I->assertCount(1, $this->contentFr1->getTranslationsQuery()->all());
    }


    public function testUnlinkByContentObjectGroupDeleted(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        $this->contentFr1->unlinkTranslation($this->contentEn);

        $this->contentEn->refresh();
        $I->assertNull($this->contentEn->getTranslationGroupId());

        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        $group = ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one();
        $I->assertNull($group);
    }


    public function testUnlinkSelf(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->linkTranslation($this->contentEs);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        $this->contentFr1->unlinkTranslation();

        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        $this->contentEn->refresh();
        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertCount(1, $this->contentEn->getTranslationsQuery()->all());
    }


    public function testGetTranslationsQueryEmptyWhenNoGroup(ModelsTester $I): void
    {
        $this->ensureTestData();
        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(0, $translations);
    }


    public function testLinkWithContentId(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs->getId());

        $this->contentEn->refresh();
        $this->contentEs->refresh();

        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertEquals($this->contentEn->getTranslationGroupId(), $this->contentEs->getTranslationGroupId());
    }


    public function testLinkInvalidContentIdThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        $I->expectThrowable(\InvalidArgumentException::class, function () {
            $this->contentEn->linkTranslation(999999);
        });
    }


    public function testLinkBothWithGroupsThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        $langDe = Language::query()->andWhere(['id' => 'de'])->one();
        if ($langDe === null) {
            $langDe = new Language();
            $langDe->setId('de');
            $langDe->save();
        }

        $contentDe = new Content();
        $contentDe->setLanguageId('de');
        $contentDe->setName('DE');
        $contentDe->save();

        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentEs->linkTranslation($contentDe);

        $this->contentFr1->refresh();
        $this->contentEs->refresh();

        $I->expectThrowable(\LogicException::class, function () {
            $this->contentFr1->linkTranslation($this->contentEs);
        });
    }
}
