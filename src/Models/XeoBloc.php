<?php

declare(strict_types=1);

/**
 * XeoBloc.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
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
 * XeoBloc pivot model - Xeo ↔ Bloc relationship with ordering.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class XeoBloc extends ActiveRecord
{
    use EventsTrait;
    use MagicRelationsTrait;
    use PopulatePropertyTrait;
    use NamespaceResolverTrait;

    protected int $xeoId;
    protected int $blocId;
    protected int $order = 0;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%xeos_blocs}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
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
        return $this->hasOne($this->resolve(Xeo::class), ['id' => 'xeoId']);
    }

    /**
     * Relation to Bloc.
     * @relation bloc
     */
    public function getBlocQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(Bloc::class), ['id' => 'blocId']);
    }
}
