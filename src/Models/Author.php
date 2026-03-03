<?php

declare(strict_types=1);

/**
 * Author.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
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
 * Author model - Authors for JSON-LD Article (E-E-A-T).
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class Author extends ActiveRecord
{
    use EventsTrait;
    use MagicRelationsTrait;
    use PopulatePropertyTrait;
    use NamespaceResolverTrait;

    protected int $id;
    protected string $firstname = '';
    protected string $lastname = '';
    protected ?string $email = null;
    protected ?string $jobTitle = null;
    protected ?string $worksFor = null;
    protected ?string $knowsAbout = null;
    protected ?string $sameAs = null;
    protected ?string $url = null;
    protected ?string $image = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%authors}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    #[Exportable]
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    #[Exportable]
    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    #[Exportable]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    #[Exportable]
    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): void
    {
        $this->jobTitle = $jobTitle;
    }

    #[Exportable]
    public function getWorksFor(): ?string
    {
        return $this->worksFor;
    }

    public function setWorksFor(?string $worksFor): void
    {
        $this->worksFor = $worksFor;
    }

    #[Exportable]
    public function getKnowsAbout(): ?string
    {
        return $this->knowsAbout;
    }

    public function setKnowsAbout(?string $knowsAbout): void
    {
        $this->knowsAbout = $knowsAbout;
    }

    #[Exportable]
    public function getSameAs(): ?string
    {
        return $this->sameAs;
    }

    public function setSameAs(?string $sameAs): void
    {
        $this->sameAs = $sameAs;
    }

    #[Exportable]
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
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
     * Relation to pivot ContentAuthor.
     * @relation contentAuthors
     */
    public function getContentAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(ContentAuthor::class), ['authorId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Content::class), ['id' => 'contentId'])
            ->via('contentAuthors');
    }

    /**
     * Relation to pivot TagAuthor.
     * @relation tagAuthors
     */
    public function getTagAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(TagAuthor::class), ['authorId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     */
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Tag::class), ['id' => 'tagId'])
            ->via('tagAuthors');
    }
}
