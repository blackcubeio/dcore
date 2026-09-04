<?php

declare(strict_types=1);

/**
 * TagManagementCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ContentTag;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for TagManagementTrait.
 */
final class TagManagementCest
{
    use DatabaseCestTrait;


    public function testAttachTag(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $content->attachTag($tag);

        $pivot = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();

        $I->assertNotNull($pivot);
    }

    public function testAttachTagAlreadyAttached(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $content->attachTag($tag);
        $content->attachTag($tag);

        $pivots = ContentTag::query()
            ->andWhere(['contentId' => $content->getId()])
            ->all();

        $I->assertCount(1, $pivots);
    }

    public function testAttachMultipleTags(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();
        $tag3 = $this->createTag();

        $content->attachTag($tag1);
        $content->attachTag($tag2);
        $content->attachTag($tag3);

        $pivots = ContentTag::query()
            ->andWhere(['contentId' => $content->getId()])
            ->all();

        $I->assertCount(3, $pivots);
    }


    public function testDetachTag(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();
        $tagId = $tag->getId();

        $content->attachTag($tag);
        $content->detachTag($tag);

        $pivot = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tagId])
            ->one();
        $I->assertNull($pivot);

        $tagAfter = Tag::query()->andWhere(['id' => $tagId])->one();
        $I->assertNotNull($tagAfter);
    }

    public function testDetachTagNotAttached(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $content->detachTag($tag);

        $I->assertTrue(true);
    }


    public function testHasTag(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $I->assertFalse($content->hasTag($tag));

        $content->attachTag($tag);

        $I->assertTrue($content->hasTag($tag));
    }

    public function testHasTagAfterDetach(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $content->attachTag($tag);
        $I->assertTrue($content->hasTag($tag));

        $content->detachTag($tag);
        $I->assertFalse($content->hasTag($tag));
    }


    public function testSyncTagsAttachNew(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $content->syncTags([$tag1, $tag2]);

        $I->assertTrue($content->hasTag($tag1));
        $I->assertTrue($content->hasTag($tag2));
        $I->assertEquals(2, $content->getTagCount());
    }

    public function testSyncTagsDetachRemoved(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();
        $tag3 = $this->createTag();

        $content->attachTag($tag1);
        $content->attachTag($tag2);
        $content->attachTag($tag3);

        $content->syncTags([$tag2]);

        $I->assertFalse($content->hasTag($tag1));
        $I->assertTrue($content->hasTag($tag2));
        $I->assertFalse($content->hasTag($tag3));
        $I->assertEquals(1, $content->getTagCount());
    }

    public function testSyncTagsMixed(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();
        $tag3 = $this->createTag();
        $tag4 = $this->createTag();

        $content->attachTag($tag1);
        $content->attachTag($tag2);

        $content->syncTags([$tag2, $tag3, $tag4]);

        $I->assertFalse($content->hasTag($tag1));
        $I->assertTrue($content->hasTag($tag2));
        $I->assertTrue($content->hasTag($tag3));
        $I->assertTrue($content->hasTag($tag4));
        $I->assertEquals(3, $content->getTagCount());
    }

    public function testSyncTagsEmpty(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        $content->attachTag($tag1);
        $content->attachTag($tag2);

        $content->syncTags([]);

        $I->assertEquals(0, $content->getTagCount());
    }


    public function testGetTagCount(ModelsTester $I): void
    {
        $content = $this->createContent();

        $I->assertEquals(0, $content->getTagCount());

        $content->attachTag($this->createTag());
        $I->assertEquals(1, $content->getTagCount());

        $content->attachTag($this->createTag());
        $I->assertEquals(2, $content->getTagCount());

        $content->attachTag($this->createTag());
        $I->assertEquals(3, $content->getTagCount());
    }


    private function createContent(): Content
    {
        $content = new Content();
        $content->setName('Test Content '.uniqid());
        $content->save();
        return $content;
    }

    private function createTag(): Tag
    {
        $tag = new Tag();
        $tag->setName('Test Tag '.uniqid());
        $tag->save();
        return $tag;
    }
}
