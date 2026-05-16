<?php

declare(strict_types=1);

/**
 * SearchHelperCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Helpers\SearchHelper;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Functional tests for SearchHelper.
 */
final class SearchHelperCest
{
    use DatabaseCestTrait;

    private static bool $schemasCreated = false;
    private static int $pageSchemaId;
    private static int $blocSchemaId;
    private static int $fileSchemaId;

    private function ensureSchemas(): void
    {
        if (self::$schemasCreated) {
            return;
        }

        $pageSchema = new ElasticSchema();
        $pageSchema->setName('test-page-search');
        $pageSchema->setKind(ElasticSchemaKind::Page);
        $pageSchema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'title' => 'Title',
                ],
                'description' => [
                    'type' => 'string',
                    'format' => 'wysiwyg',
                    'title' => 'Description',
                ],
            ],
        ]));
        $pageSchema->save();
        self::$pageSchemaId = $pageSchema->getId();

        $blocSchema = new ElasticSchema();
        $blocSchema->setName('test-bloc-search');
        $blocSchema->setKind(ElasticSchemaKind::Bloc);
        $blocSchema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'blocTitle' => [
                    'type' => 'string',
                    'title' => 'Bloc Title',
                ],
                'blocBody' => [
                    'type' => 'string',
                    'format' => 'textarea',
                    'title' => 'Bloc Body',
                ],
            ],
        ]));
        $blocSchema->save();
        self::$blocSchemaId = $blocSchema->getId();

        $fileSchema = new ElasticSchema();
        $fileSchema->setName('test-file-search');
        $fileSchema->setKind(ElasticSchemaKind::Page);
        $fileSchema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'image' => [
                    'type' => 'string',
                    'format' => 'file',
                    'fileType' => 'png,jpg',
                    'title' => 'Image',
                ],
                'caption' => [
                    'type' => 'string',
                    'title' => 'Caption',
                ],
            ],
        ]));
        $fileSchema->save();
        self::$fileSchemaId = $fileSchema->getId();

        self::$schemasCreated = true;
    }

    // ========================================
    // contentQuery
    // ========================================

    public function testContentQueryFindsByName(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = $this->createContent('Blackcube Unicorn Article');
        $this->createContent('Other Content');

        $results = SearchHelper::contentQuery('Unicorn')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    public function testContentQueryFindsByElasticTextField(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = new Content();
        $content->setName('Plain Name');
        $content->elasticSchemaId = self::$pageSchemaId;
        $content->title = 'Zephyr Quantum Title';
        $content->description = 'Some description';
        $content->save();

        $this->createContent('Unrelated Content');

        $results = SearchHelper::contentQuery('Zephyr Quantum')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    public function testContentQueryFindsByWysiwygField(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = new Content();
        $content->setName('Plain Name');
        $content->elasticSchemaId = self::$pageSchemaId;
        $content->title = 'Normal Title';
        $content->description = '<p>Nebula Prism paragraph</p>';
        $content->save();

        $results = SearchHelper::contentQuery('Nebula Prism')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    public function testContentQueryFindsByBlocElasticField(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = $this->createContent('Parent Content');

        $bloc = new Bloc();
        $bloc->setElasticSchemaId(self::$blocSchemaId);
        $bloc->blocTitle = 'Vortex Helix Heading';
        $bloc->blocBody = 'Regular body text';
        $bloc->save();

        $content->attachBloc($bloc);

        $results = SearchHelper::contentQuery('Vortex Helix')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($content->getId(), $results[0]->getId());
    }

    public function testContentQueryDoesNotMatchFileFields(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = new Content();
        $content->setName('File Content');
        $content->elasticSchemaId = self::$fileSchemaId;
        $content->image = '/uploads/photon-crystal.jpg';
        $content->caption = 'Photon Crystal Caption';
        $content->save();

        // Should NOT find by file field value
        $results = SearchHelper::contentQuery('photon-crystal.jpg')->all();
        $I->assertCount(0, $results);

        // Should find by text field value
        $results = SearchHelper::contentQuery('Photon Crystal Caption')->all();
        $I->assertCount(1, $results);
    }

    public function testContentQueryReturnsEmptyForNoMatch(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $this->createContent('Existing Content');

        $results = SearchHelper::contentQuery('Xylophone Platypus')->all();

        $I->assertCount(0, $results);
    }

    public function testContentQueryFindsByNameAndElasticCombined(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $contentByName = $this->createContent('Aurora Borealis Page');

        $contentByElastic = new Content();
        $contentByElastic->setName('Plain Page');
        $contentByElastic->elasticSchemaId = self::$pageSchemaId;
        $contentByElastic->title = 'Aurora Borealis Title';
        $contentByElastic->description = 'Something else';
        $contentByElastic->save();

        $this->createContent('Unrelated');

        $results = SearchHelper::contentQuery('Aurora Borealis')->all();

        $ids = array_map(fn($r) => $r->getId(), $results);
        $I->assertCount(2, $results);
        $I->assertContains($contentByName->getId(), $ids);
        $I->assertContains($contentByElastic->getId(), $ids);
    }

    // ========================================
    // tagQuery
    // ========================================

    public function testTagQueryFindsByName(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $tag = $this->createTag('Obsidian Falcon Tag');
        $this->createTag('Other Tag');

        $results = SearchHelper::tagQuery('Obsidian Falcon')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($tag->getId(), $results[0]->getId());
    }

    public function testTagQueryFindsByElasticTextField(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $tag = new Tag();
        $tag->setName('Plain Tag');
        $tag->elasticSchemaId = self::$pageSchemaId;
        $tag->title = 'Crimson Meridian Heading';
        $tag->description = 'Some tag description';
        $tag->save();

        $this->createTag('Unrelated Tag');

        $results = SearchHelper::tagQuery('Crimson Meridian')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($tag->getId(), $results[0]->getId());
    }

    public function testTagQueryFindsByBlocElasticField(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $tag = $this->createTag('Parent Tag');

        $bloc = new Bloc();
        $bloc->setElasticSchemaId(self::$blocSchemaId);
        $bloc->blocTitle = 'Sapphire Vertex Title';
        $bloc->blocBody = 'Regular body';
        $bloc->save();

        $tag->attachBloc($bloc);

        $results = SearchHelper::tagQuery('Sapphire Vertex')->all();

        $I->assertCount(1, $results);
        $I->assertEquals($tag->getId(), $results[0]->getId());
    }

    public function testTagQueryReturnsEmptyForNoMatch(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $this->createTag('Existing Tag');

        $results = SearchHelper::tagQuery('Xylophone Platypus')->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Cross-entity independence
    // ========================================

    public function testContentAndTagQueriesAreIndependent(ModelsTester $I): void
    {
        $this->ensureSchemas();
        $content = new Content();
        $content->setName('Titanium Oxide Content');
        $content->elasticSchemaId = self::$pageSchemaId;
        $content->title = 'Titanium Oxide Title';
        $content->description = 'Content desc';
        $content->save();

        $tag = new Tag();
        $tag->setName('Titanium Oxide Tag');
        $tag->elasticSchemaId = self::$pageSchemaId;
        $tag->title = 'Titanium Oxide Tag Title';
        $tag->description = 'Tag desc';
        $tag->save();

        $contentResults = SearchHelper::contentQuery('Titanium Oxide')->all();
        $tagResults = SearchHelper::tagQuery('Titanium Oxide')->all();

        $I->assertCount(1, $contentResults);
        $I->assertCount(1, $tagResults);
        $I->assertEquals($content->getId(), $contentResults[0]->getId());
        $I->assertEquals($tag->getId(), $tagResults[0]->getId());
    }

    // ========================================
    // Helpers
    // ========================================

    private function createContent(string $name = 'Test Content'): Content
    {
        $content = new Content();
        $content->setName($name);
        $content->save();
        return $content;
    }

    private function createTag(string $name = 'Test Tag'): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->save();
        return $tag;
    }
}
