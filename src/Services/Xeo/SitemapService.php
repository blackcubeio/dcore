<?php

declare(strict_types=1);

/**
 * SitemapService.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services\Xeo;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\GlobalXeo;
use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;

/**
 * Generates sitemap.xml content from CMS entities and GlobalXeo data.
 * Returns null if no URLs are found.
 */
final class SitemapService
{
    public function generate(string $scheme, string $hostname): ?string
    {
        $host = $this->resolveHost($hostname);

        $urls = $this->getCmsUrls($scheme, $hostname);

        if ($host !== null) {
            $globalXeo = GlobalXeo::query()
                ->andWhere(['hostId' => $host->getId(), 'kind' => 'Sitemap'])
                ->one();

            if ($globalXeo !== null) {
                $additional = $this->parseRawSitemap($globalXeo->rawData ?? '');
                foreach ($additional as $loc => $data) {
                    if (!isset($urls[$loc])) {
                        $urls[$loc] = $data;
                    }
                }
            }
        }

        if (empty($urls)) {
            return null;
        }

        return $this->generateXml($urls);
    }

    private function getCmsUrls(string $scheme, string $hostname): array
    {
        $urls = [];

        $contents = Content::query()->all();

        foreach ($contents as $content) {
            $slug = $content->getSlugQuery()->one();
            if ($slug === null || !$slug->isActive()) {
                continue;
            }
            $sitemap = $slug->getSitemapQuery()->one();
            if ($sitemap === null || !$sitemap->isActive()) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme . ':' . $slug->getLink()->withTemplate('host', $hostname)->getHref();
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => $content->getDateUpdate()?->format('Y-m-d'),
                'changefreq' => $sitemap->getFrequency(),
                'priority' => (string) $sitemap->getPriority(),
            ];
        }

        $tags = Tag::query()->all();

        foreach ($tags as $tag) {
            $slug = $tag->getSlugQuery()->one();
            if ($slug === null || !$slug->isActive()) {
                continue;
            }
            $sitemap = $slug->getSitemapQuery()->one();
            if ($sitemap === null || !$sitemap->isActive()) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme . ':' . $slug->getLink()->withTemplate('host', $hostname)->getHref();
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => $tag->getDateUpdate()?->format('Y-m-d'),
                'changefreq' => $sitemap->getFrequency(),
                'priority' => (string) $sitemap->getPriority(),
            ];
        }

        return $urls;
    }

    private function parseRawSitemap(string $rawData): array
    {
        if (trim($rawData) === '') {
            return [];
        }

        $xml = @simplexml_load_string($rawData);

        if ($xml === false) {
            return [];
        }

        $urls = [];

        foreach ($xml->url as $url) {
            $loc = (string) $url->loc;
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null,
                'priority' => isset($url->priority) ? (string) $url->priority : null,
            ];
        }

        return $urls;
    }

    private function generateXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $data) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($data['loc'], ENT_XML1) . "</loc>\n";
            if (!empty($data['lastmod'])) {
                $xml .= "    <lastmod>" . $data['lastmod'] . "</lastmod>\n";
            }
            if (!empty($data['changefreq'])) {
                $xml .= "    <changefreq>" . $data['changefreq'] . "</changefreq>\n";
            }
            if (!empty($data['priority'])) {
                $xml .= "    <priority>" . $data['priority'] . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function resolveHost(string $hostname): ?Host
    {
        $host = Host::query()
            ->andWhere(['name' => $hostname])
            ->active()
            ->one();

        if ($host !== null) {
            return $host;
        }

        return Host::query()
            ->andWhere(['id' => 1])
            ->active()
            ->one();
    }
}
