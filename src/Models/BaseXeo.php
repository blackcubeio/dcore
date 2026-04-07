<?php

declare(strict_types=1);

/**
 * BaseXeo.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
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
 * BaseXeo - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseXeo extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected int $slugId;
    protected ?int $canonicalSlugId = null;
    protected ?string $title = null;
    protected ?string $image = null;
    protected ?string $description = null;
    protected bool $noindex = false;
    protected bool $nofollow = false;
    protected bool $og = false;
    protected ?string $ogType = null;
    protected bool $twitter = false;
    protected ?string $twitterCard = null;
    protected string $jsonldType = 'WebPage';
    protected bool $speakable = false;
    protected ?string $keywords = null;
    protected bool $accessibleForFree = true;
    protected bool $active = false;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%xeos}}';
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

    public function getCanonicalSlugId(): ?int
    {
        return $this->canonicalSlugId;
    }

    #[Exportable]
    public function isCanonical(): bool
    {
        return $this->canonicalSlugId !== null && $this->canonicalSlugId === $this->slugId;
    }

    public function setCanonicalSlugId(?int $canonicalSlugId): void
    {
        $this->canonicalSlugId = $canonicalSlugId;
    }

    #[Exportable]
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    #[Exportable(base64: true)]
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    #[Exportable]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    #[Exportable]
    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    public function setNoindex(bool $noindex): void
    {
        $this->noindex = $noindex;
    }

    #[Exportable]
    public function isNofollow(): bool
    {
        return $this->nofollow;
    }

    public function setNofollow(bool $nofollow): void
    {
        $this->nofollow = $nofollow;
    }

    #[Exportable]
    public function isOg(): bool
    {
        return $this->og;
    }

    public function setOg(bool $og): void
    {
        $this->og = $og;
    }

    #[Exportable]
    public function getOgType(): ?string
    {
        return $this->ogType;
    }

    public function setOgType(?string $ogType): void
    {
        $this->ogType = $ogType;
    }

    #[Exportable]
    public function isTwitter(): bool
    {
        return $this->twitter;
    }

    public function setTwitter(bool $twitter): void
    {
        $this->twitter = $twitter;
    }

    #[Exportable]
    public function getTwitterCard(): ?string
    {
        return $this->twitterCard;
    }

    public function setTwitterCard(?string $twitterCard): void
    {
        $this->twitterCard = $twitterCard;
    }

    #[Exportable]
    public function getJsonldType(): string
    {
        return $this->jsonldType;
    }

    public function setJsonldType(string $jsonldType): void
    {
        $this->jsonldType = $jsonldType;
    }

    #[Exportable]
    public function isSpeakable(): bool
    {
        return $this->speakable;
    }

    public function setSpeakable(bool $speakable): void
    {
        $this->speakable = $speakable;
    }

    #[Exportable]
    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    #[Exportable]
    public function isAccessibleForFree(): bool
    {
        return $this->accessibleForFree;
    }

    public function setAccessibleForFree(bool $accessibleForFree): void
    {
        $this->accessibleForFree = $accessibleForFree;
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
        return $this->hasOne($this->fqcn(Slug::class), ['id' => 'slugId'])->inverseOf('xeo');
    }

    /**
     * Relation to pivot XeoBloc.
     * @relation xeoBlocs
     */
    public function getXeoBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(XeoBloc::class), ['xeoId' => 'id']);
    }

    /**
     * Relation to Bloc via pivot.
     * @relation blocs
     */
    #[Exportable(name: 'blocs')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Bloc::class), ['id' => 'blocId'])
            ->via('xeoBlocs');
    }
}
