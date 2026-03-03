<?php

declare(strict_types=1);

/**
 * HandlerDescriptor.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Handlers\RedirectHandler;
use Blackcube\Dcore\Handlers\RobotsHandler;
use Blackcube\Dcore\Handlers\SitemapHandler;
use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\GlobalXeo;
use Blackcube\Dcore\Entities\Host;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Entities\Type;
use Closure;

/**
 * Describes how to handle a given path.
 * Central resolution: path → handler class + mode + data.
 */
final class HandlerDescriptor
{
    /**
     * Route resolver: fn(string $route) => ?array{class, mode, method, expects}
     */
    private static ?Closure $routeResolver = null;

    private mixed $data = null;
    private bool $dataResolved = false;

    private function __construct(
        private readonly string $class,
        private readonly string $mode,
        private readonly ?string $method,
        private readonly array $expects,
        private readonly ?Closure $dataLoader,
    ) {}

    /**
     * Set route resolver (called at bootstrap).
     * Resolver signature: fn(string $route): ?array{class, mode, method, expects}
     */
    public static function setRouteResolver(Closure $resolver): void
    {
        self::$routeResolver = $resolver;
    }

    /**
     * Find handler for a given host + path.
     *
     * Resolution order:
     * 1. Host resolution (exact match, fallback id=1)
     * 2. robots.txt → RobotsHandler
     * 3. sitemap.xml → SitemapHandler
     * 4. Slug lookup → redirect or CMS element
     */
    public static function findByPath(string $host, string $path): ?self
    {
        $hostModel = self::resolveHost($host);
        if ($hostModel === null) {
            return null;
        }

        // robots.txt
        if ($path === 'robots.txt') {
            $exists = GlobalXeo::query()
                ->andWhere(['hostId' => $hostModel->getId(), 'kind' => 'Robots'])
                ->active()
                ->one();

            if ($exists === null) {
                return null;
            }

            return new self(RobotsHandler::class, 'construct', null, [], null);
        }

        // sitemap.xml
        if ($path === 'sitemap.xml') {
            return new self(SitemapHandler::class, 'construct', null, [], null);
        }

        // Slug lookup
        $slug = Slug::query()
            ->andWhere(['hostId' => $hostModel->getId(), 'path' => $path])
            ->active()
            ->one();

        if ($slug === null) {
            return null;
        }

        // Redirect
        if ($slug->getTargetUrl() !== null && $slug->getHttpCode() !== null) {
            return new self(
                RedirectHandler::class,
                'construct',
                null,
                [Slug::class],
                fn() => [$slug],
            );
        }

        // CMS element
        $element = $slug->getElement();
        if ($element === null) {
            return null;
        }

        $route = $element->getType()?->getHandler();
        if ($route === null || self::$routeResolver === null) {
            return null;
        }

        $handlerInfo = (self::$routeResolver)($route);
        if ($handlerInfo === null) {
            return null;
        }

        return new self(
            $handlerInfo['class'],
            $handlerInfo['mode'],
            $handlerInfo['method'] ?? null,
            $handlerInfo['expects'] ?? [],
            fn() => self::buildElementData($element, $handlerInfo['expects'] ?? []),
        );
    }

    /**
     * Find handler for an error page.
     * Looks for Content/Tag whose Type carries the given route,
     * picks the one with name == $error, or the first one.
     */
    public static function findByError(string $error, string $route): ?self
    {
        $type = Type::query()
            ->andWhere(['handler' => $route])
            ->one();

        if ($type === null || self::$routeResolver === null) {
            return null;
        }

        $typeId = $type->getId();
        $element = null;

        // Content: match by name, then first
        if ($type->isContentAllowed()) {
            $element = Content::query()
                ->andWhere(['typeId' => $typeId, 'name' => $error])
                ->active()
                ->one();

            if ($element === null) {
                $element = Content::query()
                    ->andWhere(['typeId' => $typeId])
                    ->active()
                    ->one();
            }
        }

        // Tag: match by name, then first
        if ($element === null && $type->isTagAllowed()) {
            $element = Tag::query()
                ->andWhere(['typeId' => $typeId, 'name' => $error])
                ->active()
                ->one();

            if ($element === null) {
                $element = Tag::query()
                    ->andWhere(['typeId' => $typeId])
                    ->active()
                    ->one();
            }
        }

        if ($element === null) {
            return null;
        }

        $handlerInfo = (self::$routeResolver)($route);
        if ($handlerInfo === null) {
            return null;
        }

        return new self(
            $handlerInfo['class'],
            $handlerInfo['mode'],
            $handlerInfo['method'] ?? null,
            $handlerInfo['expects'] ?? [],
            fn() => self::buildElementData($element, $handlerInfo['expects'] ?? []),
        );
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getExpects(): array
    {
        return $this->expects;
    }

    /**
     * Lazy-loaded data for the handler.
     * Returns typed params ready for Injector.
     */
    public function getData(): array
    {
        if (!$this->dataResolved) {
            $this->data = $this->dataLoader !== null ? ($this->dataLoader)() : [];
            $this->dataResolved = true;
        }

        return $this->data;
    }

    /**
     * Build injection data for a CMS element based on handler expectations.
     * Only constructs objects the handler actually needs.
     *
     * Integer-indexed: Yii3 Injector resolves integer-keyed objects by instanceof,
     * NOT by FQCN string key.
     */
    private static function buildElementData(Content|Tag $element, array $expects): array
    {
        $data = [];

        foreach ($expects as $type) {
            switch ($type) {
                case Content::class:
                    if (!$element instanceof Content) {
                        throw new \RuntimeException(
                            'Handler expects Content but element is ' . $element::class
                        );
                    }
                    $data[] = $element;
                    break;

                case Tag::class:
                    if (!$element instanceof Tag) {
                        throw new \RuntimeException(
                            'Handler expects Tag but element is ' . $element::class
                        );
                    }
                    $data[] = $element;
                    break;

                case 'Content|Tag':
                    $data[] = $element;
                    break;

                case Element::class:
                    $data[] = Element::createFromModel($element);
                    break;

                case Slug::class:
                    $slug = $element->getSlug();
                    if ($slug !== null) {
                        $data[] = $slug;
                    }
                    break;
            }
        }

        return $data;
    }

    private static function resolveHost(string $hostname): ?Host
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
