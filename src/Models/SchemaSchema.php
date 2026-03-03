<?php

declare(strict_types=1);

/**
 * SchemaSchema.php
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
 * SchemaSchema pivot model - ElasticSchema regular ↔ ElasticSchema xeo relationship.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class SchemaSchema extends ActiveRecord
{
    use EventsTrait;
    use MagicRelationsTrait;
    use PopulatePropertyTrait;
    use NamespaceResolverTrait;

    protected int $regularElasticSchemaId;
    protected int $xeoElasticSchemaId;
    protected ?string $mapping = null;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%schemas_schemas}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    public function primaryKey(): array
    {
        return ['regularElasticSchemaId', 'xeoElasticSchemaId'];
    }

    public function getRegularElasticSchemaId(): int
    {
        return $this->regularElasticSchemaId;
    }

    public function setRegularElasticSchemaId(int $regularElasticSchemaId): void
    {
        $this->regularElasticSchemaId = $regularElasticSchemaId;
    }

    public function getXeoElasticSchemaId(): int
    {
        return $this->xeoElasticSchemaId;
    }

    public function setXeoElasticSchemaId(int $xeoElasticSchemaId): void
    {
        $this->xeoElasticSchemaId = $xeoElasticSchemaId;
    }

    public function getMapping(): ?string
    {
        return $this->mapping;
    }

    public function setMapping(?string $mapping): void
    {
        $this->mapping = $mapping;
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
     * Relation to ElasticSchema (regular).
     * @relation regularElasticSchema
     */
    public function getRegularElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(ElasticSchema::class), ['id' => 'regularElasticSchemaId']);
    }

    /**
     * Relation to ElasticSchema (xeo).
     * @relation xeoElasticSchema
     */
    public function getXeoElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(ElasticSchema::class), ['id' => 'xeoElasticSchemaId']);
    }
}
