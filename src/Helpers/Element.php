<?php

declare(strict_types=1);

/**
 * Element.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Models\Content as ContentModel;
use Blackcube\Dcore\Models\ScopableQuery;
use Blackcube\Dcore\Models\Slug as SlugModel;
use Blackcube\Dcore\Models\Tag as TagModel;
use Blackcube\Dcore\Entities\Content as ContentEntity;
use Blackcube\Dcore\Entities\Slug as SlugEntity;
use Blackcube\Dcore\Entities\Tag as TagEntity;
use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * Unified CMS element reference — lightweight (type + id) or loaded (with model).
 */
class Element
{
    private static string $regex = '/^dcore-(c|t)-(\d+)$/';
    private static array $routeMapping = [
        'c' => 'content',
        't' => 'tag',
    ];
    private static array $routesToUrlsMapping = [];
    private static string $route = 'dcore-{type}-{id}';

    private ContentModel|TagModel|ContentEntity|TagEntity|null $model;

    private function __construct(
        private readonly string $type,
        private readonly int $id,
        ContentModel|TagModel|ContentEntity|TagEntity|null $model = null,
        private string $mode = 'entity',
    ) {
        $this->model = $model;
    }

    public static function createFromRoute(string $route): ?self
    {
        $element = null;
        $mapped = static::mapFromRoute($route);
        if ($mapped !== null) {
            $element = new self($mapped['type'], (int) $mapped['id']);
        }
        return $element;
    }

    public static function mapFromRoute(string $route): ?array
    {
        $result = null;
        if (preg_match(static::$regex, $route, $matches) === 1) {
            $type = static::$routeMapping[$matches[1]] ?? null;
            if ($type !== null) {
                $result = [
                    'type' => $type,
                    'id' => $matches[2]
                ];
            }
        }
        return $result;
    }
    public static function mapToRoute(array $elementType): ?string
    {
        $result = null;
        if (isset($elementType['id'], $elementType['type']) === true) {
            $reverseMapping = array_flip(static::$routeMapping);
            if (isset($reverseMapping[$elementType['type']]) === true) {
                $result = str_replace(
                    ['{type}', '{id}'],
                    [$reverseMapping[$elementType['type']], (string) $elementType['id']],
                    static::$route,
                );
            }
        }
        return $result;
    }

    public static function createFromModel(ContentModel|TagModel|ContentEntity|TagEntity $model): self
    {
        $info = match (true) {
            $model instanceof ContentModel => ['mode' => 'model', 'type' => 'content'],
            $model instanceof TagModel => ['mode' => 'model', 'type' => 'tag'],
            $model instanceof ContentEntity => ['mode' => 'entity', 'type' => 'content'],
            $model instanceof TagEntity => ['mode' => 'entity', 'type' => 'tag'],
        };

        return new self($info['type'], $model->getId(), $model, $info['mode']);
    }

    public static function createFromSlug(SlugModel|SlugEntity $slug): ?self
    {
        $element = $slug->getElement();

        return $element !== null ? self::createFromModel($element) : null;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getModel(): ContentModel|TagModel|ContentEntity|TagEntity|null
    {
        if ($this->model !== null) {
            return $this->model;
        }

        $this->model = $this->getModelQuery()?->one();

        return $this->model;
    }

    public function getModelQuery(): ?ScopableQuery
    {
        return match ($this->type) {
            'content' => ($this->mode === 'entity' ? ContentEntity::query() : ContentModel::query())->andWhere(['id' => $this->id]),
            'tag' => ($this->mode === 'entity' ? TagEntity::query() : TagModel::query())->andWhere(['id' => $this->id]),
            default => null,
        };
    }

    public function toRoute(): string
    {
        return (string) static::mapToRoute(['type' => $this->type, 'id' => $this->id]);
    }

    /**
     * Resolve a route string (dcore-c-N / dcore-t-N) to its public URL.
     *
     * @return string|null Public path (templated link) or absolute href, null if unresolvable.
     */
    public static function getLink(string $value): ?string
    {
        $element = self::createFromRoute($value);
        $slug = $element?->getModel()?->getSlugQuery()->one();
        return $slug?->getLink()->getHref();
    }

    /**
     * Batch version of getLink() — resolves many routes to their public URLs in a
     * constant number of queries. Routes already resolved are served from a static
     * cache; only the missing ones are loaded (one query for contents, one for tags,
     * slug eager-loaded). Only the requested routes are returned.
     *
     * @param string|array<string>|ActiveQuery $routes A route, a list of routes, or a query yielding them.
     * @return array<string, string|null> route => public URL (or null if unresolvable)
     */
    public static function getUrlsFromRoutes(string|array|ActiveQuery $routes): array
    {
        if ($routes instanceof ActiveQuery) {
            $routes = $routes->column();
        } elseif (is_string($routes) === true) {
            $routes = [$routes];
        }
        $missingRoutes = array_values(array_diff($routes, array_keys(self::$routesToUrlsMapping)));

        if (empty($missingRoutes) === false) {
            $contentIds = [];
            $tagIds = [];
            foreach ($missingRoutes as $missingRoute) {
                self::$routesToUrlsMapping[$missingRoute] = null;
                $mapped = self::mapFromRoute($missingRoute);
                if ($mapped !== null) {
                    if ($mapped['type'] === 'content') {
                        $contentIds[] = (int) $mapped['id'];
                    } elseif ($mapped['type'] === 'tag') {
                        $tagIds[] = (int) $mapped['id'];
                    }
                }
            }
            if (empty($contentIds) === false) {
                $contentsQuery = ContentEntity::query()->andWhere(['id' => $contentIds])->with(['slug']);
                /** @var ContentEntity $content */
                foreach ($contentsQuery->each() as $content) {
                    self::$routesToUrlsMapping[(string) self::mapToRoute(['type' => 'content', 'id' => $content->getId()])] = $content->slug?->getLink()->getHref();
                }
            }
            if (empty($tagIds) === false) {
                $tagsQuery = TagEntity::query()->andWhere(['id' => $tagIds])->with(['slug']);
                /** @var TagEntity $tag */
                foreach ($tagsQuery->each() as $tag) {
                    self::$routesToUrlsMapping[(string) self::mapToRoute(['type' => 'tag', 'id' => $tag->getId()])] = $tag->slug?->getLink()->getHref();
                }
            }
        }
        return array_filter(self::$routesToUrlsMapping,
            fn($route) => in_array($route, $routes, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Extract a URL from an object by trying its fields in order. Each field is
     * tested as a dcore route (dcore-c-N / dcore-t-N) -> slug URL ; if it is not a
     * route, the raw value is used as URL. Returns the first non-empty match, or null.
     *
     * @param object $source Object with route/url properties (e.g. elastic)
     * @param array<string> $fields Property names to try in order
     * @return string|null URL or null if all fields are empty
     */
    public static function extractUrl(object $source, array $fields): ?string
    {
        $url = null;
        foreach ($fields as $field) {
            $value = $source->$field ?? null;
            if ($url === null && $value !== null && $value !== '') {
                $element = self::createFromRoute($value);
                if ($element === null) {
                    $url = $value;
                } else {
                    $url = $element->getModel()?->getSlugQuery()->one()?->getLink()->getHref();
                }
            }
        }
        return $url;
    }
}
