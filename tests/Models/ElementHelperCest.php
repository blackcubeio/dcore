<?php

declare(strict_types=1);

/**
 * ElementHelperCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\ScopableQuery;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Models\Type;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

/**
 * Tests for Element with DB access.
 */
final class ElementHelperCest
{
    use DatabaseCestTrait;

    private static bool $dataCreated = false;
    private static int $contentId;
    private static int $tagId;
    private static int $slugContentId;
    private static int $slugTagId;

    private ?Content $content = null;
    private ?Tag $tag = null;
    private ?Slug $slugContent = null;
    private ?Slug $slugTag = null;

    private function ensureTestData(): void
    {
        if (self::$dataCreated) {
            $this->content = Content::query()->andWhere(['id' => self::$contentId])->one();
            $this->tag = Tag::query()->andWhere(['id' => self::$tagId])->one();
            $this->slugContent = Slug::query()->andWhere(['id' => self::$slugContentId])->one();
            $this->slugTag = Slug::query()->andWhere(['id' => self::$slugTagId])->one();
            return;
        }

        $type = new Type();
        $type->setName('Test Type');
        $type->setHandler('test');
        $type->save();

        $this->slugContent = new Slug();
        $this->slugContent->setHostId(1);
        $this->slugContent->setPath('test-content');
        $this->slugContent->setActive(true);
        $this->slugContent->save();
        self::$slugContentId = $this->slugContent->getId();

        $this->content = new Content();
        $this->content->setName('Test Content');
        $this->content->setSlugId($this->slugContent->getId());
        $this->content->setTypeId($type->getId());
        $this->content->setActive(true);
        $this->content->save();
        self::$contentId = $this->content->getId();

        $this->slugTag = new Slug();
        $this->slugTag->setHostId(1);
        $this->slugTag->setPath('test-tag');
        $this->slugTag->setActive(true);
        $this->slugTag->save();
        self::$slugTagId = $this->slugTag->getId();

        $this->tag = new Tag();
        $this->tag->setName('Test Tag');
        $this->tag->setSlugId($this->slugTag->getId());
        $this->tag->setTypeId($type->getId());
        $this->tag->setActive(true);
        $this->tag->save();
        self::$tagId = $this->tag->getId();

        self::$dataCreated = true;
    }

    // ========================================
    // createFromRoute + getModel
    // ========================================

    public function createFromRouteLoadsContent(ModelsTester $I): void
    {
        $I->wantTo('verify createFromRoute() + getModel() loads Content');
        $this->ensureTestData();

        $element = Element::createFromRoute('dcore-c-' . $this->content->getId());
        $model = $element->getModel();

        $I->assertInstanceOf(Content::class, $model);
        $I->assertEquals($this->content->getId(), $model->getId());
        $I->assertEquals('Test Content', $model->getName());
    }

    public function createFromRouteLoadsTag(ModelsTester $I): void
    {
        $I->wantTo('verify createFromRoute() + getModel() loads Tag');
        $this->ensureTestData();

        $element = Element::createFromRoute('dcore-t-' . $this->tag->getId());
        $model = $element->getModel();

        $I->assertInstanceOf(Tag::class, $model);
        $I->assertEquals($this->tag->getId(), $model->getId());
        $I->assertEquals('Test Tag', $model->getName());
    }

    public function createFromRouteReturnsNullModelForNonExistent(ModelsTester $I): void
    {
        $I->wantTo('verify createFromRoute() returns null model for non-existent ID');

        $element = Element::createFromRoute('dcore-c-999999');
        $I->assertNull($element->getModel());
    }

    public function createFromRouteReturnsNullForInvalid(ModelsTester $I): void
    {
        $I->wantTo('verify createFromRoute() returns null for invalid route');

        $I->assertNull(Element::createFromRoute('invalid-route'));
    }

    // ========================================
    // createFromModel
    // ========================================

    public function createFromModelContent(ModelsTester $I): void
    {
        $I->wantTo('verify createFromModel() with Content');
        $this->ensureTestData();

        $element = Element::createFromModel($this->content);

        $I->assertEquals('content', $element->getType());
        $I->assertEquals($this->content->getId(), $element->getId());
        $I->assertSame($this->content, $element->getModel());
    }

    public function createFromModelTag(ModelsTester $I): void
    {
        $I->wantTo('verify createFromModel() with Tag');
        $this->ensureTestData();

        $element = Element::createFromModel($this->tag);

        $I->assertEquals('tag', $element->getType());
        $I->assertEquals($this->tag->getId(), $element->getId());
        $I->assertSame($this->tag, $element->getModel());
    }

    // ========================================
    // createFromSlug
    // ========================================

    public function createFromSlugContent(ModelsTester $I): void
    {
        $I->wantTo('verify createFromSlug() with content slug');
        $this->ensureTestData();

        $element = Element::createFromSlug($this->slugContent);

        $I->assertNotNull($element);
        $I->assertEquals('content', $element->getType());
        $I->assertEquals($this->content->getId(), $element->getId());
    }

    public function createFromSlugTag(ModelsTester $I): void
    {
        $I->wantTo('verify createFromSlug() with tag slug');
        $this->ensureTestData();

        $element = Element::createFromSlug($this->slugTag);

        $I->assertNotNull($element);
        $I->assertEquals('tag', $element->getType());
        $I->assertEquals($this->tag->getId(), $element->getId());
    }

    public function createFromSlugOrphan(ModelsTester $I): void
    {
        $I->wantTo('verify createFromSlug() returns null for orphan slug');

        $orphan = new Slug();
        $orphan->setHostId(1);
        $orphan->setPath('orphan');
        $orphan->setActive(true);
        $orphan->save();

        $I->assertNull(Element::createFromSlug($orphan));
    }

    // ========================================
    // getModelQuery
    // ========================================

    public function getModelQueryReturnsScopedQuery(ModelsTester $I): void
    {
        $I->wantTo('verify getModelQuery() returns ScopedQuery');
        $this->ensureTestData();

        $element = Element::createFromRoute('dcore-c-' . $this->content->getId());
        $query = $element->getModelQuery();

        $I->assertInstanceOf(ScopableQuery::class, $query);

        $model = $query->one();
        $I->assertInstanceOf(Content::class, $model);
        $I->assertEquals($this->content->getId(), $model->getId());
    }

    // ========================================
    // toRoute round-trip
    // ========================================

    public function roundTripContent(ModelsTester $I): void
    {
        $I->wantTo('verify round-trip: content → route → content');
        $this->ensureTestData();

        $route = Element::createFromModel($this->content)->toRoute();
        $loaded = Element::createFromRoute($route)->getModel();

        $I->assertInstanceOf(Content::class, $loaded);
        $I->assertEquals($this->content->getId(), $loaded->getId());
    }

    public function roundTripTag(ModelsTester $I): void
    {
        $I->wantTo('verify round-trip: tag → route → tag');
        $this->ensureTestData();

        $route = Element::createFromModel($this->tag)->toRoute();
        $loaded = Element::createFromRoute($route)->getModel();

        $I->assertInstanceOf(Tag::class, $loaded);
        $I->assertEquals($this->tag->getId(), $loaded->getId());
    }
}
