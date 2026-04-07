<?php

declare(strict_types=1);

/**
 * DeleteCascadeCest.php
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
use Blackcube\Dcore\Models\ContentTag;
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagBloc;
use Blackcube\Dcore\Models\ContentTranslationGroup;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;

/**
 * Tests for delete cascade behavior on pivot tables.
 */
final class DeleteCascadeCest
{
    use DatabaseCestTrait;

    // ========================================
    // Test 1 — Delete Content → ContentBloc supprimé
    // ========================================

    public function testDeleteContentCascadesContentBloc(ModelsTester $I): void
    {
        // Setup - create parent and child (Hazeltree prevents deleting root)
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->saveInto($parent);

        // Create a second content that will share the bloc
        $content2 = new Content();
        $content2->setName('Keeps Bloc');
        $content2->saveInto($parent);

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();
        $blocId = $bloc->getId();

        $pivot = new ContentBloc();
        $pivot->setContentId($content->getId());
        $pivot->setBlocId($blocId);
        $pivot->setOrder(1);
        $pivot->save();

        // Link bloc to second content so it's not orphaned
        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content2->getId());
        $pivot2->setBlocId($blocId);
        $pivot2->setOrder(1);
        $pivot2->save();

        // Verify pivot exists
        $pivotExists = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNotNull($pivotExists);

        // Delete content (child, not root)
        $content->delete();

