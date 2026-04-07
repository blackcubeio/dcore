<?php

declare(strict_types=1);

/**
 * BaseBloc.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Traits\ModelKindTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
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
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected bool $active = false;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%blocs}}';
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

    public function getDateUpdate(): ?DateTimeImmutable
    {
        return $this->dateUpdate;
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
        return $this->hasOne($this->fqcn(ElasticSchema::class), ['id' => 'elasticSchemaId']);
    }

    /**
     * Relation to pivot ContentBloc.
     * @relation contentBlocs
     */
    public function getContentBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     * @see Content::getBlocsQuery() inverse
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Content::class), ['id' => 'contentId'])
            ->via('contentBlocs')
            ->inverseOf('blocs');
    }


    /**
     * Relation to pivot TagBloc.
     * @relation tagBlocs
     */
    public function getTagBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TagBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     * @see Tag::getBlocsQuery() inverse
     */
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Tag::class), ['id' => 'tagId'])
            ->via('tagBlocs')
            ->inverseOf('blocs');
    }


    /**
     * Relation to pivot XeoBloc.
     * @relation xeoBlocs
     */
    public function getXeoBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(XeoBloc::class), ['blocId' => 'id']);
    }

    /**
     * Relation to Xeo via pivot.
     * @relation xeos
     */
    public function getXeosQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Xeo::class), ['id' => 'xeoId'])
            ->via('xeoBlocs');
    }
}
