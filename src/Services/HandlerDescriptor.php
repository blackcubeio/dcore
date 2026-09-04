<?php

declare(strict_types=1);

/**
 * HandlerDescriptor.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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
 * Describes how to handle a given path: handler class + mode + lazy data.
 */
class HandlerDescriptor
{
    /**
     * Route loader: fn(string $route) => ?array{class, mode, method, expects}
     */
    private static ?Closure $routeLoader = null;

    private mixed $data = null;
    private bool $dataLoaded = false;

    private function __construct(
        private readonly string $class,
        private readonly string $mode,
        private readonly ?string $method,
        private readonly array $expects,
        private readonly ?Closure $dataLoader,
    ) {}

    /**
     * Simple descriptor for special routes (sitemap, robots, etc.).
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
     * Set the route loader (called at bootstrap).
     */
    public static function setRouteLoader(Closure $loader): void
    {
        self::$routeLoader = $loader;
    }

    /**
     * Descriptor from a CMS element (Content or Tag), or null if its Type carries
     * no handler / the route loader yields nothing.
     */
    public static function fromElement(Content|Tag $element): ?self
    {
        $descriptor = null;
        $route = $element->getTypeQuery()->one()?->getHandler();
        if ($route !== null && self::$routeLoader !== null) {
            $info = (self::$routeLoader)($route);
            if ($info !== null) {
                $descriptor = new self(
                    $info['class'],
                    $info['mode'],
                    $info['method'] ?? null,
                    $info['expects'] ?? [],
                    fn() => self::buildElementData($element, $info['expects'] ?? []),
                );
            }
        }
        return $descriptor;
    }

    /**
     * Descriptor for an error route: the first Content/Tag carrying the matching
     * Type (preferring the given language, then any). Null if none.
     */
    public static function fromError(string $route, string $languageId): ?self
    {
        $type = Type::query()->andWhere(['handler' => $route])->one();
        $descriptor = null;
        if ($type !== null) {
            $element = self::firstTypedElement($type, $languageId)
                ?? self::firstTypedElement($type, null);
            $descriptor = $element !== null ? self::fromElement($element) : null;
        }
        return $descriptor;
    }

    /**
     * Host for a hostname: exact name match, else the generic host (id 1).
     * One query (name OR id=1), specific wins over generic.
     */
    public static function hostFor(string $hostname): ?Host
    {
        $specific = null;
        $generic = null;
        $hostsQuery = Host::query()
            ->andWhere(['name' => $hostname])
            ->orWhere(['id' => 1])
            ->active();
        /** @var Host $host */
        foreach ($hostsQuery->each() as $host) {
            if ($host->getName() === $hostname) {
                $specific = $host;
            } else {
                $generic = $host;
            }
        }
        return $specific ?? $generic;
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
     * Lazy-loaded data for the handler: typed params ready for the Injector.
     */
    public function getData(): array
    {
        if ($this->dataLoaded === false) {
            $this->data = $this->dataLoader !== null ? ($this->dataLoader)() : [];
            $this->dataLoaded = true;
        }
        return $this->data;
    }

    /**
     * First Content (then Tag) carrying the type, optionally filtered by language.
     */
    private static function firstTypedElement(Type $type, ?string $languageId): Content|Tag|null
    {
        $where = ['typeId' => $type->getId()];
        if ($languageId !== null) {
            $where['languageId'] = $languageId;
        }
        $element = null;
        if ($type->isContentAllowed() === true) {
            $element = Content::query()->andWhere($where)->active()->one();
        }
        if ($element === null && $type->isTagAllowed() === true) {
            $element = Tag::query()->andWhere($where)->active()->one();
        }
        return $element;
    }

    /**
     * Build injection data for a CMS element based on handler expectations.
     * Integer-indexed: Yii3 Injector matches integer-keyed objects by instanceof.
     */
    private static function buildElementData(Content|Tag $element, array $expects): array
    {
        $data = [];
        foreach ($expects as $type) {
            switch ($type) {
                case Content::class:
                    if ($element instanceof Content === false) {
                        throw new \RuntimeException('Handler expects Content but element is '.$element::class);
                    }
                    $data[] = $element;
                    break;

                case Tag::class:
                    if ($element instanceof Tag === false) {
                        throw new \RuntimeException('Handler expects Tag but element is '.$element::class);
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
