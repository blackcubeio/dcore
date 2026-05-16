<?php

declare(strict_types=1);

/**
 * Element.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
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

/**
 * Unified CMS element reference — lightweight (type + id) or loaded (with model).
 */
class Element
{
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
        if (!preg_match('/^dcore-(c|t)-(\d+)$/', $route, $matches)) {
            return null;
        }

        $type = match ($matches[1]) {
            'c' => 'content',
            't' => 'tag',
        };

        return new self($type, (int) $matches[2]);
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
        $prefix = match ($this->type) {
            'content' => 'c',
            'tag' => 't',
        };

        return "dcore-{$prefix}-{$this->id}";
    }

    /**
     * Resolve a route string (dcore-c-N / dcore-t-N) to its public URL.
     *
     * @return string|null Public path (templated link) or absolute href, null if unresolvable.
     */
    public static function getLink(string $value): ?string
    {
        $url = null;
        $element = self::createFromRoute($value);
        $slug = $element?->getModel()?->getSlugQuery()->one();
        $link = $slug?->getLink();
        if ($link !== null) {
            if ($link->isTemplated()) {
                $url = '/' . ltrim($slug->getPath(), '/');
            } else {
                $url = $link->getHref();
            }
        }
        return $url;
    }

    /**
     * Resolve a link from an object by trying fields in order.
     *
     * Each field value is tested as a dcore route first (dcore-c-N / dcore-t-N).
     * If it resolves, the slug URL is returned. If not, the raw value is used as URL.
     * Returns null if all fields are empty.
     *
     * @param object $source Object with route/url properties (e.g. elastic)
     * @param array<string> $fields Property names to try in order
     * @return string|null Resolved URL or null
     */
    public static function resolveLink(object $source, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $source->$field ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $element = self::createFromRoute($value);
            if ($element !== null) {
                $model = $element->getModel();
                if ($model !== null) {
                    $slug = $model->getSlugQuery()->one();
                    if ($slug !== null) {
                        $link = $slug->getLink();
                        if ($link->isTemplated()) {
                            return '/' . ltrim($slug->getPath(), '/');
                        }
                        return $link->getHref();
                    }
                }
                continue;
            }

            return $value;
        }
        return null;
    }
}
