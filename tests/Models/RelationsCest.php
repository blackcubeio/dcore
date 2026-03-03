<?php

declare(strict_types=1);

/**
 * RelationsCest.php
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
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\TagBloc;
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Elastic\ElasticSchema;

/**
 * Tests for ActiveRecord relations.
 */
final class RelationsCest
{
    use DatabaseCestTrait {
        _before as traitBefore;
    }

    public function _before(ModelsTester $I): void
    {
        $this->traitBefore($I);
        // Clear schema caches
        Content::clearSchemaCache();
        Tag::clearSchemaCache();
        Bloc::clearSchemaCache();
    }

    // ========================================
    // Helper methods
    // ========================================

    private function createHost(string $name = 'test-host'): Host
    {
        $host = new Host();
        $host->setName($name . '-' . uniqid());
        $host->save();
        return $host;
    }

    private function createSlug(string $path, ?Host $host = null): Slug
    {
        $slug = new Slug();
        $slug->setPath($path . '-' . uniqid());
        if ($host !== null) {
            $slug->setHostId($host->getId());
        }
        $slug->save();
        return $slug;
    }

    private function createType(string $name = 'test-type'): Type
    {
        $type = new Type();
        $type->setName($name . '-' . uniqid());
        $type->save();
        return $type;
    }

    private static int $languageCounter = 0;

    private function createLanguage(string $id = 'fr'): Language
    {
        $language = new Language();
        $language->setId($id . ++self::$languageCounter);
        $language->setName('Test Language');
        $language->save();
        return $language;
    }

