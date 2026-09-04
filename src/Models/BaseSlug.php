<?php

declare(strict_types=1);

/**
 * BaseSlug.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
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
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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

    #[Exportable(description: 'Host scope of the slug: 1 = served on every domain; a specific host id = served only on that domain.')]
    public function getHostId(): int
    {
        return $this->hostId;
    }

    #[Importable(name: 'hostId')]
    public function setHostId(int $hostId): void
    {
        $this->hostId = $hostId;
    }

    #[Exportable(description: 'The URL path.')]
    public function getPath(): string
    {
        return $this->path;
    }

    #[Importable(name: 'path')]
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    #[Exportable(description: 'When set with httpCode, the slug is a REDIRECTION to targetUrl instead of an element.')]
    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    #[Importable(name: 'targetUrl')]
    public function setTargetUrl(?string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    #[Exportable(description: 'Redirection status (301/302) when targetUrl is set.')]
    public function getHttpCode(): ?int
    {
        return $this->httpCode;
    }

    #[Importable(name: 'httpCode')]
    public function setHttpCode(?int $httpCode): void
    {
        $this->httpCode = $httpCode;
    }

    #[Exportable(description: 'On/off switch.')]
    public function isActive(): bool
    {
        return $this->active;
    }

    #[Importable(name: 'active')]
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

    public function getHostQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Host::class), ['id' => 'hostId']);
    }

    #[Exportable(name: 'xeo', description: 'The SEO/GEO metadata carried by this slug.')]
    public function getXeoQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Xeo::class), ['slugId' => 'id']); // inverseOf('slug') removed: RelationPopulator (yii) crashes on a nullable hasOne in single eager-load — with(...)->each(): [null] is not empty(), reset()=null, then null->relationQuery()
    }

    #[Exportable(name: 'sitemap', description: 'The sitemap entry carried by this slug.')]
    public function getSitemapQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Sitemap::class), ['slugId' => 'id']); // inverseOf('slug') removed: RelationPopulator (yii) crashes on a nullable hasOne in single eager-load — with(...)->each(): [null] is not empty(), reset()=null, then null->relationQuery()
    }

    public function getContentQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Content::class), ['slugId' => 'id']); // inverseOf('slug') removed: RelationPopulator (yii) crashes on a nullable hasOne in single eager-load — with(...)->each(): [null] is not empty(), reset()=null, then null->relationQuery()
    }

    public function getTagQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Tag::class), ['slugId' => 'id']); // inverseOf('slug') removed: RelationPopulator (yii) crashes on a nullable hasOne in single eager-load — with(...)->each(): [null] is not empty(), reset()=null, then null->relationQuery()
    }

    /**
     * Polymorphic: returns Content or Tag linked to this slug.
     */
    public function getElement(): Content|Tag|null
    {
        return $this->getContentQuery()->one() ?? $this->getTagQuery()->one();
    }

    public function getLink(): Link {
        $hostName = null;
        if ($this->targetUrl !== null && $this->httpCode !== null) {
            // redirect mode: direct target url, no template
            $uri = $this->targetUrl;
        } else {
            // classic mode: {host} is always kept so it can be resolved on demand
            $uriBase = Link::BASE_HREF;
            if ($this->hostId > 1) {
                $host = $this->getHostQuery()->one();
                $hostName = $host->getName();
            }
            $uri = $uriBase.'/'.(ltrim($this->path, '/'));
        }
        $link = new Link('', $uri);
        if ($hostName !== null) {
            $link = $link->withTemplate('host', $hostName);
        }
        return $link;
    }
}