        // Verify pivot is gone (CASCADE)
        $pivotAfter = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivotAfter);

        // Verify bloc still exists (shared with content2)
        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNotNull($blocAfter);
    }

    // ========================================
    // Test 2 — Delete Content → ContentTag supprimé
    // ========================================

    public function testDeleteContentCascadesContentTag(ModelsTester $I): void
    {
        // Setup - create parent and child (Hazeltree prevents deleting root)
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->saveInto($parent);

        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->saveInto($tagParent);

        $pivot = new ContentTag();
        $pivot->setContentId($content->getId());
        $pivot->setTagId($tag->getId());
        $pivot->save();

        // Delete content
        $content->delete();

        // Verify pivot is gone
        $pivotAfter = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();
        $I->assertNull($pivotAfter);

        // Verify tag still exists
        $tagAfter = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $I->assertNotNull($tagAfter);
    }

    // ========================================
    // Test 3 — Delete Tag → TagBloc supprimé
    // ========================================

    public function testDeleteTagCascadesTagBloc(ModelsTester $I): void
    {
        // Setup - create parent and child (Hazeltree prevents deleting root)
        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('To Delete');
        $tag->saveInto($tagParent);

        // Create a second tag that will share the bloc
        $tag2 = new Tag();
        $tag2->setName('Keeps Bloc');
        $tag2->saveInto($tagParent);

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();
        $blocId = $bloc->getId();

        $pivot = new TagBloc();
        $pivot->setTagId($tag->getId());
        $pivot->setBlocId($blocId);
        $pivot->setOrder(1);
        $pivot->save();

        // Link bloc to second tag so it's not orphaned
        $pivot2 = new TagBloc();
        $pivot2->setTagId($tag2->getId());
        $pivot2->setBlocId($blocId);
        $pivot2->setOrder(1);
        $pivot2->save();

        // Delete tag
        $tag->delete();

        // Verify pivot is gone
        $pivotAfter = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivotAfter);

        // Verify bloc still exists (shared with tag2)
        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNotNull($blocAfter);
    }

    // ========================================
    // Test 4 — Delete Tag → ContentTag supprimé
    // ========================================

    public function testDeleteTagCascadesContentTag(ModelsTester $I): void
    {
        // Setup - create parent and child (Hazeltree prevents deleting root)
        $contentParent = new Content();
        $contentParent->setName('Content Parent');
        $contentParent->save();

        $content = new Content();
        $content->setName('Test Content');
        $content->saveInto($contentParent);

        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('To Delete');
        $tag->saveInto($tagParent);

        $pivot = new ContentTag();
        $pivot->setContentId($content->getId());
        $pivot->setTagId($tag->getId());
        $pivot->save();

        // Delete tag
        $tag->delete();

        // Verify pivot is gone
        $pivotAfter = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();
        $I->assertNull($pivotAfter);

        // Verify content still exists
        $contentAfter = Content::query()->andWhere(['id' => $content->getId()])->one();
        $I->assertNotNull($contentAfter);
    }

    // ========================================
    // Test 5 — Delete Bloc → pivots supprimés
    // ========================================

    public function testDeleteBlocCascadesPivots(ModelsTester $I): void
    {
        $content = new Content();
        $content->setName('Test Content');
        $content->save();

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->save();

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $pivot1 = new ContentBloc();
        $pivot1->setContentId($content->getId());
        $pivot1->setBlocId($bloc->getId());
        $pivot1->setOrder(1);
        $pivot1->save();

        $pivot2 = new TagBloc();
        $pivot2->setTagId($tag->getId());
        $pivot2->setBlocId($bloc->getId());
        $pivot2->setOrder(1);
        $pivot2->save();

        // Delete bloc
        $bloc->delete();

        // Verify both pivots are gone
        $I->assertNull(ContentBloc::query()->andWhere(['blocId' => $bloc->getId()])->one());
        $I->assertNull(TagBloc::query()->andWhere(['blocId' => $bloc->getId()])->one());

        // Verify content and tag still exist
        $I->assertNotNull(Content::query()->andWhere(['id' => $content->getId()])->one());
        $I->assertNotNull(Tag::query()->andWhere(['id' => $tag->getId()])->one());
    }

    // ========================================
    // Test 6 — Delete Content → Slug supprimé (CASCADE)
    // ========================================

    public function testDeleteContentCascadesSlug(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/cascade-test-' . uniqid());
        $slug->save();
        $slugId = $slug->getId();

        // Setup - create parent and child (Hazeltree prevents deleting root)
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->setSlugId($slugId);
        $content->saveInto($parent);

        // Verify link
        $I->assertEquals($slugId, $content->getSlugId());

        // Delete content
        $content->delete();

        // Verify slug is deleted (CASCADE)
        $slugAfter = Slug::query()->andWhere(['id' => $slugId])->one();
        $I->assertNull($slugAfter);
    }

    // ========================================
    // Test 7 — Delete Content → Seo/Sitemap supprimés via Slug
    // ========================================

    public function testDeleteContentCascadesSeoAndSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/seo-test-' . uniqid());
        $slug->save();
        $slugId = $slug->getId();

        // Create Seo linked to Slug
        $seo = new Xeo();
        $seo->setSlugId($slugId);
        $seo->setTitle('Test SEO');
        $seo->save();
        $seoId = $seo->getId();

        // Create Sitemap linked to Slug
        $sitemap = new Sitemap();
        $sitemap->setSlugId($slugId);
        $sitemap->save();
        $sitemapId = $sitemap->getId();

        // Setup content
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->setSlugId($slugId);
        $content->saveInto($parent);

        // Delete content
        $content->delete();

        // Verify all are deleted
        $I->assertNull(Slug::query()->andWhere(['id' => $slugId])->one());
        $I->assertNull(Xeo::query()->andWhere(['id' => $seoId])->one());
        $I->assertNull(Sitemap::query()->andWhere(['id' => $sitemapId])->one());
    }

    // ========================================
    // Test 8 — Delete Content → Bloc orphelin supprimé
    // ========================================

    public function testDeleteContentCascadesOrphanedBloc(ModelsTester $I): void
    {
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->saveInto($parent);

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();
        $blocId = $bloc->getId();

        $pivot = new ContentBloc();
        $pivot->setContentId($content->getId());
        $pivot->setBlocId($blocId);
        $pivot->setOrder(1);
        $pivot->save();

        // Delete content
        $content->delete();

        // Verify bloc is deleted (orphaned)
        $I->assertNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }

    // ========================================
    // Test 9 — Delete Content → Bloc partagé NON supprimé
    // ========================================

    public function testDeleteContentKeepsSharedBloc(ModelsTester $I): void
    {
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content1 = new Content();
        $content1->setName('To Delete');
        $content1->saveInto($parent);

        $content2 = new Content();
        $content2->setName('Keep');
        $content2->saveInto($parent);

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();
        $blocId = $bloc->getId();

        // Link bloc to both contents
        $pivot1 = new ContentBloc();
        $pivot1->setContentId($content1->getId());
        $pivot1->setBlocId($blocId);
        $pivot1->setOrder(1);
        $pivot1->save();

        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content2->getId());
        $pivot2->setBlocId($blocId);
        $pivot2->setOrder(1);
        $pivot2->save();

        // Delete content1
        $content1->delete();

        // Verify bloc still exists (shared with content2)
        $I->assertNotNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }

    // ========================================
    // Test 10 — Delete Content → ContentTranslationGroup orphelin supprimé
    // ========================================

    public function testDeleteContentCascadesOrphanedContentTranslationGroup(ModelsTester $I): void
    {
        $translationGroup = new ContentTranslationGroup();
        $translationGroup->save();
        $groupId = $translationGroup->getId();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->setTranslationGroupId($groupId);
        $content->saveInto($parent);

        // Delete content
        $content->delete();

        // Verify translation group is deleted (orphaned)
        $I->assertNull(ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one());
    }

    // ========================================
    // Test 11 — Delete Content → ContentTranslationGroup partagé NON supprimé
    // ========================================

    public function testDeleteContentKeepsSharedContentTranslationGroup(ModelsTester $I): void
    {
        $translationGroup = new ContentTranslationGroup();
        $translationGroup->save();
        $groupId = $translationGroup->getId();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content1 = new Content();
        $content1->setName('To Delete FR');
        $content1->setTranslationGroupId($groupId);
        $content1->saveInto($parent);

        $content2 = new Content();
        $content2->setName('Keep EN');
        $content2->setTranslationGroupId($groupId);
        $content2->saveInto($parent);

        // Delete content1
        $content1->delete();

        // Verify translation group still exists (shared with content2)
        $I->assertNotNull(ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one());
    }

    // ========================================
    // Test 12 — Delete Tag → Slug/Seo/Sitemap supprimés
    // ========================================

    public function testDeleteTagCascadesSlugSeoSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/tag-cascade-' . uniqid());
        $slug->save();
        $slugId = $slug->getId();

        $seo = new Xeo();
        $seo->setSlugId($slugId);
        $seo->setTitle('Test SEO');
        $seo->save();
        $seoId = $seo->getId();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slugId);
        $sitemap->save();
        $sitemapId = $sitemap->getId();

        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('To Delete');
        $tag->setSlugId($slugId);
        $tag->saveInto($tagParent);

        // Delete tag
        $tag->delete();

        // Verify all are deleted
        $I->assertNull(Slug::query()->andWhere(['id' => $slugId])->one());
        $I->assertNull(Xeo::query()->andWhere(['id' => $seoId])->one());
        $I->assertNull(Sitemap::query()->andWhere(['id' => $sitemapId])->one());
    }

    // ========================================
    // Test 13 — Delete Tag → Bloc orphelin supprimé
    // ========================================

    public function testDeleteTagCascadesOrphanedBloc(ModelsTester $I): void
    {
        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('To Delete');
        $tag->saveInto($tagParent);

        $schema = $this->createElasticSchema();
        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();
        $blocId = $bloc->getId();

        $pivot = new TagBloc();
        $pivot->setTagId($tag->getId());
        $pivot->setBlocId($blocId);
        $pivot->setOrder(1);
        $pivot->save();

        // Delete tag
        $tag->delete();

        // Verify bloc is deleted (orphaned)
        $I->assertNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }

    // ========================================
    // Helper
    // ========================================

    private function createElasticSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-schema-' . uniqid());
        $schema->setSchema('{"type":"object"}');
        $schema->save();
        return $schema;
    }
}
