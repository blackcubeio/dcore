<?php

declare(strict_types=1);

/**
 * BaseContent.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Traits\LinkTrait;
use Blackcube\Dcore\Traits\NamespaceResolverTrait;
use Closure;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
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
    use NamespaceResolverTrait;

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

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

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

    public function setDateCreate(?DateTimeImmutable $dateCreate): void
    {
        $this->dateCreate = $dateCreate;
    }

    public function getDateUpdate(): ?DateTimeImmutable
    {
        return $this->dateUpdate;
    }

    public function setDateUpdate(?DateTimeImmutable $dateUpdate): void
    {
        $this->dateUpdate = $dateUpdate;
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
        return $this->hasOne($this->resolve(Slug::class), ['id' => 'slugId'])->inverseOf('content');
    }

    public function getSlug(): ?Slug
    {
        return $this->getSlugQuery()->one();
    }

    /**
     * Relation to Type.
     * @relation type
     */
    public function getTypeQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(Type::class), ['id' => 'typeId']);
    }

    public function getType(): ?Type
    {
        return $this->getTypeQuery()->one();
    }

    /**
     * Relation to Language.
     * @relation language
     */
    public function getLanguageQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(Language::class), ['id' => 'languageId']);
    }

    /**
     * Relation to pivot ContentBloc.
     * @relation contentBlocs
     */
    public function getContentBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(ContentBloc::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Bloc via pivot.
     * @relation blocs
     * @see Bloc::getContentsQuery() inverse
     */
    #[Exportable(name: 'blocs')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Bloc::class), ['id' => 'blocId'])
            ->via('contentBlocs')
            ->innerJoin(
                '{{%contents_blocs}} cb',
                '{{%blocs}}.[[id]] = cb.[[blocId]] AND cb.[[contentId]] = :contentId',
                [':contentId' => $this->getId()]
            )
            ->orderBy(['cb.[[order]]' => SORT_ASC]);
    }

    /**
     * @return Bloc[]
     */
    public function getBlocs(): array
    {
        return $this->getBlocsQuery()->all();
    }

    /**
     * Relation to pivot ContentTag.
     * @relation contentTags
     */
    public function getContentTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(ContentTag::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Tag via pivot.
     * @relation tags
     * @see Tag::getContentsQuery() inverse
     */
    #[Exportable(name: 'tags', fields: ['id', 'name'])]
    public function getTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Tag::class), ['id' => 'tagId'])
            ->via('contentTags')
            ->inverseOf('contents');
    }

    /**
     * @return Tag[]
     */
    public function getTags(): array
    {
        return $this->getTagsQuery()->all();
    }

    /**
     * Relation to TranslationGroup.
     * @relation translationGroup
     */
    public function getTranslationGroupQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->resolve(TranslationGroup::class), ['id' => 'translationGroupId']);
    }

    /**
     * Relation to other Contents in the same TranslationGroup (excluding self).
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
        return $this->hasMany($this->resolve(ContentAuthor::class), ['contentId' => 'id']);
    }

    /**
     * Relation to Author via pivot.
     * @relation authors
     */
    public function getAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Author::class), ['id' => 'authorId'])
            ->via('contentAuthors')
            ->innerJoin(
                '{{%contents_authors}} ca',
                '{{%authors}}.[[id]] = ca.[[authorId]] AND ca.[[contentId]] = :contentId',
                [':contentId' => $this->getId()]
            )
            ->orderBy(['ca.[[order]]' => SORT_ASC]);
    }

}
