<?php

declare(strict_types=1);

/**
 * BaseTag.php
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
 * BaseTag - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseTag extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use LinkTrait;
    use NamespaceResolverTrait;

    protected int $id;
    protected string $name = '';
    protected ?int $slugId = null;
    protected ?int $typeId = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%tags}}';
    }

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
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
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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
        return $this->hasOne($this->resolve(Slug::class), ['id' => 'slugId'])->inverseOf('tag');
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
     * Relation to pivot TagBloc.
     * @relation tagBlocs
     */
    public function getTagBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(TagBloc::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Bloc via pivot.
     * @relation blocs
     * @see Bloc::getTagsQuery() inverse
     */
    #[Exportable(name: 'blocs')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Bloc::class), ['id' => 'blocId'])
            ->via('tagBlocs')
            ->innerJoin(
                '{{%tags_blocs}} tb',
                '{{%blocs}}.[[id]] = tb.[[blocId]] AND tb.[[tagId]] = :tagId',
                [':tagId' => $this->getId()]
            )
            ->orderBy(['tb.[[order]]' => SORT_ASC]);
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
        return $this->hasMany($this->resolve(ContentTag::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     * @see Content::getTagsQuery() inverse
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Content::class), ['id' => 'contentId'])
            ->via('contentTags')
            ->inverseOf('tags');
    }

    /**
     * @return Content[]
     */
    public function getContents(): array
    {
        return $this->getContentsQuery()->all();
    }

    /**
     * Relation to pivot TagAuthor.
     * @relation tagAuthors
     */
    public function getTagAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(TagAuthor::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Author via pivot.
     * @relation authors
     */
    public function getAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->resolve(Author::class), ['id' => 'authorId'])
            ->via('tagAuthors')
            ->innerJoin(
                '{{%tags_authors}} ta',
                '{{%authors}}.[[id]] = ta.[[authorId]] AND ta.[[tagId]] = :tagId',
                [':tagId' => $this->getId()]
            )
            ->orderBy(['ta.[[order]]' => SORT_ASC]);
    }
}
