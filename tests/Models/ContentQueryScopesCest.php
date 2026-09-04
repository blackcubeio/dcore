<?php

declare(strict_types=1);

/**
 * ContentQueryScopesCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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


    public function testAtDateChangesReferenceDate(ModelsTester $I): void
    {
        $content = new Content();
        $content->setName('Future Content');
        $content->setDateStart(new DateTimeImmutable('2025-06-01'));
        $content->setDateEnd(new DateTimeImmutable('2025-06-30'));
        $content->save();

        $ids = [$content->getId()];

        $results = Content::query()->andWhere(['id' => $ids])->available()->all();
        $I->assertCount(0, $results);

        $results = Content::query()->andWhere(['id' => $ids])->atDate(date: '2025-06-15')->available()->all();
        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }


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
