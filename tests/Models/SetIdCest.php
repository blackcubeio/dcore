<?php

declare(strict_types=1);

/**
 * SetIdCest.php
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
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\ActiveRecord\Elastic\ElasticSchema;
use LogicException;

/**
 * Tests for setId() behavior on Content, Tag, Bloc.
 *
 * setId() rules:
 * - New record (no ID set): setId(x) → OK
 * - Existing record with same ID: setId(sameId) → OK (idempotent)
 * - Existing record with different ID: setId(differentId) → LogicException
 */
final class SetIdCest
{
    use DatabaseCestTrait;

    // ========================================
    // Content tests
    // ========================================

    public function testContentSetIdOnNewRecord(ModelsTester $I): void
    {
        $I->wantTo('verify Content setId() works on new record');

        $content = new Content();
        $content->setId(123);

        $I->assertEquals(123, $content->getId());
    }

    public function testContentSetIdIdempotent(ModelsTester $I): void
    {
        $I->wantTo('verify Content setId() is idempotent with same ID');

        $content = new Content();
        $content->setName('Test Content');
        $content->setLanguageId('fr');
        $content->save();

        $savedId = $content->getId();
        $I->assertNotNull($savedId);

        // Setting same ID should work
        $content->setId($savedId);

        $I->assertEquals($savedId, $content->getId());
    }

    public function testContentSetIdThrowsOnDifferentId(ModelsTester $I): void
    {
        $I->wantTo('verify Content setId() throws LogicException when changing ID');

        $content = new Content();
        $content->setName('Test Content');
        $content->setLanguageId('fr');
        $content->save();

        $savedId = $content->getId();
        $I->assertNotNull($savedId);

        $I->expectThrowable(LogicException::class, function () use ($content, $savedId) {
            $content->setId($savedId + 1);
        });
    }

    // ========================================
    // Tag tests
    // ========================================

    public function testTagSetIdOnNewRecord(ModelsTester $I): void
    {
        $I->wantTo('verify Tag setId() works on new record');

        $tag = new Tag();
        $tag->setId(456);

        $I->assertEquals(456, $tag->getId());
    }

    public function testTagSetIdIdempotent(ModelsTester $I): void
    {
        $I->wantTo('verify Tag setId() is idempotent with same ID');

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->save();

        $savedId = $tag->getId();
        $I->assertNotNull($savedId);

        // Setting same ID should work
        $tag->setId($savedId);

        $I->assertEquals($savedId, $tag->getId());
    }

    public function testTagSetIdThrowsOnDifferentId(ModelsTester $I): void
    {
        $I->wantTo('verify Tag setId() throws LogicException when changing ID');

        $tag = new Tag();
        $tag->setName('Test Tag');
        $tag->save();

        $savedId = $tag->getId();
        $I->assertNotNull($savedId);

        $I->expectThrowable(LogicException::class, function () use ($tag, $savedId) {
            $tag->setId($savedId + 1);
        });
    }

    // ========================================
    // Bloc tests
    // ========================================

    public function testBlocSetIdOnNewRecord(ModelsTester $I): void
    {
        $I->wantTo('verify Bloc setId() works on new record');

        $bloc = new Bloc();
        $bloc->setId(789);

        $I->assertEquals(789, $bloc->getId());
    }

    public function testBlocSetIdIdempotent(ModelsTester $I): void
    {
        $I->wantTo('verify Bloc setId() is idempotent with same ID');

        // Create ElasticSchema first (required for Bloc)
        $schema = new ElasticSchema();
        $schema->setName('TestSchema');
        $schema->setSchema('{"type":"object","properties":{}}');
        $schema->save();

        $bloc = new Bloc();
        $bloc->setActive(true);
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $savedId = $bloc->getId();
        $I->assertNotNull($savedId);

        // Setting same ID should work
        $bloc->setId($savedId);

        $I->assertEquals($savedId, $bloc->getId());
    }

    public function testBlocSetIdThrowsOnDifferentId(ModelsTester $I): void
    {
        $I->wantTo('verify Bloc setId() throws LogicException when changing ID');

        // Create ElasticSchema first (required for Bloc)
        $schema = new ElasticSchema();
        $schema->setName('TestSchema2');
        $schema->setSchema('{"type":"object","properties":{}}');
        $schema->save();

        $bloc = new Bloc();
        $bloc->setActive(true);
        $bloc->elasticSchemaId = $schema->getId();
        $bloc->save();

        $savedId = $bloc->getId();
        $I->assertNotNull($savedId);

        $I->expectThrowable(LogicException::class, function () use ($bloc, $savedId) {
            $bloc->setId($savedId + 1);
        });
    }
}
