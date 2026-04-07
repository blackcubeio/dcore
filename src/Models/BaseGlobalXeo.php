<?php

declare(strict_types=1);

/**
 * BaseGlobalXeo.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
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
 * BaseGlobalXeo - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseGlobalXeo extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $hostId;
    protected string $name = '';
    protected string $kind = '';
    protected bool $active = false;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%globalXeos}}';
    }

    public function getHostId(): int
    {
        return $this->hostId;
    }

    public function setHostId(int $hostId): void
    {
        $this->hostId = $hostId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

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
     * Relation to Host.
     * @relation host
     */
    public function getHostQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Host::class), ['id' => 'hostId']);
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
