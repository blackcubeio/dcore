<?php

declare(strict_types=1);

/**
 * SitemapService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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
class SitemapService
{
    public function generate(Host $host, string $hostname, string $scheme): ?string
    {
        $urls = $this->getCmsUrls($scheme, $hostname);

        $globalXeo = GlobalXeo::query()
            ->andWhere(['hostId' => $host->getId(), 'kind' => 'Sitemap'])
            ->one();

        if ($globalXeo !== null) {
            $additional = $this->parseRawSitemap($globalXeo->rawData ?? '');
            foreach ($additional as $loc => $data) {
                if (isset($urls[$loc]) === false) {
                    $urls[$loc] = $data;
                }
            }
        }

        if (empty($urls) === true) {
            return null;
        }

        return $this->generateXml($urls);
    }

    private function getCmsUrls(string $scheme, string $hostname): array
    {
        $urls = [];

        /** @var Content $content */
        foreach (Content::query()->each() as $content) {
            $slug = $content->getSlugQuery()->one();
            if ($slug === null || $slug->isActive() === false) {
                continue;
            }
            $sitemap = $slug->getSitemapQuery()->one();
            if ($sitemap === null || $sitemap->isActive() === false) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme.':'.$slug->getLink()->withTemplate('host', $hostname)->getHref();
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => $content->getDateUpdate()?->format('Y-m-d'),
                'changefreq' => $sitemap->getFrequency(),
                'priority' => (string) $sitemap->getPriority(),
            ];
        }

        /** @var Tag $tag */
        foreach (Tag::query()->each() as $tag) {
            $slug = $tag->getSlugQuery()->one();
            if ($slug === null || $slug->isActive() === false) {
                continue;
            }
            $sitemap = $slug->getSitemapQuery()->one();
            if ($sitemap === null || $sitemap->isActive() === false) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme.':'.$slug->getLink()->withTemplate('host', $hostname)->getHref();
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
                'lastmod' => isset($url->lastmod) === true ? (string) $url->lastmod : null,
                'priority' => isset($url->priority) === true ? (string) $url->priority : null,
            ];
        }

        return $urls;
    }

    private function generateXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $data) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>".htmlspecialchars($data['loc'], ENT_XML1)."</loc>\n";
            if (empty($data['lastmod']) === false) {
                $xml .= "    <lastmod>".$data['lastmod']."</lastmod>\n";
            }
            if (empty($data['changefreq']) === false) {
                $xml .= "    <changefreq>".$data['changefreq']."</changefreq>\n";
            }
            if (empty($data['priority']) === false) {
                $xml .= "    <priority>".$data['priority']."</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
