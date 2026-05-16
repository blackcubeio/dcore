<?php

declare(strict_types=1);

/**
 * TagManagementCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
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

    // ========================================
    // attachTag
    // ========================================

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
        $content->attachTag($tag); // Duplicate - should be ignored

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

    // ========================================
    // detachTag
    // ========================================

    public function testDetachTag(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();
        $tagId = $tag->getId();

        $content->attachTag($tag);
        $content->detachTag($tag);

        // Pivot should be gone
        $pivot = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tagId])
            ->one();
        $I->assertNull($pivot);

        // Tag itself should still exist (tags are shared)
        $tagAfter = Tag::query()->andWhere(['id' => $tagId])->one();
        $I->assertNotNull($tagAfter);
    }

    public function testDetachTagNotAttached(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        // Should not throw error
        $content->detachTag($tag);

        $I->assertTrue(true); // No exception
    }

    // ========================================
    // hasTag
    // ========================================

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

    // ========================================
    // syncTags
    // ========================================

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

        // Sync with only tag2 - should detach tag1 and tag3
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

        // Start with tag1 and tag2
        $content->attachTag($tag1);
        $content->attachTag($tag2);

        // Sync with tag2 and tag3 and tag4
        // - tag1 should be detached
        // - tag2 should remain
        // - tag3 and tag4 should be attached
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

        // Sync with empty array - should detach all
        $content->syncTags([]);

        $I->assertEquals(0, $content->getTagCount());
    }

    // ========================================
    // getTagCount
    // ========================================

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

    // ========================================
    // Helpers
    // ========================================

    private function createContent(): Content
    {
        $content = new Content();
        $content->setName('Test Content ' . uniqid());
        $content->save();
        return $content;
    }

    private function createTag(): Tag
    {
        $tag = new Tag();
        $tag->setName('Test Tag ' . uniqid());
        $tag->save();
        return $tag;
    }
}
