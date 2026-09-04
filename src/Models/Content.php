<?php

declare(strict_types=1);

/**
 * Content.php
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
use Blackcube\Dcore\Attributes\ResetQueryCacheOnWrite;
use Blackcube\Dcore\Traits\OrderedManagementTrait;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Blackcube\Dcore\Traits\TagManagementTrait;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeInterface;
use Blackcube\ActiveRecord\HazeltreeElasticTrait;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Content model - Contents with tree structure and dynamic properties.
 * Uses HazeltreeTrait for tree structure.
 * Uses ElasticTrait for dynamic properties.
 *
 * Note: Date attributes must be declared here (not inherited from BaseContent)
 * because PHP attributes are not automatically inherited by child classes.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
#[ResetQueryCacheOnWrite]
class Content extends BaseContent implements HazeltreeInterface, ElasticInterface
{
    use HazeltreeElasticTrait;
    use ScopedQueryTrait;
    use OrderedManagementTrait;
    use TagManagementTrait;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return ElasticHazeltreeQuery::create($modelClass ?? static::class);
    }

    /**
     * Get Hazeltree path for export.
     */
    #[Exportable(name: 'path', description: 'Hazeltree path: the position of the node in the tree.')]
    public function getTreePath(): ?string
    {
        return $this->path;
    }

    /**
     * Get Elastic Schema ID.
     * used for form <-> model mapping.
     * @return mixed
     */
    #[Exportable(description: 'The ElasticSchema validating the page-level data fields.')]
    public function getElasticSchemaId(): mixed
    {
        return $this->elasticSchemaId;
    }

    /**
     * Get elastic data for export.
     *
     * @return array<string, mixed>
     */
    #[Exportable(name: 'data', description: 'Elastic (EAV) field values, validated by elasticSchemaId.')]
    public function getData(): array
    {
        return $this->getElasticValues();
    }

    /**
     * Set Elastic Schema ID.
     * used for form <-> model mapping.
     * @param mixed $elasticSchemaId
     */
    #[Importable(name: 'elasticSchemaId')]
    public function setElasticSchemaId(mixed $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
    }

    protected function getOrderedConfig(string $entityType): array
    {
        return match ($entityType) {
            'bloc' => ['pivotClass' => ContentBloc::class, 'fkColumn' => 'contentId', 'entityIdColumn' => 'blocId', 'owned' => true],
            'author' => ['pivotClass' => ContentAuthor::class, 'fkColumn' => 'contentId', 'entityIdColumn' => 'authorId', 'owned' => false],
            default => throw new \InvalidArgumentException('Unknown entity type: '.$entityType),
        };
    }

    public function attachBloc(Bloc $bloc, int $position = 0): void { $this->attachOrdered('bloc', $bloc, $position); }
    public function detachBloc(Bloc $bloc): void { $this->detachOrdered('bloc', $bloc); }
    public function moveBloc(Bloc $bloc, int $position): void { $this->moveOrdered('bloc', $bloc, $position); }
    public function moveBlocUp(Bloc $bloc): void { $this->moveOrderedUp('bloc', $bloc); }
    public function moveBlocDown(Bloc $bloc): void { $this->moveOrderedDown('bloc', $bloc); }
    public function reorderBlocs(): void { $this->reorderOrdered('bloc'); }
    public function getBlocCount(): int { return $this->getOrderedCount('bloc'); }

    public function attachAuthor(Author $author, int $position = 0): void { $this->attachOrdered('author', $author, $position); }
    public function detachAuthor(Author $author): void { $this->detachOrdered('author', $author); }
    public function moveAuthor(Author $author, int $position): void { $this->moveOrdered('author', $author, $position); }
    public function moveAuthorUp(Author $author): void { $this->moveOrderedUp('author', $author); }
    public function moveAuthorDown(Author $author): void { $this->moveOrderedDown('author', $author); }
    public function reorderAuthors(): void { $this->reorderOrdered('author'); }
    public function getAuthorCount(): int { return $this->getOrderedCount('author'); }
    public function hasAuthor(Author $author): bool { return $this->hasOrdered('author', $author); }

    protected function getTagPivotClass(): string
    {
        return ContentTag::class;
    }

    protected function getTagPivotFkColumn(): string
    {
        return 'contentId';
    }

    /**
     * Link a Content as translation of $this.
     *
     * @param int|string|self $target Content, Content ID (int or numeric string) to link
     * @throws \InvalidArgumentException if $target not found
     * @throws \LogicException if both have groups, or language already in group
     */
    public function linkTranslation(int|string|self $target): void
    {
        if (is_numeric($target) === true) {
            $target = static::query()->andWhere(['id' => (int) $target])->one();
            if ($target === null) {
                throw new \InvalidArgumentException('Target Content not found');
            }
        } elseif ($target instanceof self === false) {
            throw new \InvalidArgumentException('Invalid target type');
        }

        if ($this->languageId === $target->getLanguageId()) {
            throw new \LogicException('Cannot link translations with the same language');
        }

        if ($this->translationGroupId !== null && $target->getTranslationGroupId() !== null) {
            throw new \LogicException('Cannot link: both Contents already belong to a ContentTranslationGroup');
        }

        if ($this->translationGroupId !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getLanguageId()])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in ContentTranslationGroup');
            }
        }
        if ($target->getTranslationGroupId() !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $target->getTranslationGroupId()])
                ->andWhere(['languageId' => $this->languageId])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in ContentTranslationGroup');
            }
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();
        try {
            if ($this->translationGroupId !== null) {
                $target->setTranslationGroupId($this->translationGroupId);
                $target->save();
            } elseif ($target->getTranslationGroupId() !== null) {
                $this->translationGroupId = $target->getTranslationGroupId();
                $this->save();
            } else {
                $group = new ContentTranslationGroup();
                $group->save();

                $this->translationGroupId = $group->getId();
                $this->save();

                $target->setTranslationGroupId($group->getId());
                $target->save();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Unlink from translation group.
     *
     * @param int|string|Language|self|null $target
     *        - null: $this removes itself from group
     *        - int: Content ID to remove
     *        - string: Language ID to remove
     *        - Language: Language to remove
     *        - Content: Content to remove
     * @throws \InvalidArgumentException if $target not found or not in group
     */
    public function unlinkTranslation(int|string|Language|self|null $target = null): void
    {
        if ($this->translationGroupId === null) {
            throw new \InvalidArgumentException('Content is not in a ContentTranslationGroup');
        }

        if ($target === null) {
            $contentToRemove = $this;
        } elseif ($target instanceof self) {
            $contentToRemove = $target;
        } elseif ($target instanceof Language) {
            $contentToRemove = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getId()])
                ->one();
        } elseif (is_numeric($target) === true) {
            $contentToRemove = static::query()
                ->andWhere(['id' => (int) $target])
                ->one();
        } elseif (is_string($target) === true) {
            $contentToRemove = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target])
                ->one();
        } else {
            throw new \InvalidArgumentException('Invalid target type');
        }

        if ($contentToRemove === null) {
            throw new \InvalidArgumentException('Target Content not found');
        }

        if ($contentToRemove->getTranslationGroupId() !== $this->translationGroupId) {
            throw new \InvalidArgumentException('Target Content is not in the same ContentTranslationGroup');
        }

        $groupId = $this->translationGroupId;

        $db = $this->db();
        $transaction = $db->beginTransaction();
        try {
            $contentToRemove->setTranslationGroupId(null);
            $contentToRemove->save();

            $remainingCount = static::query()
                ->andWhere(['translationGroupId' => $groupId])
                ->count();

            if ($remainingCount <= 1) {
                $lastMember = static::query()
                    ->andWhere(['translationGroupId' => $groupId])
                    ->one();

                if ($lastMember !== null) {
                    $lastMember->setTranslationGroupId(null);
                    $lastMember->save();
                }

                $group = ContentTranslationGroup::query()->andWhere(['id' => $groupId])->one();
                if ($group !== null) {
                    $group->delete();
                }
            }

            if ($contentToRemove !== $this) {
                $this->refresh();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Override delete to cascade delete related entities.
     * Deletes: Blocs (orphaned), Xeo, Sitemap, Slug, ContentTranslationGroup (if orphaned)
     *
     * Note: We override delete() instead of deleteInternal() because
     * HazeltreeTrait::hazeltreeDeleteInternal() doesn't continue the chain.
     *
     * @return int Number of rows deleted
     */
    public function delete(): int
    {
        $slugId = $this->slugId;
        $translationGroupId = $this->translationGroupId;

        $descendantIds = static::query()
            ->andWhere(['>', 'left', $this->left])
            ->andWhere(['<', 'right', $this->right])
            ->select(['id'])
            ->column();
        $allContentIds = array_merge([$this->getId()], $descendantIds);

        $blocIds = ContentBloc::query()
            ->andWhere(['contentId' => $allContentIds])
            ->select(['blocId'])
            ->column();

        $deletedCount = parent::delete();

        if ($slugId !== null) {
            $slug = Slug::query()->andWhere(['id' => $slugId])->one();
            if ($slug !== null) {
                $slug->delete();
            }
        }

        foreach ($blocIds as $blocId) {
            $contentCount = ContentBloc::query()->andWhere(['blocId' => $blocId])->count();
            $tagCount = TagBloc::query()->andWhere(['blocId' => $blocId])->count();
            if ($contentCount === 0 && $tagCount === 0) {
                $bloc = Bloc::query()->andWhere(['id' => $blocId])->one();
                if ($bloc !== null) {
                    $bloc->delete();
                }
            }
        }

        if ($translationGroupId !== null) {
            $remainingContents = self::query()
                ->andWhere(['translationGroupId' => $translationGroupId])
                ->count();
            if ($remainingContents === 0) {
                $translationGroup = ContentTranslationGroup::query()
                    ->andWhere(['id' => $translationGroupId])
                    ->one();
                if ($translationGroup !== null) {
                    $translationGroup->delete();
                }
            }
        }

        return $deletedCount;
    }
}
