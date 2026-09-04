<?php

declare(strict_types=1);

/**
 * MapperServiceCest.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Author;
use Blackcube\Dcore\Models\Bloc;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ElasticSchema;
use Blackcube\Dcore\Models\Language;
use Blackcube\Dcore\Models\LlmMenu;
use Blackcube\Dcore\Models\Menu;
use Blackcube\Dcore\Models\Parameter;
use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Models\Xeo;
use Blackcube\Dcore\Services\FileService;
use Blackcube\Dcore\Services\MapperService;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ExportImport\TestFileProvider;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use DateTimeImmutable;

/**
 * Tests the attribute-driven mapping (MapperService) against a real DB:
 * - export of a full Content (slug + bloc + tag + author) matches the persisted data,
 *   field whitelists on tags/authors honoured;
 * - import (create + update) feeds the Importable setters and persists.
 */
final class MapperServiceCest
{
    use DatabaseCestTrait;

    private function mapper(): MapperService
    {
        return new MapperService(new FileService(new TestFileProvider()));
    }

    private function createLanguage(): Language
    {
        $language = Language::query()->andWhere(['id' => 'fr'])->one();
        if ($language === null) {
            $language = new Language();
            $language->setId('fr');
            $language->setName('Français');
            $language->setActive(true);
            $language->setMain(true);
            $language->save();
        }

        return $language;
    }

    private function createType(): Type
    {
        $type = new Type();
        $type->setName('type-'.uniqid());
        $type->save();

        return $type;
    }

    private function createSlug(): Slug
    {
        $slug = new Slug();
        $slug->setPath('/page-'.uniqid());
        $slug->save();

        return $slug;
    }

    private function createBloc(): Bloc
    {
        $schema = new ElasticSchema();
        $schema->setName('schema-'.uniqid());
        $schema->setSchema('{"type":"object"}');
        $schema->save();

        $bloc = new Bloc();
        $bloc->setElasticSchemaId($schema->getId());
        $bloc->save();

        return $bloc;
    }

    private function createAuthor(): Author
    {
        $author = new Author();
        $author->setFirstname('John');
        $author->setLastname('Doe');
        $author->setEmail('john-'.uniqid().'@example.com');
        $author->setActive(true);
        $author->save();

        return $author;
    }

    private function createTag(): Tag
    {
        $tag = new Tag();
        $tag->setName('tag-'.uniqid());
        $tag->save();

        return $tag;
    }

    public function exportFullContentMatchesDb(ModelsTester $I): void
    {
        $I->wantTo('export a full Content (slug+bloc+tag+author) and assert it matches the persisted data');

        $language = $this->createLanguage();
        $type = $this->createType();
        $slug = $this->createSlug();

        $content = new Content();
        $content->setName('Ma page');
        $content->setLanguageId($language->getId());
        $content->setTypeId($type->getId());
        $content->setSlugId($slug->getId());
        $content->setActive(true);
        $content->save();

        $bloc = $this->createBloc();
        $content->attachBloc($bloc);

        $tag = $this->createTag();
        $content->attachTag($tag);

        $author = $this->createAuthor();
        $content->attachAuthor($author);

        $reloaded = Content::query()->andWhere(['id' => $content->getId()])->one();
        $exported = $this->mapper()->export($reloaded);

        $I->assertEquals('content', $exported['elementType']);
        $I->assertEquals($content->getId(), $exported['id']);
        $I->assertEquals('Ma page', $exported['name']);
        $I->assertEquals($language->getId(), $exported['languageId']);
        $I->assertEquals($type->getId(), $exported['typeId']);
        $I->assertTrue($exported['active']);

        $I->assertIsArray($exported['slug'], 'slug exported as a nested object');
        $I->assertNotEmpty($exported['slug']);

        $I->assertCount(1, $exported['blocs'], 'one attached bloc exported');
        $I->assertEquals($bloc->getElasticSchemaId(), $exported['blocs'][0]['elasticSchemaId']);

        $I->assertCount(1, $exported['tags']);
        $I->assertEquals($tag->getId(), $exported['tags'][0]['id']);
        $I->assertEquals($tag->getName(), $exported['tags'][0]['name']);
        $I->assertEquals(['id', 'name'], array_keys($exported['tags'][0]), 'tag whitelist honoured');

        $I->assertCount(1, $exported['authors']);
        $I->assertEquals($author->getId(), $exported['authors'][0]['id']);
        $I->assertEquals('John', $exported['authors'][0]['firstname']);
        $I->assertEquals('Doe', $exported['authors'][0]['lastname']);
        $I->assertEquals(['id', 'firstname', 'lastname'], array_keys($exported['authors'][0]), 'author whitelist honoured');
    }

