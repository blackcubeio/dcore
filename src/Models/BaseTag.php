<?php

declare(strict_types=1);

/**
 * BaseTag.php
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
use Blackcube\Dcore\Traits\LinkTrait;
use Blackcube\Dcore\Traits\ModelKindTrait;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\EventsTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * BaseTag - Pure Yii3 ActiveRecord base class.
 * Contains: properties, relations only.
 * No Blackcube traits here.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class BaseTag extends ActiveRecord
{
    use MagicRelationsTrait;
    use MagicPropertiesTrait;
    use EventsTrait;
    use LinkTrait;
    use ModelKindTrait;

    abstract protected function fqcn(string $fqcn): string;

    protected int $id;
    protected string $name = '';
    protected ?int $slugId = null;
    protected ?string $languageId = null;
    protected ?int $translationGroupId = null;
    protected ?int $typeId = null;
    protected bool $active = true;
    protected ?DateTimeImmutable $dateCreate = null;
    protected ?DateTimeImmutable $dateUpdate = null;

    public function tableName(): string
    {
        return '{{%tags}}';
    }

    #[Exportable(description: 'Database id.')]
    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): void
    {
        if (isset($this->id) === true && $this->id !== $id) {
            throw new \LogicException('Cannot change ID on existing record');
        }
        $this->id = $id;
    }

    #[Exportable(description: 'Display name.')]
    public function getName(): string
    {
        return $this->name;
    }

    #[Importable(name: 'name')]
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

    #[Exportable(description: 'Language of this node.')]
    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    #[Importable(name: 'languageId')]
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

    #[Exportable(description: 'Type, which constrains the allowed bloc schemas and the render handler.')]
    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    #[Importable(name: 'typeId')]
    public function setTypeId(?int $typeId): void
    {
        $this->typeId = $typeId;
    }

    #[Exportable(description: 'Editor on/off switch.')]
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

    /**
     * Relation to Slug.
     * @relation slug
     */
    #[Exportable(name: 'slug', description: 'Same as Content: URL + xeo + sitemap, nullable (non-routable).')]
    public function getSlugQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Slug::class), ['id' => 'slugId']); // inverseOf 'tag' removed: yii RelationPopulator bug on nullable hasOne in single eager-load (with-each)
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
     * Relation to Type.
     * @relation type
     */
    public function getTypeQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(Type::class), ['id' => 'typeId']);
    }

    /**
     * Relation to pivot TagBloc.
     * @relation tagBlocs
     */
    public function getTagBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TagBloc::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Bloc via pivot.
     * @relation blocs
     * @see Bloc::getTagsQuery() inverse
     */
    #[Exportable(name: 'blocs', description: 'Same as Content: ordered structured content.')]
    public function getBlocsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Bloc::class), ['id' => 'blocId'])
            ->via('tagBlocs')
            ->alias('b')
            ->innerJoin(
                '{{%tags_blocs}} tb',
                'b.[[id]] = tb.[[blocId]] AND tb.[[tagId]] = :tagId',
                [':tagId' => $this->getId()]
            )
            ->orderBy(['tb.[[order]]' => SORT_ASC]);
    }

    /**
     * Relation to pivot ContentTag.
     * @relation contentTags
     */
    public function getContentTagsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(ContentTag::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Content via pivot.
     * @relation contents
     * @see Content::getTagsQuery() inverse
     */
    public function getContentsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Content::class), ['id' => 'contentId'])
            ->via('contentTags')
            ->inverseOf('tags');
    }

    /**
     * Relation to pivot TagAuthor.
     * @relation tagAuthors
     */
    public function getTagAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(TagAuthor::class), ['tagId' => 'id']);
    }

    /**
     * Relation to Author via pivot.
     * @relation authors
     */
    #[Exportable(name: 'authors', fields: ['id', 'firstname', 'lastname'], description: 'Ordered authors.')]
    public function getAuthorsQuery(): ActiveQueryInterface
    {
        return $this->hasMany($this->fqcn(Author::class), ['id' => 'authorId'])
            ->via('tagAuthors')
            ->alias('a')
            ->innerJoin(
                '{{%tags_authors}} ta',
                'a.[[id]] = ta.[[authorId]] AND ta.[[tagId]] = :tagId',
                [':tagId' => $this->getId()]
            )
            ->orderBy(['ta.[[order]]' => SORT_ASC]);
    }

    /**
     * Relation to TagTranslationGroup.
     * @relation tagTranslationGroup
     */
    public function getTagTranslationGroupQuery(): ActiveQueryInterface
    {
        return $this->hasOne($this->fqcn(TagTranslationGroup::class), ['id' => 'translationGroupId']);
    }

    /**
     * Relation to other Tags in the same TagTranslationGroup (excluding self).
     * Returns empty query if no group.
     * @relation translations
     */
    #[Exportable(name: 'translations', fields: ['id', 'languageId'], description: 'Other language variants linked through the same translationGroup.')]
    public function getTranslationsQuery(): ActiveQueryInterface
    {
        if ($this->translationGroupId === null) {
            $query = static::query()->emulateExecution()->multiple(true);
        } else {
            $query = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['!=', 'id', $this->getId()])
                ->multiple(true);
        }

        return $query;
    }
}
