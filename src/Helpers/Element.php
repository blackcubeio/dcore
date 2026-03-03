<?php

declare(strict_types=1);

/**
 * Element.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Helpers;

use Blackcube\Dcore\Models\Content as ContentModel;
use Blackcube\Dcore\Models\ScopedQuery;
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

    public function getModelQuery(): ?ScopedQuery
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
}
