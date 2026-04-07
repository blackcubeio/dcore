<?php

declare(strict_types=1);

/**
 * BaseTypeElasticSchema.php
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
 * BaseTypeElasticSchema - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseTypeElasticSchema extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $typeId;
    protected int $elasticSchemaId;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%types_elasticSchemas}}';
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
        return $this->hasOne($this->fqcn(Type::class), ['id' => 'typeId']);
    }

    /**
     * Relation to ElasticSchema.
     * @relation elasticSchema
     */
    public function getElasticSchemaQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(ElasticSchema::class), ['id' => 'elasticSchemaId']);
    }
}
