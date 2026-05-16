<?php

declare(strict_types=1);

/**
 * BaseSchemaSchema.php
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
 * BaseSchemaSchema - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseSchemaSchema extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $regularElasticSchemaId;
    protected int $xeoElasticSchemaId;
    protected ?string $mapping = null;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%schemas_schemas}}';
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
        return $this->hasOne($this->fqcn(ElasticSchema::class), ['id' => 'regularElasticSchemaId']);
    }

    /**
     * Relation to ElasticSchema (xeo).
     * @relation xeoElasticSchema
     */
    public function getXeoElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(ElasticSchema::class), ['id' => 'xeoElasticSchemaId']);
    }
}
