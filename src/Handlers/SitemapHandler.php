<?php

declare(strict_types=1);

/**
 * SitemapHandler.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Handlers;

use Blackcube\Dcore\Models\Content;
use Blackcube\Dcore\Models\GlobalXeo;
use Blackcube\Dcore\Models\Host;
use Blackcube\Dcore\Models\Slug;
use Blackcube\Dcore\Models\Tag;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler for /sitemap.xml — autonomous, queries Content/Tag/GlobalXeo directly.
 */
final class SitemapHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $hostname = $uri->getHost();
        $host = $this->resolveHost($hostname);

        // Classic: publishable+active elements with active slug
        $urls = $this->getCmsUrls($scheme, $hostname);

        // Additional: GlobalXeo kind=Sitemap if active
        if ($host !== null) {
            $globalXeo = GlobalXeo::query()
                ->andWhere(['hostId' => $host->getId(), 'kind' => 'Sitemap'])
                ->active()
                ->one();

            if ($globalXeo !== null) {
                $additional = $this->parseRawSitemap($globalXeo->rawData ?? '');
                // Classic wins — additional only adds new locs
                foreach ($additional as $loc => $data) {
                    if (!isset($urls[$loc])) {
                        $urls[$loc] = $data;
                    }
                }
            }
        }

        // Nothing → 404
        if (empty($urls)) {
            return $this->responseFactory->createResponse(404);
        }

        $body = $this->streamFactory->createStream($this->generateXml($urls));

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($body);
    }

    private function getCmsUrls(string $scheme, string $hostname): array
    {
        $urls = [];

        $contents = Content::query()->publishable()->active()->all();

        foreach ($contents as $content) {
            $slug = $content->getSlug();
            if ($slug === null || !$slug->isActive()) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme . ':' . $slug->getLink()->withTemplate('host', $hostname)->getHref();
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => $content->getDateUpdate()?->format('Y-m-d'),
                'priority' => '0.8',
            ];
        }

        $tags = Tag::query()->publishable()->active()->all();

        foreach ($tags as $tag) {
            $slug = $tag->getSlug();
            if ($slug === null || !$slug->isActive()) {
                continue;
            }
            /** @var Slug $slug */
            $loc = $scheme . ':' . $slug->getLink()->withTemplate('host', $hostname)->getHref();
            $urls[$loc] = [
                'loc' => $loc,
                'lastmod' => $tag->getDateUpdate()?->format('Y-m-d'),
                'priority' => '0.6',
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

    private function generateXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $data) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($data['loc'], ENT_XML1) . "</loc>\n";
            if ($data['lastmod'] !== null) {
                $xml .= "    <lastmod>" . $data['lastmod'] . "</lastmod>\n";
            }
            if ($data['priority'] !== null) {
                $xml .= "    <priority>" . $data['priority'] . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
