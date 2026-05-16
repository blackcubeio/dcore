<?php

declare(strict_types=1);

/**
 * BaseXeoBloc.php
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
 * BaseXeoBloc - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseXeoBloc extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $xeoId;
    protected int $blocId;
    protected int $order = 0;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%xeos_blocs}}';
    }


    public function getXeoId(): int
    {
        return $this->xeoId;
    }

    public function setXeoId(int $xeoId): void
    {
        $this->xeoId = $xeoId;
    }

    public function getBlocId(): int
    {
        return $this->blocId;
    }

    public function setBlocId(int $blocId): void
    {
        $this->blocId = $blocId;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
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
     * Relation to Xeo.
     * @relation xeo
     */
    public function getXeoQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Xeo::class), ['id' => 'xeoId']);
    }

    /**
     * Relation to Bloc.
     * @relation bloc
     */
    public function getBlocQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Bloc::class), ['id' => 'blocId']);
    }
}