    private function createElasticSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-schema-' . uniqid());
        $schema->setSchema('{"type":"object"}');
        $schema->save();
        return $schema;
    }

    private function createContent(?Slug $slug = null, ?Type $type = null, ?Language $language = null): Content
    {
        $content = new Content();
        $content->setName('Test Content ' . uniqid());
        if ($slug !== null) {
            $content->setSlugId($slug->getId());
        }
        if ($type !== null) {
            $content->setTypeId($type->getId());
        }
        if ($language !== null) {
            $content->setLanguageId($language->getId());
        }
        $content->save();
        return $content;
    }

    private function createTag(?Slug $slug = null, ?Type $type = null): Tag
    {
        $tag = new Tag();
        $tag->setName('Test Tag ' . uniqid());
        if ($slug !== null) {
            $tag->setSlugId($slug->getId());
        }
        if ($type !== null) {
            $tag->setTypeId($type->getId());
        }
        $tag->save();
        return $tag;
    }

    private function createBloc(?ElasticSchema $schema = null): Bloc
    {
        $bloc = new Bloc();
        if ($schema !== null) {
            $bloc->elasticSchemaId = $schema->getId();
        } else {
            $schema = $this->createElasticSchema();
            $bloc->elasticSchemaId = $schema->getId();
        }
        $bloc->save();
        return $bloc;
    }

    // ========================================
    // Test 1 — hasOne direct
    // ========================================

    public function testHasOneSlugHost(ModelsTester $I): void
    {
        $host = $this->createHost('relation-host');
        $slug = $this->createSlug('/test-path', $host);

        // Reload and test relation (magic property access)
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $relatedHost = $loadedSlug->getHostQuery()->one();

        $I->assertInstanceOf(Host::class, $relatedHost);
        $I->assertEquals($host->getId(), $relatedHost->getId());
        $I->assertEquals($host->getName(), $relatedHost->getName());
    }

    public function testHasOneContentSlug(ModelsTester $I): void
    {
        $slug = $this->createSlug('/content-path');
        $content = $this->createContent($slug);

        // Reload and test relation (magic property access)
        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $relatedSlug = $loadedContent->getSlugQuery()->one();

        $I->assertInstanceOf(Slug::class, $relatedSlug);
        $I->assertEquals($slug->getId(), $relatedSlug->getId());
    }

    public function testHasOneContentType(ModelsTester $I): void
    {
        $type = $this->createType('content-type');
        $content = $this->createContent(null, $type);

        // Reload and test relation (magic property access)
        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $relatedType = $loadedContent->getTypeQuery()->one();

        $I->assertInstanceOf(Type::class, $relatedType);
        $I->assertEquals($type->getId(), $relatedType->getId());
    }

    public function testHasOneContentLanguage(ModelsTester $I): void
    {
        $language = $this->createLanguage('en');
        $content = $this->createContent(null, null, $language);

        // Reload and test relation (magic property access)
        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $relatedLanguage = $loadedContent->getLanguageQuery()->one();

        $I->assertInstanceOf(Language::class, $relatedLanguage);
        $I->assertEquals($language->getId(), $relatedLanguage->getId());
    }

    public function testHasOneTagSlug(ModelsTester $I): void
    {
        $slug = $this->createSlug('/tag-path');
        $tag = $this->createTag($slug);

        // Reload and test relation (magic property access)
        $loadedTag = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $relatedSlug = $loadedTag->getSlugQuery()->one();

        $I->assertInstanceOf(Slug::class, $relatedSlug);
        $I->assertEquals($slug->getId(), $relatedSlug->getId());
    }

    public function testHasOneTagType(ModelsTester $I): void
    {
        $type = $this->createType('tag-type');
        $tag = $this->createTag(null, $type);

        // Reload and test relation (magic property access)
        $loadedTag = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $relatedType = $loadedTag->getTypeQuery()->one();

        $I->assertInstanceOf(Type::class, $relatedType);
        $I->assertEquals($type->getId(), $relatedType->getId());
    }

    public function testHasOneBlocElasticSchema(ModelsTester $I): void
    {
        $schema = $this->createElasticSchema();
        $bloc = $this->createBloc($schema);

        // Reload and test relation (magic property access)
        $loadedBloc = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $relatedSchema = $loadedBloc->getElasticSchemaQuery()->one();

        $I->assertInstanceOf(ElasticSchema::class, $relatedSchema);
        $I->assertEquals($schema->getId(), $relatedSchema->getId());
    }

    public function testHasOneXeoSlug(ModelsTester $I): void
    {
        $slug = $this->createSlug('/xeo-path');

        $xeo = new Xeo();
        $xeo->setSlugId($slug->getId());
        $xeo->setTitle('XEO Title');
        $xeo->save();

        // Reload and test relation (magic property access)
        $loadedXeo = Xeo::query()->andWhere(['id' => $xeo->getId()])->one();
        $relatedSlug = $loadedXeo->getSlugQuery()->one();

        $I->assertInstanceOf(Slug::class, $relatedSlug);
        $I->assertEquals($slug->getId(), $relatedSlug->getId());
    }

    public function testHasOneSitemapSlug(ModelsTester $I): void
    {
        $slug = $this->createSlug('/sitemap-path');

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->save();

        // Reload and test relation (magic property access)
        $loadedSitemap = Sitemap::query()->andWhere(['id' => $sitemap->getId()])->one();
        $relatedSlug = $loadedSitemap->getSlugQuery()->one();

        $I->assertInstanceOf(Slug::class, $relatedSlug);
        $I->assertEquals($slug->getId(), $relatedSlug->getId());
    }

    // ========================================
    // Test 2 — hasOne inverse
    // ========================================

    public function testHasOneInverseSlugXeo(ModelsTester $I): void
    {
        $slug = $this->createSlug('/inverse-xeo');

        $xeo = new Xeo();
        $xeo->setSlugId($slug->getId());
        $xeo->setTitle('Inverse XEO');
        $xeo->save();

        // Test inverse relation from Slug (magic property access)
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $relatedXeo = $loadedSlug->getXeoQuery()->one();

        $I->assertInstanceOf(Xeo::class, $relatedXeo);
        $I->assertEquals($xeo->getId(), $relatedXeo->getId());
    }

    public function testHasOneInverseSlugSitemap(ModelsTester $I): void
    {
        $slug = $this->createSlug('/inverse-sitemap');

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->save();

        // Test inverse relation from Slug (magic property access)
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $relatedSitemap = $loadedSlug->getSitemapQuery()->one();

        $I->assertInstanceOf(Sitemap::class, $relatedSitemap);
        $I->assertEquals($sitemap->getId(), $relatedSitemap->getId());
    }

    public function testHasOneInverseSlugContent(ModelsTester $I): void
    {
        $slug = $this->createSlug('/inverse-content');
        $content = $this->createContent($slug);

        // Test inverse relation from Slug (magic property access)
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $relatedContent = $loadedSlug->getContentQuery()->one();

        $I->assertInstanceOf(Content::class, $relatedContent);
        $I->assertEquals($content->getId(), $relatedContent->getId());
    }

    public function testHasOneInverseSlugTag(ModelsTester $I): void
    {
        $slug = $this->createSlug('/inverse-tag');
        $tag = $this->createTag($slug);

        // Test inverse relation from Slug (magic property access)
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $relatedTag = $loadedSlug->getTagQuery()->one();

        $I->assertInstanceOf(Tag::class, $relatedTag);
        $I->assertEquals($tag->getId(), $relatedTag->getId());
    }

    // ========================================
    // Test 3 — hasMany via pivot
    // ========================================

    public function testHasManyContentBlocs(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();
        $bloc3 = $this->createBloc();

        // Create pivot entries with order
        $pivot1 = new ContentBloc();
        $pivot1->setContentId($content->getId());
        $pivot1->setBlocId($bloc1->getId());
        $pivot1->setOrder(3);
        $pivot1->save();

        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content->getId());
        $pivot2->setBlocId($bloc2->getId());
        $pivot2->setOrder(1);
        $pivot2->save();

        $pivot3 = new ContentBloc();
        $pivot3->setContentId($content->getId());
        $pivot3->setBlocId($bloc3->getId());
        $pivot3->setOrder(2);
        $pivot3->save();

        // Reload and test relation (magic property access)
        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $blocs = $loadedContent->getBlocs();

        $I->assertCount(3, $blocs);
        $I->assertInstanceOf(Bloc::class, $blocs[0]);

        // Test ordering (should be bloc2, bloc3, bloc1 based on order 1, 2, 3)
        $I->assertEquals($bloc2->getId(), $blocs[0]->getId());
        $I->assertEquals($bloc3->getId(), $blocs[1]->getId());
        $I->assertEquals($bloc1->getId(), $blocs[2]->getId());
    }

    public function testHasManyContentTags(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        // Create pivot entries
        $pivot1 = new ContentTag();
        $pivot1->setContentId($content->getId());
        $pivot1->setTagId($tag1->getId());
        $pivot1->save();

        $pivot2 = new ContentTag();
        $pivot2->setContentId($content->getId());
        $pivot2->setTagId($tag2->getId());
        $pivot2->save();

        // Reload and test relation (magic property access)
        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $tags = $loadedContent->getTags();

        $I->assertCount(2, $tags);
        $I->assertInstanceOf(Tag::class, $tags[0]);
    }

    public function testHasManyTagBlocs(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $bloc1 = $this->createBloc();
        $bloc2 = $this->createBloc();

        // Create pivot entries with order
        $pivot1 = new TagBloc();
        $pivot1->setTagId($tag->getId());
        $pivot1->setBlocId($bloc1->getId());
        $pivot1->setOrder(2);
        $pivot1->save();

        $pivot2 = new TagBloc();
        $pivot2->setTagId($tag->getId());
        $pivot2->setBlocId($bloc2->getId());
        $pivot2->setOrder(1);
        $pivot2->save();

        // Reload and test relation (magic property access)
        $loadedTag = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $blocs = $loadedTag->getBlocs();

        $I->assertCount(2, $blocs);
        // Test ordering (bloc2 first with order 1)
        $I->assertEquals($bloc2->getId(), $blocs[0]->getId());
        $I->assertEquals($bloc1->getId(), $blocs[1]->getId());
    }

    public function testHasManyTagContents(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $content1 = $this->createContent();
        $content2 = $this->createContent();

        // Create pivot entries
        $pivot1 = new ContentTag();
        $pivot1->setContentId($content1->getId());
        $pivot1->setTagId($tag->getId());
        $pivot1->save();

        $pivot2 = new ContentTag();
        $pivot2->setContentId($content2->getId());
        $pivot2->setTagId($tag->getId());
        $pivot2->save();

        // Reload and test relation (magic property access)
        $loadedTag = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $contents = $loadedTag->getContents();

        $I->assertCount(2, $contents);
        $I->assertInstanceOf(Content::class, $contents[0]);
    }

    public function testHasManyBlocContents(ModelsTester $I): void
    {
        $bloc = $this->createBloc();
        $content1 = $this->createContent();
        $content2 = $this->createContent();

        // Create pivot entries
        $pivot1 = new ContentBloc();
        $pivot1->setContentId($content1->getId());
        $pivot1->setBlocId($bloc->getId());
        $pivot1->setOrder(1);
        $pivot1->save();

        $pivot2 = new ContentBloc();
        $pivot2->setContentId($content2->getId());
        $pivot2->setBlocId($bloc->getId());
        $pivot2->setOrder(2);
        $pivot2->save();

        // Reload and test relation (magic property access)
        $loadedBloc = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $contents = $loadedBloc->getContents();

        $I->assertCount(2, $contents);
        $I->assertInstanceOf(Content::class, $contents[0]);
    }

    public function testHasManyBlocTags(ModelsTester $I): void
    {
        $bloc = $this->createBloc();
        $tag1 = $this->createTag();
        $tag2 = $this->createTag();

        // Create pivot entries
        $pivot1 = new TagBloc();
        $pivot1->setTagId($tag1->getId());
        $pivot1->setBlocId($bloc->getId());
        $pivot1->setOrder(1);
        $pivot1->save();

        $pivot2 = new TagBloc();
        $pivot2->setTagId($tag2->getId());
        $pivot2->setBlocId($bloc->getId());
        $pivot2->setOrder(2);
        $pivot2->save();

        // Reload and test relation (magic property access)
        $loadedBloc = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $tags = $loadedBloc->getTags();

        $I->assertCount(2, $tags);
        $I->assertInstanceOf(Tag::class, $tags[0]);
    }

    // ========================================
    // Test 4 — Polymorphic relation
    // ========================================

    public function testPolymorphicSlugElementContent(ModelsTester $I): void
    {
        $slug = $this->createSlug('/element-content');
        $content = $this->createContent($slug);

        // Test getElement() returns Content
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $element = $loadedSlug->getElement();

        $I->assertInstanceOf(Content::class, $element);
        $I->assertEquals($content->getId(), $element->getId());
    }

    public function testPolymorphicSlugElementTag(ModelsTester $I): void
    {
        $slug = $this->createSlug('/element-tag');
        $tag = $this->createTag($slug);

        // Test getElement() returns Tag
        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $element = $loadedSlug->getElement();

        $I->assertInstanceOf(Tag::class, $element);
        $I->assertEquals($tag->getId(), $element->getId());
    }

    public function testPolymorphicSlugElementOrphan(ModelsTester $I): void
    {
        // Slug without Content or Tag
        $slug = $this->createSlug('/orphan');

        $loadedSlug = Slug::query()->andWhere(['id' => $slug->getId()])->one();
        $element = $loadedSlug->getElement();

        $I->assertNull($element);
    }

    // ========================================
    // Test 5 — Pivot model relations
    // ========================================

    public function testPivotContentBlocRelations(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();

        $pivot = new ContentBloc();
        $pivot->setContentId($content->getId());
        $pivot->setBlocId($bloc->getId());
        $pivot->setOrder(1);
        $pivot->save();

        // Test pivot relations
        $loadedPivot = ContentBloc::query()
            ->andWhere(['contentId' => $content->getId(), 'blocId' => $bloc->getId()])
            ->one();

        $relatedContent = $loadedPivot->getContentQuery()->one();
        $relatedBloc = $loadedPivot->getBlocQuery()->one();

        $I->assertInstanceOf(Content::class, $relatedContent);
        $I->assertInstanceOf(Bloc::class, $relatedBloc);
        $I->assertEquals($content->getId(), $relatedContent->getId());
        $I->assertEquals($bloc->getId(), $relatedBloc->getId());
    }

    public function testPivotTagBlocRelations(ModelsTester $I): void
    {
        $tag = $this->createTag();
        $bloc = $this->createBloc();

        $pivot = new TagBloc();
        $pivot->setTagId($tag->getId());
        $pivot->setBlocId($bloc->getId());
        $pivot->setOrder(1);
        $pivot->save();

        // Test pivot relations
        $loadedPivot = TagBloc::query()
            ->andWhere(['tagId' => $tag->getId(), 'blocId' => $bloc->getId()])
            ->one();

        $relatedTag = $loadedPivot->getTagQuery()->one();
        $relatedBloc = $loadedPivot->getBlocQuery()->one();

        $I->assertInstanceOf(Tag::class, $relatedTag);
        $I->assertInstanceOf(Bloc::class, $relatedBloc);
    }

    public function testPivotContentTagRelations(ModelsTester $I): void
    {
        $content = $this->createContent();
        $tag = $this->createTag();

        $pivot = new ContentTag();
        $pivot->setContentId($content->getId());
        $pivot->setTagId($tag->getId());
        $pivot->save();

        // Test pivot relations
        $loadedPivot = ContentTag::query()
            ->andWhere(['contentId' => $content->getId(), 'tagId' => $tag->getId()])
            ->one();

        $relatedContent = $loadedPivot->getContentQuery()->one();
        $relatedTag = $loadedPivot->getTagQuery()->one();

        $I->assertInstanceOf(Content::class, $relatedContent);
        $I->assertInstanceOf(Tag::class, $relatedTag);
    }

    // ========================================
    // Test 6 — Empty relations
    // ========================================

    public function testEmptyHasOneRelation(ModelsTester $I): void
    {
        // Content without slug
        $content = $this->createContent();

        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $slug = $loadedContent->getSlugQuery()->one();

        $I->assertNull($slug);
    }

    public function testEmptyHasManyRelation(ModelsTester $I): void
    {
        // Content without blocs
        $content = $this->createContent();

        $loadedContent = Content::query()->andWhere(['id' => $content->getId()])->one();
        $blocs = $loadedContent->getBlocs();

        $I->assertIsArray($blocs);
        $I->assertCount(0, $blocs);
    }

    // ========================================
    // Test 7 — Eager loading
    // ========================================

    public function testEagerLoadingHasOne(ModelsTester $I): void
    {
        $slug = $this->createSlug('/eager-one');
        $content = $this->createContent($slug);

        // Load with eager loading
        $loadedContent = Content::query()
            ->with(['slug'])
            ->andWhere(['id' => $content->getId()])
            ->one();

        // Relation should be already loaded
        $I->assertInstanceOf(Slug::class, $loadedContent->relation('slug'));
    }

    public function testEagerLoadingHasMany(ModelsTester $I): void
    {
        $content = $this->createContent();
        $bloc = $this->createBloc();

        $pivot = new ContentBloc();
        $pivot->setContentId($content->getId());
        $pivot->setBlocId($bloc->getId());
        $pivot->setOrder(1);
        $pivot->save();

        // Load with eager loading
        $loadedContent = Content::query()
            ->with(['blocs'])
            ->andWhere(['id' => $content->getId()])
            ->one();

        // Relation should be already loaded
        $blocs = $loadedContent->relation('blocs');
        $I->assertIsArray($blocs);
        $I->assertCount(1, $blocs);
    }

    public function testEagerLoadingMultiple(ModelsTester $I): void
    {
        $slug = $this->createSlug('/eager-multi');
        $type = $this->createType('eager-type');
        $content = $this->createContent($slug, $type);

        // Load with multiple relations
        $loadedContent = Content::query()
            ->with(['slug', 'type'])
            ->andWhere(['id' => $content->getId()])
            ->one();

        $I->assertInstanceOf(Slug::class, $loadedContent->relation('slug'));
        $I->assertInstanceOf(Type::class, $loadedContent->relation('type'));
    }
}
