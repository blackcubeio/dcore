<?php

declare(strict_types=1);

/**
 * BlocManagementCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ContentBloc;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagBloc;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;

/**
 * Tests for BlocManagementTrait.
 */
final class BlocManagementCest
{
    use DatabaseCestTrait;

    // ========================================
    // attachBloc
    // ========================================

    public function testAttachBlocAtEnd(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(2, $pivots);
        $I->assertEquals(1, $pivots[0]->getOrder());
        $I->assertEquals($bloc1->getId(), $pivots[0]->getBlocId());
        $I->assertEquals(2, $pivots[1]->getOrder());
        $I->assertEquals($bloc2->getId(), $pivots[1]->getBlocId());
    }

    public function testAttachBlocAtPosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);
        $content->attachBloc($bloc3, 2); // Insert at position 2

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(3, $pivots);
        $I->assertEquals($bloc1->getId(), $pivots[0]->getBlocId()); // position 1
        $I->assertEquals($bloc3->getId(), $pivots[1]->getBlocId()); // position 2 (inserted)
        $I->assertEquals($bloc2->getId(), $pivots[2]->getBlocId()); // position 3 (shifted)
    }

    public function testAttachBlocAtFirstPosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);
        $content->attachBloc($bloc3, 1); // Insert at first position

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(3, $pivots);
        $I->assertEquals($bloc3->getId(), $pivots[0]->getBlocId()); // position 1
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId()); // position 2
        $I->assertEquals($bloc2->getId(), $pivots[2]->getBlocId()); // position 3
    }

    public function testAttachBlocAlreadyAttached(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();

        $content->attachBloc($bloc);
        $content->attachBloc($bloc); // Duplicate - should be ignored

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->all();

        $I->assertCount(1, $pivots);
    }

    // ========================================
    // detachBloc
    // ========================================

    public function testDetachBlocDeletesPivotAndBloc(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();
        $blocId = $bloc->getId();

        $content->attachBloc($bloc);
        $content->detachBloc($bloc);

        // Pivot should be gone
        $pivot = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivot);

        // Bloc should be deleted (not shared)
        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNull($blocAfter);
    }

    public function testDetachBlocReordersRemaining(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);
        $content->attachBloc($bloc3);

        // Detach middle bloc
        $content->detachBloc($bloc2);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(2, $pivots);
        $I->assertEquals(1, $pivots[0]->getOrder());
        $I->assertEquals($bloc1->getId(), $pivots[0]->getBlocId());
        $I->assertEquals(2, $pivots[1]->getOrder());
        $I->assertEquals($bloc3->getId(), $pivots[1]->getBlocId());
    }

    // ========================================
    // moveBloc
    // ========================================

    public function testMoveBlocToNewPosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);
        $content->attachBloc($bloc3);

        // Move bloc1 from position 1 to position 3
        $content->moveBloc($bloc1, 3);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(3, $pivots);
        $I->assertEquals($bloc2->getId(), $pivots[0]->getBlocId()); // was 2, now 1
        $I->assertEquals($bloc3->getId(), $pivots[1]->getBlocId()); // was 3, now 2
        $I->assertEquals($bloc1->getId(), $pivots[2]->getBlocId()); // was 1, now 3
    }

    public function testMoveBlocUp(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);
        $content->attachBloc($bloc3);

        // Move bloc3 from position 3 to position 1
        $content->moveBloc($bloc3, 1);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(3, $pivots);
        $I->assertEquals($bloc3->getId(), $pivots[0]->getBlocId()); // now 1
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId()); // now 2
        $I->assertEquals($bloc2->getId(), $pivots[2]->getBlocId()); // now 3
    }

    public function testMoveBlocSamePosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);

        // Move bloc1 to same position - no change
        $content->moveBloc($bloc1, 1);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertEquals($bloc1->getId(), $pivots[0]->getBlocId());
        $I->assertEquals($bloc2->getId(), $pivots[1]->getBlocId());
    }

    // ========================================
    // moveBlocUp / moveBlocDown
    // ========================================

    public function testMoveBlocUpOnePosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);

        $content->moveBlocUp($bloc2);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertEquals($bloc2->getId(), $pivots[0]->getBlocId());
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId());
    }

    public function testMoveBlocDownOnePosition(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();

        $content->attachBloc($bloc1);
        $content->attachBloc($bloc2);

        $content->moveBlocDown($bloc1);

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertEquals($bloc2->getId(), $pivots[0]->getBlocId());
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId());
    }

    public function testMoveBlocUpAtTop(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();

        $content->attachBloc($bloc);
        $content->moveBlocUp($bloc); // Already at top - should do nothing

        $pivot = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->one();

        $I->assertEquals(1, $pivot->getOrder());
    }

    public function testMoveBlocDownAtBottom(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();

        $content->attachBloc($bloc);
        $content->moveBlocDown($bloc); // Already at bottom - should do nothing

        $pivot = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->one();

        $I->assertEquals(1, $pivot->getOrder());
    }

    // ========================================
    // reorderBlocs
    // ========================================

    public function testReorderBlocs(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        // Create pivots with non-sequential orders manually
        $pivot1 = new ContentBloc();
        $pivot1->setContentId($content->getId());
        $pivot1->setBlocId($bloc1->getId());
        $pivot1->setOrder(10);
        $pivot1->save();

        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content->getId());
        $pivot2->setBlocId($bloc2->getId());
        $pivot2->setOrder(5);
        $pivot2->save();

        $pivot3 = new ContentBloc();
        $pivot3->setContentId($content->getId());
        $pivot3->setBlocId($bloc3->getId());
        $pivot3->setOrder(20);
        $pivot3->save();

        // Reorder to sequential
        $content->reorderBlocs();

        $pivots = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertCount(3, $pivots);
        $I->assertEquals(1, $pivots[0]->getOrder());
        $I->assertEquals($bloc2->getId(), $pivots[0]->getBlocId()); // was 5, now 1
        $I->assertEquals(2, $pivots[1]->getOrder());
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId()); // was 10, now 2
        $I->assertEquals(3, $pivots[2]->getOrder());
        $I->assertEquals($bloc3->getId(), $pivots[2]->getBlocId()); // was 20, now 3
    }

    // ========================================
    // getBlocCount
    // ========================================

    public function testGetBlocCount(ModelsTester $I): void
    {
        $content = $this->createContent();

        $I->assertEquals(0, $content->getBlocCount());

        $content->attachBloc($this->createBloc());
        $I->assertEquals(1, $content->getBlocCount());

        $content->attachBloc($this->createBloc());
        $I->assertEquals(2, $content->getBlocCount());

        $content->attachBloc($this->createBloc());
        $I->assertEquals(3, $content->getBlocCount());
    }

    // ========================================
    // Tag blocs
    // ========================================

    public function testTagAttachBloc(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $bloc = $this->createBloc();

        $tag->attachBloc($bloc);

        $pivot = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId(), 'blocId' => $bloc->getId()])
            ->one();

        $I->assertNotNull($pivot);
        $I->assertEquals(1, $pivot->getOrder());
    }

    public function testTagDetachBloc(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $bloc = $this->createBloc();
        $blocId = $bloc->getId();

        $tag->attachBloc($bloc);
        $tag->detachBloc($bloc);

        // Pivot gone
        $pivot = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivot);

        // Bloc deleted
        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNull($blocAfter);
    }

    public function testTagMoveBloc(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        $tag->attachBloc($bloc1);
        $tag->attachBloc($bloc2);
        $tag->attachBloc($bloc3);

        $tag->moveBloc($bloc3, 1);

        $pivots = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        $I->assertEquals($bloc3->getId(), $pivots[0]->getBlocId());
        $I->assertEquals($bloc1->getId(), $pivots[1]->getBlocId());
        $I->assertEquals($bloc2->getId(), $pivots[2]->getBlocId());
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

    private function createBloc(): Bloc
    {
        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->setElasticSchemaId($schema->getId());
        $bloc->save();
        return $bloc;
    }

    private function createElasticSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-schema-' . uniqid());
        $schema->setSchema('{"type":"object"}');
        $schema->save();
        return $schema;
    }
}
