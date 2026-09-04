<?php

declare(strict_types=1);

/**
 * BaseXeo.php
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
use Blackcube\Dcore\Models\Formatters\FileFormatter;
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
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
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

    #[Exportable(description: 'Emit a canonical link for this slug.')]
    public function isCanonical(): bool
    {
        return $this->canonicalSlugId !== null && $this->canonicalSlugId === $this->slugId;
    }

    public function setCanonicalSlugId(?int $canonicalSlugId): void
    {
        $this->canonicalSlugId = $canonicalSlugId;
    }

    #[Exportable(description: 'SEO title (<title> / og:title).')]
    public function getTitle(): ?string
    {
        return $this->title;
    }

    #[Importable(name: 'title')]
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    #[Exportable(format: FileFormatter::class, description: 'Social share image, as a file reference.')]
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    #[Exportable(description: 'SEO meta description.')]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    #[Importable(name: 'description')]
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    #[Exportable(description: 'robots noindex flag.')]
    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    #[Importable(name: 'noindex')]
    public function setNoindex(bool $noindex): void
    {
        $this->noindex = $noindex;
    }

    #[Exportable(description: 'robots nofollow flag.')]
    public function isNofollow(): bool
    {
        return $this->nofollow;
    }

    #[Importable(name: 'nofollow')]
    public function setNofollow(bool $nofollow): void
    {
        $this->nofollow = $nofollow;
    }

    #[Exportable(description: 'Emit Open Graph tags.')]
    public function isOg(): bool
    {
        return $this->og;
    }

    #[Importable(name: 'og')]
    public function setOg(bool $og): void
    {
        $this->og = $og;
    }

    #[Exportable(description: 'Open Graph og:type (e.g. website, article).')]
    public function getOgType(): ?string
    {
        return $this->ogType;
    }

    #[Importable(name: 'ogType')]
    public function setOgType(?string $ogType): void
    {
        $this->ogType = $ogType;
    }

    #[Exportable(description: 'Emit Twitter Card tags.')]
    public function isTwitter(): bool
    {
        return $this->twitter;
    }

    #[Importable(name: 'twitter')]
    public function setTwitter(bool $twitter): void
    {
        $this->twitter = $twitter;
    }

    #[Exportable(description: 'Twitter card type (e.g. summary_large_image).')]
    public function getTwitterCard(): ?string
    {
        return $this->twitterCard;
    }

    #[Importable(name: 'twitterCard')]
    public function setTwitterCard(?string $twitterCard): void
    {
        $this->twitterCard = $twitterCard;
    }

    #[Exportable(description: 'JSON-LD schema.org @type.')]
    public function getJsonldType(): string
    {
        return $this->jsonldType;
    }

    #[Importable(name: 'jsonldType')]
    public function setJsonldType(string $jsonldType): void
    {
        $this->jsonldType = $jsonldType;
    }

    #[Exportable(description: 'Emit the schema.org speakable markup (voice/GEO).')]
    public function isSpeakable(): bool
    {
        return $this->speakable;
    }

    #[Importable(name: 'speakable')]
    public function setSpeakable(bool $speakable): void
    {
        $this->speakable = $speakable;
    }

    #[Exportable(description: 'SEO keywords.')]
    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    #[Importable(name: 'keywords')]
    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    #[Exportable(description: 'schema.org isAccessibleForFree flag.')]
    public function isAccessibleForFree(): bool
    {
        return $this->accessibleForFree;
    }

    #[Importable(name: 'accessibleForFree')]
    public function setAccessibleForFree(bool $accessibleForFree): void
    {
        $this->accessibleForFree = $accessibleForFree;
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

    public function getSlugQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Slug::class), ['id' => 'slugId']); // inverseOf 'xeo' removed: yii RelationPopulator bug on nullable hasOne in single eager-load (with-each)
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
    #[Exportable(name: 'blocs', description: 'Structured blocs feeding rich results (e.g. an FAQ bloc -> FAQPage JSON-LD).')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Bloc::class), ['id' => 'blocId'])
            ->via('xeoBlocs')
            ->alias('b')
            ->innerJoin(
                '{{%xeos_blocs}} xb',
                'b.[[id]] = xb.[[blocId]] AND xb.[[xeoId]] = :xeoId',
                [':xeoId' => $this->getId()]
            )
            ->orderBy(['xb.[[order]]' => SORT_ASC]);
    }
}
