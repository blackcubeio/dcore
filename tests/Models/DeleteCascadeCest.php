<?php

declare(strict_types=1);

/**
 * DeleteCascadeCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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


    public function testDeleteContentCascadesContentBloc(ModelsTester $I): void
    {
        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->saveInto($parent);

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

        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content2->getId());
        $pivot2->setBlocId($blocId);
        $pivot2->setOrder(1);
        $pivot2->save();

        $pivotExists = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNotNull($pivotExists);

        $content->delete();

        $pivotAfter = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivotAfter);

        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNotNull($blocAfter);
    }


    public function testDeleteContentCascadesContentTag(ModelsTester $I): void
    {
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

        $content->delete();

        $pivotAfter = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();
        $I->assertNull($pivotAfter);

        $tagAfter = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $I->assertNotNull($tagAfter);
    }


    public function testDeleteTagCascadesTagBloc(ModelsTester $I): void
    {
        $tagParent = new Tag();
        $tagParent->setName('Tag Parent');
        $tagParent->save();

        $tag = new Tag();
        $tag->setName('To Delete');
        $tag->saveInto($tagParent);

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

        $pivot2 = new TagBloc();
        $pivot2->setTagId($tag2->getId());
        $pivot2->setBlocId($blocId);
        $pivot2->setOrder(1);
        $pivot2->save();

        $tag->delete();

        $pivotAfter = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId(), 'blocId' => $blocId])
            ->one();
        $I->assertNull($pivotAfter);

        $blocAfter = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertNotNull($blocAfter);
    }


    public function testDeleteTagCascadesContentTag(ModelsTester $I): void
    {
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

        $tag->delete();

        $pivotAfter = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();
        $I->assertNull($pivotAfter);

        $contentAfter = Content::query()->andWhere(['id' => $content->getId()])->one();
        $I->assertNotNull($contentAfter);
    }


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

        $bloc->delete();

        $I->assertNull(ContentBloc::query()->andWhere(['blocId' => $bloc->getId()])->one());
        $I->assertNull(TagBloc::query()->andWhere(['blocId' => $bloc->getId()])->one());

        $I->assertNotNull(Content::query()->andWhere(['id' => $content->getId()])->one());
        $I->assertNotNull(Tag::query()->andWhere(['id' => $tag->getId()])->one());
    }


    public function testDeleteContentCascadesSlug(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/cascade-test-'.uniqid());
        $slug->save();
        $slugId = $slug->getId();

        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->setSlugId($slugId);
        $content->saveInto($parent);

        $I->assertEquals($slugId, $content->getSlugId());

        $content->delete();

        $slugAfter = Slug::query()->andWhere(['id' => $slugId])->one();
        $I->assertNull($slugAfter);
    }


    public function testDeleteContentCascadesSeoAndSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/seo-test-'.uniqid());
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

        $parent = new Content();
        $parent->setName('Parent');
        $parent->save();

        $content = new Content();
        $content->setName('To Delete');
        $content->setSlugId($slugId);
        $content->saveInto($parent);

        $content->delete();

        $I->assertNull(Slug::query()->andWhere(['id' => $slugId])->one());
        $I->assertNull(Xeo::query()->andWhere(['id' => $seoId])->one());
        $I->assertNull(Sitemap::query()->andWhere(['id' => $sitemapId])->one());
    }


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

        $content->delete();

        $I->assertNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }


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

        $content1->delete();

        $I->assertNotNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }


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

        $content->delete();

        $I->assertNull(ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one());
    }


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

        $content1->delete();

        $I->assertNotNull(ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one());
    }


    public function testDeleteTagCascadesSlugSeoSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setPath('/tag-cascade-'.uniqid());
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

        $tag->delete();

        $I->assertNull(Slug::query()->andWhere(['id' => $slugId])->one());
        $I->assertNull(Xeo::query()->andWhere(['id' => $seoId])->one());
        $I->assertNull(Sitemap::query()->andWhere(['id' => $sitemapId])->one());
    }


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

        $tag->delete();

        $I->assertNull(Bloc::query()->andWhere(['id' => $blocId])->one());
    }


    private function createElasticSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-schema-'.uniqid());
        $schema->setSchema('{"type":"object"}');
        $schema->save();
        return $schema;
    }
}
