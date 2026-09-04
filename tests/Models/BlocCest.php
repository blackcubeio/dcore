<?php

declare(strict_types=1);

/**
 * BlocCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Bloc;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class BlocCest
{
    use DatabaseCestTrait;

    private function createElasticSchema(string $schema = '{"type":"object"}'): ElasticSchema
    {
        $elasticSchema = new ElasticSchema();
        $elasticSchema->setName('test-schema-'.uniqid());
        $elasticSchema->setSchema($schema);
        $elasticSchema->save();
        return $elasticSchema;
    }

    private function createBlocContentSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-bloc-content-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'title' => 'Titre'],
                'content' => ['type' => 'string', 'title' => 'Contenu'],
                'position' => ['type' => 'integer', 'title' => 'Position'],
            ],
            'required' => ['title'],
        ]));
        $schema->save();
        return $schema;
    }

    private function createBlocMediaSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-bloc-media-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string', 'format' => 'uri', 'title' => 'URL'],
                'alt' => ['type' => 'string', 'title' => 'Texte alternatif'],
                'width' => ['type' => 'integer', 'title' => 'Largeur'],
                'height' => ['type' => 'integer', 'title' => 'Hauteur'],
            ],
        ]));
        $schema->save();
        return $schema;
    }

    private function createBlocNestedSchema(): ElasticSchema
    {
        $schema = new ElasticSchema();
        $schema->setName('test-bloc-nested-'.uniqid());
        $schema->setSchema(json_encode([
            'type' => 'object',
            'properties' => [
                'image' => [
                    'type' => 'object',
                    'title' => 'Image',
                    'properties' => [
                        'src' => ['type' => 'string', 'title' => 'Source'],
                        'alt' => ['type' => 'string', 'title' => 'Alt'],
                    ],
                ],
                'link' => [
                    'type' => 'object',
                    'title' => 'Lien',
                    'properties' => [
                        'href' => ['type' => 'string', 'title' => 'URL'],
                        'target' => ['type' => 'string', 'title' => 'Target'],
                    ],
                ],
            ],
        ]));
        $schema->save();
        return $schema;
    }

    public function testInsert(ModelsTester $I): void
    {
        $schema = $this->createElasticSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->setActive(true);

        $bloc->save();

        $I->assertNotNull($bloc->getId());
        $I->assertTrue($bloc->isActive());
        $I->assertNotNull($bloc->getDateCreate());
    }

    public function testWithElasticProperties(ModelsTester $I): void
    {
        $jsonSchema = json_encode([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
            ],
        ]);
        $schema = $this->createElasticSchema($jsonSchema);

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->title = 'Hello';
        $bloc->content = 'World';
        $bloc->save();

        $found = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();

        $I->assertEquals('Hello', $found->title);
        $I->assertEquals('World', $found->content);
    }

    public function testUpdate(ModelsTester $I): void
    {
        $schema = $this->createElasticSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $bloc->setActive(true);
        $bloc->save();

        $I->assertTrue($bloc->isActive());
        $I->assertNotNull($bloc->getDateUpdate());
    }

    public function testDelete(ModelsTester $I): void
    {
        $schema = $this->createElasticSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $id = $bloc->getId();
        $bloc->delete();

        $found = Bloc::query()->andWhere(['id' => $id])->one();
        $I->assertNull($found);
    }


    public function testElasticAssociateSchema(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->setActive(true);
        $bloc->save();

        $I->assertEquals($schema->getId(), $bloc->elasticSchemaId);

        $found = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $I->assertEquals($schema->getId(), $found->elasticSchemaId);
    }

    public function testElasticDynamicProperties(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->title = 'My Bloc';
        $bloc->content = 'Bloc content';
        $bloc->position = 1;
        $bloc->save();

        $I->assertEquals('My Bloc', $bloc->title);
        $I->assertEquals('Bloc content', $bloc->content);
        $I->assertEquals(1, $bloc->position);

        $found = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $I->assertEquals('My Bloc', $found->title);
        $I->assertEquals('Bloc content', $found->content);
        $I->assertEquals(1, $found->position);
    }

    public function testElasticNestedObject(ModelsTester $I): void
    {
        $schema = $this->createBlocNestedSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->image = ['src' => '/img/photo.jpg', 'alt' => 'Photo'];
        $bloc->link = ['href' => 'https://example.com', 'target' => '_blank'];
        $bloc->save();

        $I->assertEquals(['src' => '/img/photo.jpg', 'alt' => 'Photo'], $bloc->image);
        $I->assertEquals(['href' => 'https://example.com', 'target' => '_blank'], $bloc->link);

        $found = Bloc::query()->andWhere(['id' => $bloc->getId()])->one();
        $I->assertEquals(['src' => '/img/photo.jpg', 'alt' => 'Photo'], $found->image);
        $I->assertEquals(['href' => 'https://example.com', 'target' => '_blank'], $found->link);
    }

    public function testElasticGetValues(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->title = 'Test Values';
        $bloc->content = 'Some content';
        $bloc->position = 5;
        $bloc->save();

        $values = $bloc->getElasticValues();

        $I->assertIsArray($values);
        $I->assertEquals('Test Values', $values['title']);
        $I->assertEquals('Some content', $values['content']);
        $I->assertEquals(5, $values['position']);
    }

    public function testElasticSchemaChangeResetsProperties(ModelsTester $I): void
    {
        $contentSchema = $this->createBlocContentSchema();
        $mediaSchema = $this->createBlocMediaSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $contentSchema->getId();
        $bloc->title = 'Old Title';
        $bloc->content = 'Old Content';
        $bloc->save();

        $blocId = $bloc->getId();

        $bloc->elasticSchemaId = $mediaSchema->getId();
        $bloc->save();

        Bloc::clearSchemaCache();
        $reloaded = Bloc::query()->andWhere(['id' => $blocId])->one();

        $reloaded->url = 'https://example.com/image.jpg';
        $reloaded->alt = 'An image';
        $reloaded->save();

        $found = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertEquals('https://example.com/image.jpg', $found->url);
        $I->assertEquals('An image', $found->alt);
    }

    public function testElasticProtectedExtrasColumn(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->title = 'Test';
        $bloc->save();

        $I->expectThrowable(\Error::class, function () use ($bloc) {
            $bloc->_extras = '{"hacked": true}';
        });
    }

    public function testElasticPersistence(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->setActive(true);
        $bloc->title = 'Persistent Bloc';
        $bloc->content = 'This should persist';
        $bloc->position = 10;
        $bloc->save();

        $blocId = $bloc->getId();

        Bloc::clearSchemaCache();

        $found = Bloc::query()->andWhere(['id' => $blocId])->one();

        $I->assertNotNull($found);
        $I->assertEquals('Persistent Bloc', $found->title);
        $I->assertEquals('This should persist', $found->content);
        $I->assertEquals(10, $found->position);
        $I->assertTrue($found->isActive());
    }

    public function testElasticUnknownPropertyThrows(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $I->expectThrowable(\Throwable::class, function () use ($bloc) {
            $bloc->unknownField = 'value';
        });
    }

    public function testElasticNullSchemaAllowsNoExtras(ModelsTester $I): void
    {
        $emptySchema = $this->createElasticSchema('{"type":"object","properties":{}}');

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $emptySchema->getId();
        $bloc->setActive(true);
        $bloc->save();

        $I->expectThrowable(\Throwable::class, function () use ($bloc) {
            $bloc->anyProperty = 'value';
        });
    }

    public function testElasticUpdateProperties(ModelsTester $I): void
    {
        $schema = $this->createBlocContentSchema();

        $bloc = new Bloc();
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->title = 'Initial Title';
        $bloc->content = 'Initial Content';
        $bloc->position = 1;
        $bloc->save();

        $blocId = $bloc->getId();

        $found = Bloc::query()->andWhere(['id' => $blocId])->one();
        $found->title = 'Updated Title';
        $found->position = 2;
        $found->save();

        $updated = Bloc::query()->andWhere(['id' => $blocId])->one();
        $I->assertEquals('Updated Title', $updated->title);
        $I->assertEquals('Initial Content', $updated->content);
        $I->assertEquals(2, $updated->position);
    }
}
