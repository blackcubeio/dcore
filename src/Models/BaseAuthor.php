<?php

declare(strict_types=1);

/**
 * BaseAuthor.php
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
 * BaseAuthor - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseAuthor extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

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

    #[Exportable(description: 'Database id.')]
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    #[Exportable(description: 'First name.')]
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    #[Importable(name: 'firstname')]
    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    #[Exportable(description: 'Last name.')]
    public function getLastname(): string
    {
        return $this->lastname;
    }

    #[Importable(name: 'lastname')]
    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    #[Exportable(description: 'Contact email.')]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    #[Importable(name: 'email')]
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    #[Exportable(description: 'schema.org jobTitle.')]
    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    #[Importable(name: 'jobTitle')]
    public function setJobTitle(?string $jobTitle): void
    {
        $this->jobTitle = $jobTitle;
    }

    #[Exportable(description: 'schema.org worksFor (the organization).')]
    public function getWorksFor(): ?string
    {
        return $this->worksFor;
    }

    #[Importable(name: 'worksFor')]
    public function setWorksFor(?string $worksFor): void
    {
        $this->worksFor = $worksFor;
    }

    #[Exportable(description: 'schema.org knowsAbout (areas of expertise).')]
    public function getKnowsAbout(): ?string
    {
        return $this->knowsAbout;
    }

    #[Importable(name: 'knowsAbout')]
    public function setKnowsAbout(?string $knowsAbout): void
    {
        $this->knowsAbout = $knowsAbout;
    }

    #[Exportable(description: 'schema.org sameAs (profile URLs).')]
    public function getSameAs(): ?string
    {
        return $this->sameAs;
    }

    #[Importable(name: 'sameAs')]
    public function setSameAs(?string $sameAs): void
    {
        $this->sameAs = $sameAs;
    }

    #[Exportable(description: 'Personal or profile URL.')]
    public function getUrl(): ?string
    {
        return $this->url;
    }

    #[Importable(name: 'url')]
    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    #[Exportable(format: FileFormatter::class, description: 'Avatar, as a file reference.')]
    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
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

    /**
     * Relation to pivot ContentAuthor.
     * @relation contentAuthors
     */
    public function getContentAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentAuthor::class), ['authorId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Content::class), ['id' => 'contentId'])
            ->via('contentAuthors');
    }

    /**
     * Relation to pivot TagAuthor.
     * @relation tagAuthors
     */
    public function getTagAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TagAuthor::class), ['authorId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     */
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Tag::class), ['id' => 'tagId'])
            ->via('tagAuthors');
    }
}
