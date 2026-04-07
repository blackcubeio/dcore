<?php

declare(strict_types=1);

/**
 * ContentQueryScopesCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use DateTimeImmutable;

/**
 * Tests for ScopedQuery scopes on Content: active(), available(), atDate(), publishable().
 */
final class ContentQueryScopesCest
{
    use DatabaseCestTrait;

    // ========================================
    // Test 1 — active() filtre par champ actif
    // ========================================

    public function testActiveFiltersOnlyActiveRecords(ModelsTester $I): void
    {
        $active = new Content();
        $active->setName('Active');
        $active->setActive(true);
        $active->save();

        $inactive = new Content();
        $inactive->setName('Inactive');
        $inactive->setActive(false);
        $inactive->save();

        $ids = [$active->getId(), $inactive->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($active->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 2 — active() ne regarde pas les ancêtres
    // ========================================

    public function testActiveDoesNotCheckAncestors(ModelsTester $I): void
    {
        $parent = new Content();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Content();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($child->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 3 — available() dates valides
    // ========================================

    public function testAvailableWithValidDates(ModelsTester $I): void
    {
        $now = new DateTimeImmutable();
        $yesterday = $now->modify('-1 day');
        $tomorrow = $now->modify('+1 day');

        $content1 = new Content();
        $content1->setName('Available');
        $content1->setDateStart($yesterday);
        $content1->setDateEnd($tomorrow);
        $content1->save();

        $content2 = new Content();
        $content2->setName('Not Started');
        $content2->setDateStart($tomorrow);
        $content2->save();

        $content3 = new Content();
        $content3->setName('Expired');
        $content3->setDateEnd($yesterday);
        $content3->save();

        $ids = [$content1->getId(), $content2->getId(), $content3->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->available()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content1->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 4 — available() dates NULL = pas de restriction
    // ========================================

    public function testAvailableNullDatesAlwaysValid(ModelsTester $I): void
    {
        $content1 = new Content();
        $content1->setName('No Dates');
        $content1->save();

        $content2 = new Content();
        $content2->setName('Only Start');
        $content2->setDateStart(new DateTimeImmutable('-1 day'));
        $content2->save();

        $content3 = new Content();
        $content3->setName('Only End');
        $content3->setDateEnd(new DateTimeImmutable('+1 day'));
        $content3->save();

        $ids = [$content1->getId(), $content2->getId(), $content3->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->available()->all();

        $I->assertCount(3, $results);
    }

    // ========================================
    // Test 5 — atDate() change la date de référence
    // ========================================

    public function testAtDateChangesReferenceDate(ModelsTester $I): void
    {
        $content = new Content();
        $content->setName('Future Content');
        $content->setDateStart(new DateTimeImmutable('2025-06-01'));
        $content->setDateEnd(new DateTimeImmutable('2025-06-30'));
        $content->save();

        $ids = [$content->getId()];

        // Sans atDate : pas disponible maintenant
        $results = Content::query()->andWhere(['id' => $ids])->available()->all();
        $I->assertCount(0, $results);

        // Avec atDate en juin 2025 : disponible
        $results = Content::query()->andWhere(['id' => $ids])->atDate(date: '2025-06-15')->available()->all();
        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 6 — atDate() seul ne filtre rien
    // ========================================

    public function testAtDateAloneDoesNotFilter(ModelsTester $I): void
    {
        $content1 = new Content();
        $content1->setName('Content 1');
        $content1->save();

        $content2 = new Content();
        $content2->setName('Content 2');
        $content2->save();

        $ids = [$content1->getId(), $content2->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->atDate(date: '2025-06-15')->all();

        $I->assertCount(2, $results);
    }

    // ========================================
    // Test 7 — publishable() élément seul actif et disponible
    // ========================================

    public function testPublishableSingleActiveAndAvailable(ModelsTester $I): void
    {
        $now = new DateTimeImmutable();
        $yesterday = $now->modify('-1 day');
        $tomorrow = $now->modify('+1 day');

        $content = new Content();
        $content->setName('Publishable');
        $content->setActive(true);
        $content->setDateStart($yesterday);
        $content->setDateEnd($tomorrow);
        $content->save();

        $ids = [$content->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 8 — publishable() élément inactif = non
    // ========================================

    public function testPublishableInactiveExcluded(ModelsTester $I): void
    {
        $content = new Content();
        $content->setName('Inactive');
        $content->setActive(false);
        $content->save();

        $ids = [$content->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 9 — publishable() parent inactif bloque enfant
    // ========================================

    public function testPublishableInactiveParentBlocksChild(ModelsTester $I): void
    {
        $parent = new Content();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Content();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 10 — publishable() parent expiré bloque enfant
    // ========================================

    public function testPublishableExpiredParentBlocksChild(ModelsTester $I): void
    {
        $now = new DateTimeImmutable();
        $yesterday = $now->modify('-1 day');
        $tomorrow = $now->modify('+1 day');

        $parent = new Content();
        $parent->setName('Parent Expired');
        $parent->setActive(true);
        $parent->setDateEnd($yesterday);
        $parent->save();

        $child = new Content();
        $child->setName('Child Valid');
        $child->setActive(true);
        $child->setDateStart($yesterday);
        $child->setDateEnd($tomorrow);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 11 — publishable() chaîne complète valide
    // ========================================

    public function testPublishableValidChain(ModelsTester $I): void
    {
        $now = new DateTimeImmutable();
        $yesterday = $now->modify('-1 day');
        $tomorrow = $now->modify('+1 day');

        $grandparent = new Content();
        $grandparent->setName('Grandparent');
        $grandparent->setActive(true);
        $grandparent->setDateStart($yesterday);
        $grandparent->setDateEnd($tomorrow);
        $grandparent->save();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->setActive(true);
        $parent->setDateStart($yesterday);
        $parent->setDateEnd($tomorrow);
        $parent->saveInto($grandparent);

        $child = new Content();
        $child->setName('Child');
        $child->setActive(true);
        $child->setDateStart($yesterday);
        $child->setDateEnd($tomorrow);
        $child->saveInto($parent);

        $ids = [$grandparent->getId(), $parent->getId(), $child->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(3, $results);
    }

    // ========================================
    // Test 12 — publishable() racine sans ancêtres
    // ========================================

    public function testPublishableRootNoAncestors(ModelsTester $I): void
    {
        $content = new Content();
        $content->setName('Root');
        $content->setActive(true);
        $content->save();

        $ids = [$content->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(1, $results);
    }

    // ========================================
    // Test 13 — publishable() ancêtre futur bloque
    // ========================================

    public function testPublishableFutureAncestorBlocks(ModelsTester $I): void
    {
        $futureDate = new DateTimeImmutable('+1 month');

        $parent = new Content();
        $parent->setName('Parent Future');
        $parent->setActive(true);
        $parent->setDateStart($futureDate);
        $parent->save();

        $child = new Content();
        $child->setName('Child Now');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Content::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }
}
