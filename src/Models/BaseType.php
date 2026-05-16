<?php

declare(strict_types=1);

/**
 * BaseType.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Traits\ModelKindTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseType - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseType extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected string $name = '';
    protected ?string $handler = null;
    protected bool $contentAllowed = true;
    protected bool $tagAllowed = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%types}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getHandler(): ?string
    {
        return $this->handler;
    }

    public function setHandler(?string $handler): void
    {
        $this->handler = $handler;
    }

    public function isContentAllowed(): bool
    {
        return $this->contentAllowed;
    }

    public function setContentAllowed(bool $contentAllowed): void
    {
        $this->contentAllowed = $contentAllowed;
    }

    public function isTagAllowed(): bool
    {
        return $this->tagAllowed;
    }

    public function setTagAllowed(bool $tagAllowed): void
    {
        $this->tagAllowed = $tagAllowed;
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
     * Relation to pivot TypeElasticSchema.
     * @relation typeElasticSchemas
     */
    public function getTypeElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TypeElasticSchema::class), ['typeId' => 'id']);
    }

    /**
     * Relation to ElasticSchema via pivot (bloc types available for this type).
     * @relation elasticSchemas
     */
    public function getElasticSchemasQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ElasticSchema::class), ['id' => 'elasticSchemaId'])
            ->via('typeElasticSchemas');
    }
}
