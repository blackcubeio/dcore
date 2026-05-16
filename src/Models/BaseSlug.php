<?php

declare(strict_types=1);

/**
 * BaseSlug.php
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
 * BaseSlug - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseSlug extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected int $hostId = 1;
    protected string $path = '';
    protected ?string $targetUrl = null;
    protected ?int $httpCode = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%slugs}}';
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    #[Exportable]
    public function getHostId(): int
    {
        return $this->hostId;
    }

    public function setHostId(int $hostId): void
    {
        $this->hostId = $hostId;
    }

    #[Exportable]
    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    #[Exportable]
    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    #[Exportable]
    public function getHttpCode(): ?int
    {
        return $this->httpCode;
    }

    public function setHttpCode(?int $httpCode): void
    {
        $this->httpCode = $httpCode;
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

    public function getHostQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Host::class), ['id' => 'hostId']);
    }

    #[Exportable(name: 'xeo')]
    public function getXeoQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Xeo::class), ['slugId' => 'id'])->inverseOf('slug');
    }

    #[Exportable(name: 'sitemap')]
    public function getSitemapQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Sitemap::class), ['slugId' => 'id'])->inverseOf('slug');
    }

    public function getContentQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Content::class), ['slugId' => 'id'])->inverseOf('slug');
    }

    public function getTagQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Tag::class), ['slugId' => 'id'])->inverseOf('slug');
    }

    /**
     * Polymorphic: returns Content or Tag linked to this slug.
     */
    public function getElement(): Content|Tag|null
    {
        return $this->getContentQuery()->one() ?? $this->getTagQuery()->one();
    }

    public function getLink(): Link {
        if ($this->targetUrl !== null && $this->httpCode !== null) {
            $link = new Link('', $this->targetUrl);
        } else {
            $uri = '//{host}/'.(ltrim($this->path, '/'));
            $link = new Link('', $uri);
        }

        if ($this->hostId > 1) {
            $host = $this->getHostQuery()->one();
            $link = $link->withTemplate('host', $host->getName());
        }
        return $link;
    }
}
