<?php

declare(strict_types=1);

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\Sitemap;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Services\Xeo\SitemapService;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;

final class SitemapHandlerCest
{
    use DatabaseCestTrait;

    private ?SitemapService $sitemapService = null;

    private function ensureService(): SitemapService
    {
        return $this->sitemapService ??= new SitemapService();
    }

    public function testEmptyReturnsNull(ModelsTester $I): void
    {
        $result = $this->ensureService()->generate('https', 'localhost');
        $I->assertNull($result);
    }

    public function testContentAppearsInSitemap(ModelsTester $I): void
    {
        $this->createContentWithSlug('mon-article', 'Mon Article');

        $result = $this->ensureService()->generate('https', 'localhost');

        $I->assertNotNull($result);
        $I->assertStringContainsString('<urlset', $result);
        $I->assertStringContainsString('mon-article', $result);
        $I->assertStringContainsString('<loc>', $result);
    }

    public function testTagAppearsInSitemap(ModelsTester $I): void
    {
        $this->createTagWithSlug('ma-categorie', 'Ma Catégorie');

        $result = $this->ensureService()->generate('https', 'localhost');

        $I->assertNotNull($result);
        $I->assertStringContainsString('ma-categorie', $result);
    }

    public function testInactiveContentAbsentFromSitemap(ModelsTester $I): void
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath('inactive-page');
        $slug->setActive(true);
        $slug->save();

        $content = new Content();
        $content->setName('Inactive');
        $content->setSlugId($slug->getId());
        $content->setActive(false);
        $content->save();

        $result = $this->ensureService()->generate('https', 'localhost');

        if ($result !== null) {
            $I->assertStringNotContainsString('inactive-page', $result);
        } else {
            $I->assertNull($result);
        }
    }

    public function testValidXmlStructure(ModelsTester $I): void
    {
        $this->createContentWithSlug('page-a', 'Page A');
        $this->createContentWithSlug('page-b', 'Page B');

        $result = $this->ensureService()->generate('https', 'localhost');

        $I->assertNotNull($result);
        $xml = simplexml_load_string($result);
        $I->assertNotFalse($xml, 'Sitemap must be valid XML');

        $urls = $xml->url;
        $I->assertGreaterThanOrEqual(2, count($urls));
    }

    private function createContentWithSlug(string $path, string $name): Content
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath($path);
        $slug->setActive(true);
        $slug->save();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->setActive(true);
        $sitemap->save();

        $content = new Content();
        $content->setName($name);
        $content->setSlugId($slug->getId());
        $content->setActive(true);
        $content->save();

        return $content;
    }

    private function createTagWithSlug(string $path, string $name): Tag
    {
        $slug = new Slug();
        $slug->setHostId(1);
        $slug->setPath($path);
        $slug->setActive(true);
        $slug->save();

        $sitemap = new Sitemap();
        $sitemap->setSlugId($slug->getId());
        $sitemap->setActive(true);
        $sitemap->save();

        $tag = new Tag();
        $tag->setName($name);
        $tag->setSlugId($slug->getId());
        $tag->setActive(true);
        $tag->save();

        return $tag;
    }
}