    public function exportTagMatchesDb(ModelsTester $I): void
    {
        $I->wantTo('export a Tag and assert elementType + scalars + slug match the persisted data');

        $slug = $this->createSlug();

        $tag = new Tag();
        $tag->setName('Mon tag');
        $tag->setSlugId($slug->getId());
        $tag->setActive(true);
        $tag->save();

        $reloaded = Tag::query()->andWhere(['id' => $tag->getId()])->one();
        $exported = $this->mapper()->export($reloaded);

        $I->assertEquals('tag', $exported['elementType']);
        $I->assertEquals($tag->getId(), $exported['id']);
        $I->assertEquals('Mon tag', $exported['name']);
        $I->assertTrue($exported['active']);
        $I->assertIsArray($exported['slug'], 'slug exported as a nested object');
        $I->assertNotEmpty($exported['slug']);
    }

    public function importCreatesContentFromScalars(ModelsTester $I): void
    {
        $I->wantTo('import (create) feeds the Importable setters of a new Content and persists');

        $language = $this->createLanguage();
        $type = $this->createType();

        $content = new Content();
        $this->mapper()->import($content, [
            'name' => 'Created by import',
            'languageId' => $language->getId(),
            'typeId' => $type->getId(),
            'dateStart' => '2026-03-15 08:00:00',
        ]);
        $I->assertInstanceOf(DateTimeImmutable::class, $content->getDateStart(), 'dateStart fed through DateFormatter');
        $I->assertEquals('2026-03-15 08:00:00', $content->getDateStart()->format('Y-m-d H:i:s'));

        $content->save();

        $reloaded = Content::query()->andWhere(['id' => $content->getId()])->one();
        $I->assertEquals('Created by import', $reloaded->getName());
        $I->assertEquals($language->getId(), $reloaded->getLanguageId());
        $I->assertEquals($type->getId(), $reloaded->getTypeId());
    }

    public function importUpdatesExistingContent(ModelsTester $I): void
    {
        $I->wantTo('import (update) overwrites the Importable fields of an existing Content, leaves the rest');

        $language = $this->createLanguage();

        $content = new Content();
        $content->setName('Before');
        $content->setLanguageId($language->getId());
        $content->save();
        $id = $content->getId();

        $existing = Content::query()->andWhere(['id' => $id])->one();
        $this->mapper()->import($existing, ['name' => 'After']);
        $existing->save();

        $reloaded = Content::query()->andWhere(['id' => $id])->one();
        $I->assertEquals('After', $reloaded->getName(), 'name overwritten by import');
        $I->assertEquals($language->getId(), $reloaded->getLanguageId(), 'untouched field kept');
    }

    public function describeCarriesTypesAndDescriptions(ModelsTester $I): void
    {
        $I->wantTo('check describe() derives types from the signatures and descriptions from the attributes');

        $shape = $this->mapper()->describe(Content::class);

        $I->assertTrue($shape['fields']['name']['read'] === true);
        $I->assertTrue($shape['fields']['name']['write'] === true);
        $I->assertSame('?string', $shape['fields']['name']['type']);
        $I->assertSame('Display name.', $shape['fields']['name']['description']);
        $I->assertSame('bool', $shape['fields']['active']['type']);
        $I->assertSame('?datetime', $shape['fields']['dateStart']['type']);
        $I->assertTrue($shape['relations']['slug']['read'] === true);
        $I->assertNotEmpty($shape['relations']['slug']['description']);
    }

    public function describeLeavesNoUndocumentedMember(ModelsTester $I): void
    {
        $I->wantTo('check every exposed field/relation of the mapped models carries a type and a description');

        $modelClasses = [
            Author::class,
            Bloc::class,
            Content::class,
            ElasticSchema::class,
            Language::class,
            LlmMenu::class,
            Menu::class,
            Parameter::class,
            Sitemap::class,
            Slug::class,
            Tag::class,
            Type::class,
            Xeo::class,
        ];
        foreach ($modelClasses as $modelClass) {
            $modelShape = $this->mapper()->describe($modelClass);
            foreach ($modelShape['fields'] as $fieldName => $field) {
                $I->assertNotEmpty($field['type'], $modelClass.' field '.$fieldName.' must carry a type');
                $I->assertNotEmpty($field['description'], $modelClass.' field '.$fieldName.' must carry a description');
            }
            foreach ($modelShape['relations'] as $relationName => $relation) {
                $I->assertNotEmpty($relation['description'], $modelClass.' relation '.$relationName.' must carry a description');
            }
        }
    }
}
