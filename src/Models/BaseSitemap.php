<?php

declare(strict_types=1);

/**
 * BaseSitemap.php
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
 * BaseSitemap - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseSitemap extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected int $slugId;
    protected string $frequency = 'daily';
    protected float $priority = 0.5;
    protected bool $active = false;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%sitemaps}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSlugId(): int
    {
        return $this->slugId;
    }

    public function setSlugId(int $slugId): void
    {
        $this->slugId = $slugId;
    }

    #[Exportable]
    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): void
    {
        $this->frequency = $frequency;
    }

    #[Exportable]
    public function getPriority(): float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): void
    {
        $this->priority = $priority;
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

    public function getSlugQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Slug::class), ['id' => 'slugId'])->inverseOf('sitemap');
    }
}
