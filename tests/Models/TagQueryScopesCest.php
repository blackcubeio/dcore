<?php

declare(strict_types=1);

/**
 * TagQueryScopesCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for ScopedQuery scopes on Tag: active(), available(), atDate(), publishable().
 * Note: Tag has no dateStart/dateEnd, so date-related tests are adapted.
 */
final class TagQueryScopesCest
{
    use DatabaseCestTrait;

    // ========================================
    // Test 1 — active() filtre par champ actif
    // ========================================

    public function testActiveFiltersOnlyActiveRecords(ModelsTester $I): void
    {
        $active = new Tag();
        $active->setName('Active');
        $active->setActive(true);
        $active->save();

        $inactive = new Tag();
        $inactive->setName('Inactive');
        $inactive->setActive(false);
        $inactive->save();

        $ids = [$active->getId(), $inactive->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($active->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 2 — active() ne regarde pas les ancêtres
    // ========================================

    public function testActiveDoesNotCheckAncestors(ModelsTester $I): void
    {
        $parent = new Tag();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($child->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 3 — available() = active() pour Tag (pas de dates)
    // ========================================

    public function testAvailableEqualsActiveForTag(ModelsTester $I): void
    {
        $active = new Tag();
        $active->setName('Active');
        $active->setActive(true);
        $active->save();

        $inactive = new Tag();
        $inactive->setName('Inactive');
        $inactive->setActive(false);
        $inactive->save();

        $ids = [$active->getId(), $inactive->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->available()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($active->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 4 — atDate() ne fait rien pour Tag
    // ========================================

    public function testAtDateHasNoEffectForTag(ModelsTester $I): void
    {
        $tag1 = new Tag();
        $tag1->setName('Tag 1');
        $tag1->setActive(true);
        $tag1->save();

        $tag2 = new Tag();
        $tag2->setName('Tag 2');
        $tag2->setActive(true);
        $tag2->save();

        $ids = [$tag1->getId(), $tag2->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->atDate(date: '2025-06-15')->all();

        $I->assertCount(2, $results);
    }

    // ========================================
    // Test 5 — publishable() élément actif
    // ========================================

    public function testPublishableSingleActive(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Publishable');
        $tag->setActive(true);
        $tag->save();

        $ids = [$tag->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(1, $results);
        $I->assertEquals($tag->getId(), $results[0]->getId());
    }

    // ========================================
    // Test 6 — publishable() élément inactif = non
    // ========================================

    public function testPublishableInactiveExcluded(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Inactive');
        $tag->setActive(false);
        $tag->save();

        $ids = [$tag->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 7 — publishable() parent inactif bloque enfant
    // ========================================

    public function testPublishableInactiveParentBlocksChild(ModelsTester $I): void
    {
        $parent = new Tag();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$parent->getId(), $child->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 8 — publishable() chaîne complète valide
    // ========================================

    public function testPublishableValidChain(ModelsTester $I): void
    {
        $grandparent = new Tag();
        $grandparent->setName('Grandparent');
        $grandparent->setActive(true);
        $grandparent->save();

        $parent = new Tag();
        $parent->setName('Parent');
        $parent->setActive(true);
        $parent->saveInto($grandparent);

        $child = new Tag();
        $child->setName('Child');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$grandparent->getId(), $parent->getId(), $child->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(3, $results);
    }

    // ========================================
    // Test 9 — publishable() racine sans ancêtres
    // ========================================

    public function testPublishableRootNoAncestors(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Root');
        $tag->setActive(true);
        $tag->save();

        $ids = [$tag->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(1, $results);
    }

    // ========================================
    // Test 10 — publishable() grand-parent inactif bloque
    // ========================================

    public function testPublishableInactiveGrandparentBlocks(ModelsTester $I): void
    {
        $grandparent = new Tag();
        $grandparent->setName('Grandparent Inactive');
        $grandparent->setActive(false);
        $grandparent->save();

        $parent = new Tag();
        $parent->setName('Parent Active');
        $parent->setActive(true);
        $parent->saveInto($grandparent);

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $ids = [$grandparent->getId(), $parent->getId(), $child->getId()];
        $results = Tag::query()->andWhere(['id' => $ids])->publishable()->all();

        $I->assertCount(0, $results);
    }
}
