<?php

declare(strict_types=1);

/**
 * TypeElasticSchema.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Traits\NamespaceResolverTrait;
use Closure;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;
use Blackcube\Dcore\Traits\PopulatePropertyTrait;

/**
 * TypeElasticSchema pivot model - Type ↔ ElasticSchema relationship.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class TypeElasticSchema extends ActiveRecord
{
    use EventsTrait;
    use MagicRelationsTrait;
    use PopulatePropertyTrait;
    use NamespaceResolverTrait;

    protected int $typeId;
    protected int $elasticSchemaId;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%types_elasticSchemas}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    public function getTypeId(): int
    {
        return $this->typeId;
    }

    public function setTypeId(int $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getElasticSchemaId(): int
    {
        return $this->elasticSchemaId;
    }

    public function setElasticSchemaId(int $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
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
     * Relation to Type.
     * @relation type
     */
    public function getTypeQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(Type::class), ['id' => 'typeId']);
    }

    /**
     * Relation to ElasticSchema.
     * @relation elasticSchema
     */
    public function getElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(ElasticSchema::class), ['id' => 'elasticSchemaId']);
    }
}
