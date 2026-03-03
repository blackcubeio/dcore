<?php

declare(strict_types=1);

/**
 * BaseBloc.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Traits\NamespaceResolverTrait;
use Closure;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseBloc - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseBloc extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use NamespaceResolverTrait;

    protected int $id;
    protected bool $active = false;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%blocs}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): void
    {
        if (isset($this->id) && $this->id !== $id) {
            throw new \LogicException('Cannot change ID on existing record');
        }
        $this->id = $id;
    }

    #[Exportable]
    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getDateCreate(): ?DateTimeImmutable
    {
        return $this->dateCreate;
    }

    public function setDateCreate(?DateTimeImmutable $dateCreate): void
    {
        $this->dateCreate = $dateCreate;
    }

    public function getDateUpdate(): ?DateTimeImmutable
    {
        return $this->dateUpdate;
    }

    public function setDateUpdate(?DateTimeImmutable $dateUpdate): void
    {
        $this->dateUpdate = $dateUpdate;
    }

    // ========================================
    // Relations
    // ========================================

    /**
     * Relation to ElasticSchema.
     * @relation elasticSchema
     */
    public function getElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(ElasticSchema::class), ['id' => 'elasticSchemaId']);
    }

    /**
     * Relation to pivot ContentBloc.
     * @relation contentBlocs
     */
    public function getContentBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(ContentBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     * @see Content::getBlocsQuery() inverse
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Content::class), ['id' => 'contentId'])
            ->via('contentBlocs')
            ->inverseOf('blocs');
    }

    /**
     * @return Content[]
     */
    public function getContents(): array
    {
        return $this->getContentsQuery()->all();
    }

    /**
     * Relation to pivot TagBloc.
     * @relation tagBlocs
     */
    public function getTagBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(TagBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     * @see Tag::getBlocsQuery() inverse
     */
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Tag::class), ['id' => 'tagId'])
            ->via('tagBlocs')
            ->inverseOf('blocs');
    }

    /**
     * @return Tag[]
     */
    public function getTags(): array
    {
        return $this->getTagsQuery()->all();
    }

    /**
     * Relation to pivot XeoBloc.
     * @relation xeoBlocs
     */
    public function getXeoBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(XeoBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Xeo via pivot.
     * @relation xeos
     */
    public function getXeosQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Xeo::class), ['id' => 'xeoId'])
            ->via('xeoBlocs');
    }
}
