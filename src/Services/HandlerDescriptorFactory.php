<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Entities\Content;
use Blackcube\Dcore\Entities\Slug;
use Blackcube\Dcore\Entities\Tag;
use Blackcube\Dcore\Entities\Type;
use Blackcube\Dcore\Helpers\Element;
use Blackcube\Dcore\Interfaces\SsrRouteProviderInterface;
use Closure;

/**
 * Builds the HandlerDescriptors: special routes, CMS elements (through the handler route
 * of their Type) and error routes. The routes come from the SSR route provider.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class HandlerDescriptorFactory
{
    public function __construct(
        private readonly SsrRouteProviderInterface $routeProvider,
    ) {}

    /**
     * Simple descriptor for special routes (sitemap, robots, etc.).
     */
    public function simple(
        string $class,
        string $mode,
        array $expects = [],
        ?Closure $dataLoader = null,
    ): HandlerDescriptor {
        return new HandlerDescriptor($class, $mode, null, $expects, $dataLoader);
    }

    /**
     * Descriptor from a CMS element (Content or Tag), or null if its Type carries
     * no handler / the route provider knows no handler for it.
     */
    public function fromElement(Content|Tag $element): ?HandlerDescriptor
    {
        $descriptor = null;
        $route = $element->getTypeQuery()->one()?->getHandler();
        if ($route !== null) {
            $info = $this->routeProvider->getHandlerInfo($route)
                ?? $this->routeProvider->getErrorHandlerInfoByRoute($route);
            if ($info !== null) {
                $descriptor = new HandlerDescriptor(
                    $info['class'],
                    $info['mode'],
                    $info['method'] ?? null,
                    $info['expects'] ?? [],
                    fn() => $this->buildElementData($element, $info['expects'] ?? []),
                );
            }
        }
        return $descriptor;
    }

    /**
     * Descriptor for an error route: the first Content/Tag carrying the matching
     * Type (preferring the given language, then any). Null if none.
     */
    public function fromError(string $route, string $languageId): ?HandlerDescriptor
    {
        $type = Type::query()->andWhere(['handler' => $route])->one();
        $descriptor = null;
        if ($type !== null) {
            $element = $this->firstTypedElement($type, $languageId)
                ?? $this->firstTypedElement($type, null);
            $descriptor = $element !== null ? $this->fromElement($element) : null;
        }
        return $descriptor;
    }

    /**
     * First Content (then Tag) carrying the type, optionally filtered by language.
     */
    private function firstTypedElement(Type $type, ?string $languageId): Content|Tag|null
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
    private function buildElementData(Content|Tag $element, array $expects): array
    {
        $data = [];
        foreach ($expects as $type) {
            switch ($type) {
                case Content::class:
                    if ($element instanceof Content === false) {
                        throw new \RuntimeException('Handler expects Content but element is ' . $element::class);
                    }
                    $data[] = $element;
                    break;

                case Tag::class:
                    if ($element instanceof Tag === false) {
                        throw new \RuntimeException('Handler expects Tag but element is ' . $element::class);
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
