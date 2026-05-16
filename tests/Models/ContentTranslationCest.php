<?php

declare(strict_types=1);

/**
 * ContentTranslationCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
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
        $this->contentFr1->setName('FR 1 ' . uniqid());
        $this->contentFr1->save();

        $this->contentFr2 = new Content();
        $this->contentFr2->setLanguageId('fr');
        $this->contentFr2->setName('FR 2 ' . uniqid());
        $this->contentFr2->save();

        $this->contentEn = new Content();
        $this->contentEn->setLanguageId('en');
        $this->contentEn->setName('EN ' . uniqid());
        $this->contentEn->save();

        $this->contentEs = new Content();
        $this->contentEs->setLanguageId('es');
        $this->contentEs->setName('ES ' . uniqid());
        $this->contentEs->save();
    }

    // ========================================
    // Test 1 : Link EN + ES (deux orphelins)
    // ========================================

    public function testLinkTwoOrphans(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs);

        // Refresh to get updated values
        $this->contentEn->refresh();
        $this->contentEs->refresh();

        // Group created, both have same groupId
        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertEquals($this->contentEn->getTranslationGroupId(), $this->contentEs->getTranslationGroupId());

        // getTranslationsQuery() works
        $translations = $this->contentEn->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
        $I->assertEquals('es', $translations[0]->getLanguageId());

        $translations = $this->contentEs->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
        $I->assertEquals('en', $translations[0]->getLanguageId());
    }

    // ========================================
    // Test 2 : Link FR1 to existing group
    // ========================================

    public function testLinkToExistingGroup(ModelsTester $I): void
    {
        $this->ensureTestData();
        // First create group EN/ES
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentEn->refresh();
        $this->contentEs->refresh();

        // FR1 joins the group
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();

        // Same groupId for all 3
        $I->assertEquals($this->contentFr1->getTranslationGroupId(), $this->contentEn->getTranslationGroupId());

        // getTranslationsQuery() returns 2 results
        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(2, $translations);

        // Scope language() works
        $translationEn = $this->contentFr1->getTranslationsQuery()->language(languageId: 'en')->one();
        $I->assertNotNull($translationEn);
        $I->assertEquals('en', $translationEn->getLanguageId());
    }

    // ========================================
    // Test 3 : Link FR2 to group with FR1 -> Exception
    // ========================================

    public function testLinkSameLanguageThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group EN/ES/FR1
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentFr1->linkTranslation($this->contentEn);

        // FR2 = same language as FR1 already in group
        $I->expectThrowable(\LogicException::class, function () {
            $this->contentFr2->linkTranslation($this->contentEn);
        });
    }

    // ========================================
    // Test 4 : Unlink by Content ID
    // ========================================

    public function testUnlinkById(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group FR1/EN/ES
        $this->contentEn->linkTranslation($this->contentEs);
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();

        $groupId = $this->contentFr1->getTranslationGroupId();

        // Unlink ES by id
        $this->contentFr1->unlinkTranslation($this->contentEs->getId());

        // ES has no group
        $this->contentEs->refresh();
        $I->assertNull($this->contentEs->getTranslationGroupId());

        // Group still exists (FR1 + EN)
        $this->contentFr1->refresh();
        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(1, $translations);
    }

    // ========================================
    // Test 5 : Unlink by languageId -> group deleted
    // ========================================

    public function testUnlinkByLanguageIdGroupDeleted(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group FR1/EN
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        // Unlink EN by language
        $this->contentFr1->unlinkTranslation('en');

        // EN has no group
        $this->contentEn->refresh();
        $I->assertNull($this->contentEn->getTranslationGroupId());

        // FR1 has no group (<=1 member -> deletion)
        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        // Group deleted
        $group = ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one();
        $I->assertNull($group);
    }

    // ========================================
    // Test 6 : Re-link FR1 + EN + ES
    // ========================================

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

    // ========================================
    // Test 7 : Unlink by Language object
    // ========================================

    public function testUnlinkByLanguageObject(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group FR1/EN/ES
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->linkTranslation($this->contentEs);
        $this->contentFr1->refresh();

        // Unlink via Language object
        $this->contentFr1->unlinkTranslation($this->langEs);

        $this->contentEs->refresh();
        $I->assertNull($this->contentEs->getTranslationGroupId());

        // Group still exists (FR1 + EN)
        $this->contentFr1->refresh();
        $I->assertCount(1, $this->contentFr1->getTranslationsQuery()->all());
    }

    // ========================================
    // Test 8 : Unlink by Content object -> group deleted
    // ========================================

    public function testUnlinkByContentObjectGroupDeleted(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group FR1/EN
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        // Unlink via Content object
        $this->contentFr1->unlinkTranslation($this->contentEn);

        $this->contentEn->refresh();
        $I->assertNull($this->contentEn->getTranslationGroupId());

        // FR1 alone -> group deleted
        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        $group = ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one();
        $I->assertNull($group);
    }

    // ========================================
    // Test 9 : Unlink self (sans parametre)
    // ========================================

    public function testUnlinkSelf(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create group FR1/EN/ES
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentFr1->linkTranslation($this->contentEs);
        $this->contentFr1->refresh();
        $groupId = $this->contentFr1->getTranslationGroupId();

        // FR1 removes itself
        $this->contentFr1->unlinkTranslation();

        $this->contentFr1->refresh();
        $I->assertNull($this->contentFr1->getTranslationGroupId());

        // Group still exists (EN + ES)
        $this->contentEn->refresh();
        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertCount(1, $this->contentEn->getTranslationsQuery()->all());
    }

    // ========================================
    // Test 10 : getTranslationsQuery() empty when no group
    // ========================================

    public function testGetTranslationsQueryEmptyWhenNoGroup(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Content without group
        $translations = $this->contentFr1->getTranslationsQuery()->all();
        $I->assertCount(0, $translations);
    }

    // ========================================
    // Test 11 : Link with Content ID instead of object
    // ========================================

    public function testLinkWithContentId(ModelsTester $I): void
    {
        $this->ensureTestData();
        $this->contentEn->linkTranslation($this->contentEs->getId());

        $this->contentEn->refresh();
        $this->contentEs->refresh();

        $I->assertNotNull($this->contentEn->getTranslationGroupId());
        $I->assertEquals($this->contentEn->getTranslationGroupId(), $this->contentEs->getTranslationGroupId());
    }

    // ========================================
    // Test 12 : Link invalid Content ID throws exception
    // ========================================

    public function testLinkInvalidContentIdThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        $I->expectThrowable(\InvalidArgumentException::class, function () {
            $this->contentEn->linkTranslation(999999);
        });
    }

    // ========================================
    // Test 13 : Link both with groups throws exception
    // ========================================

    public function testLinkBothWithGroupsThrowsException(ModelsTester $I): void
    {
        $this->ensureTestData();
        // Create content with German language for second group
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

        // Create two separate groups: FR1+EN and ES+DE
        $this->contentFr1->linkTranslation($this->contentEn);
        $this->contentEs->linkTranslation($contentDe);

        $this->contentFr1->refresh();
        $this->contentEs->refresh();

        // Try to link FR1 (has group) with ES (has group)
        $I->expectThrowable(\LogicException::class, function () {
            $this->contentFr1->linkTranslation($this->contentEs);
        });
    }
}
