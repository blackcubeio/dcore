<?php

declare(strict_types=1);

/**
 * BaseContent.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Traits\LinkTrait;
use Blackcube\Dcore\Traits\ModelKindTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseContent - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * Note: Date event attributes (DefaultDateTimeOnInsert, SetDateTimeOnUpdate)
 * must be declared on the final child class (Content) because PHP attributes
 * are not automatically inherited.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseContent extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use LinkTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected ?string $name = null;
    protected ?int $slugId = null;
    protected ?string $languageId = null;
    protected ?int $translationGroupId = null;
    protected ?int $typeId = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateStart = null;
    protected ?DateTimeImmutable $dateEnd = null;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%contents}}';
    }

    #[Exportable]
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): void
    {
        if (isset($this->id) && $this->id !== $id) {
            throw new \LogicException('Cannot change ID on existing record');
        }
        $this->id = $id;
    }

    #[Exportable]
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlugId(): ?int
    {
        return $this->slugId;
    }

    public function setSlugId(?int $slugId): void
    {
        $this->slugId = $slugId;
    }

    #[Exportable]
    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getTranslationGroupId(): ?int
    {
        return $this->translationGroupId;
    }

    public function setTranslationGroupId(?int $translationGroupId): void
    {
        $this->translationGroupId = $translationGroupId;
    }

    #[Exportable]
    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    public function setTypeId(?int $typeId): void
    {
        $this->typeId = $typeId;
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

    #[Exportable(format: 'Y-m-d H:i:s')]
    public function getDateStart(): ?DateTimeImmutable
    {
        return $this->dateStart;
    }

    public function setDateStart(?DateTimeImmutable $dateStart): void
    {
        $this->dateStart = $dateStart;
    }

    #[Exportable(format: 'Y-m-d H:i:s')]
    public function getDateEnd(): ?DateTimeImmutable
    {
        return $this->dateEnd;
    }

    public function setDateEnd(?DateTimeImmutable $dateEnd): void
    {
        $this->dateEnd = $dateEnd;
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
     * Relation to Slug.
     * @relation slug
     */
    #[Exportable(name: 'slug')]
    public function getSlugQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Slug::class), ['id' => 'slugId'])->inverseOf('content');
    }

    /**
     * Relation to Type.
     * @relation type
     */
    public function getTypeQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Type::class), ['id' => 'typeId']);
    }

    /**
     * Relation to Language.
     * @relation language
     */
    public function getLanguageQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Language::class), ['id' => 'languageId']);
    }

    /**
     * Relation to pivot ContentBloc.
     * @relation contentBlocs
     */
    public function getContentBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentBloc::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Bloc via pivot.
     * @relation blocs
     * @see Bloc::getContentsQuery() inverse
     */
    #[Exportable(name: 'blocs')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Bloc::class), ['id' => 'blocId'])
            ->via('contentBlocs')
            ->alias('b')
            ->innerJoin(
                '{{%contents_blocs}} cb',
                'b.[[id]] = cb.[[blocId]] AND cb.[[contentId]] = :contentId',
                [':contentId' => $this->getId()]
            )
            ->orderBy(['cb.[[order]]' => SORT_ASC]);
    }

    /**
     * Relation to pivot ContentTag.
     * @relation contentTags
     */
    public function getContentTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentTag::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     * @see Tag::getContentsQuery() inverse
     */
    #[Exportable(name: 'tags', fields: ['id', 'name'])]
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Tag::class), ['id' => 'tagId'])
            ->via('contentTags')
            ->inverseOf('contents');
    }

    /**
     * Relation to ContentTranslationGroup.
     * @relation contentTranslationGroup
     */
    public function getContentTranslationGroupQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(ContentTranslationGroup::class), ['id' => 'translationGroupId']);
    }

    /**
     * Relation to other Contents in the same ContentTranslationGroup (excluding self).
     * Returns empty query if no group.
     * @relation translations
     */
    public function getTranslationsQuery(): ActiveQueryInterface
    {
        if ($this->translationGroupId === null) {
            return static::query()->andWhere('1 = 0');
        }

        return static::query()
            ->andWhere(['translationGroupId' => $this->translationGroupId])
            ->andWhere(['!=', 'id', $this->getId()]);
    }

    /**
     * Relation to pivot ContentAuthor.
     * @relation contentAuthors
     */
    public function getContentAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentAuthor::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Author via pivot.
     * @relation authors
     */
    #[Exportable(name: 'authors', fields: ['id', 'firstname', 'lastname'])]
    public function getAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Author::class), ['id' => 'authorId'])
            ->via('contentAuthors')
            ->alias('a')
            ->innerJoin(
                '{{%contents_authors}} ca',
                'a.[[id]] = ca.[[authorId]] AND ca.[[contentId]] = :contentId',
                [':contentId' => $this->getId()]
            )
            ->orderBy(['ca.[[order]]' => SORT_ASC]);
    }

}
