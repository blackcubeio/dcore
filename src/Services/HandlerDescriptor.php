<?php

declare(strict_types=1);

/**
 * HandlerDescriptor.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Entities\Content;
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
     * Create a simple descriptor (for special routes like sitemap, robots, etc.).
     */
    public static function simple(
        string $class,
        string $mode,
        array $expects = [],
        ?Closure $dataLoader = null,
    ): self {
        return new self($class, $mode, null, $expects, $dataLoader);
    }

    /**
     * Set route resolver (called at bootstrap).
     * Resolver signature: fn(string $route): ?array{class, mode, method, expects}
     */
    public static function setRouteResolver(Closure $resolver): void
    {
        self::$routeResolver = $resolver;
    }

    /**
     * Build descriptor from a CMS element (Content or Tag).
     * Returns null if element has no Type handler or route resolver fails.
     */
    public static function fromElement(Content|Tag $element): ?self
    {
        $route = $element->getTypeQuery()->one()?->getHandler();
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
     * Resolve host model by hostname (exact match, fallback id=1).
     */
    public static function resolveHost(string $hostname): ?Host
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

    /**
     * Find handler for a given host + path.
     * Handles slug lookup → CMS element resolution.
     * Special routes (robots, sitemap, llms, md) and redirects
     * are handled by the SSR middleware.
     */
    public static function findByPath(string $host, string $path): ?self
    {
        $hostModel = self::resolveHost($host);
        if ($hostModel === null) {
            return null;
        }

        $slug = Slug::query()
            ->andWhere(['hostId' => $hostModel->getId(), 'path' => $path])
            ->active()
            ->one();

        if ($slug === null) {
            return null;
        }

        $element = $slug->getElement();
        if ($element === null) {
            return null;
        }

        return self::fromElement($element);
    }

    /**
     * Find handler for an error page.
     * Looks for Content/Tag whose Type carries the given route,
     * matching the given language first, then any.
     */
    public static function findByError(string $route, string $languageId): ?self
    {
        $type = Type::query()
            ->andWhere(['handler' => $route])
            ->one();

        if ($type === null || self::$routeResolver === null) {
            return null;
        }

        $typeId = $type->getId();
        $element = null;

        if ($type->isContentAllowed()) {
            $element = Content::query()
                ->andWhere(['typeId' => $typeId, 'languageId' => $languageId])
                ->active()
                ->one();
        }

        if ($element === null && $type->isTagAllowed()) {
            $element = Tag::query()
                ->andWhere(['typeId' => $typeId, 'languageId' => $languageId])
                ->active()
                ->one();
        }

        if ($element === null && $type->isContentAllowed()) {
            $element = Content::query()
                ->andWhere(['typeId' => $typeId])
                ->active()
                ->one();
        }

        if ($element === null && $type->isTagAllowed()) {
            $element = Tag::query()
                ->andWhere(['typeId' => $typeId])
                ->active()
                ->one();
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
                    $slug = $element->getSlugQuery()->one();
                    if ($slug !== null) {
                        $data[] = $slug;
                    }
                    break;
            }
        }

        return $data;
    }

}
