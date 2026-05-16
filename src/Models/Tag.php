<?php

declare(strict_types=1);

/**
 * Tag.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Traits\OrderedManagementTrait;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Blackcube\ActiveRecord\Elastic\ElasticInterface;
use Blackcube\ActiveRecord\Hazeltree\HazeltreeInterface;
use Blackcube\ActiveRecord\HazeltreeElasticTrait;
use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Tag model - Taxonomy with tree structure and dynamic properties.
 * Uses HazeltreeTrait for tree structure (limited to 2-3 levels).
 * Uses ElasticTrait for dynamic properties.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class Tag extends BaseTag implements HazeltreeInterface, ElasticInterface
{
    use HazeltreeElasticTrait;
    use ScopedQueryTrait;
    use OrderedManagementTrait;

    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return ElasticHazeltreeQuery::create($modelClass ?? static::class);
    }

    /**
     * Get Hazeltree path for export.
     */
    #[Exportable(name: 'path')]
    public function getTreePath(): ?string
    {
        return $this->path;
    }

    /**
     * Get Elastic Schema ID.
     * Used for form <-> model mapping.
     */
    #[Exportable]
    public function getElasticSchemaId(): mixed
    {
        return $this->elasticSchemaId;
    }

    /**
     * Get elastic data for export.
     *
     * @return array<string, mixed>
     */
    #[Exportable(name: 'data')]
    public function getData(): array
    {
        return $this->getElasticValues();
    }

    /**
     * Set Elastic Schema ID.
     * Used for form <-> model mapping.
     */
    public function setElasticSchemaId(mixed $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
    }

    // ========================================
    // OrderedManagementTrait implementation
    // ========================================

    protected function getOrderedConfig(string $entityType): array
    {
        return match ($entityType) {
            'bloc' => ['pivotClass' => TagBloc::class, 'fkColumn' => 'tagId', 'entityIdColumn' => 'blocId', 'owned' => true],
            'author' => ['pivotClass' => TagAuthor::class, 'fkColumn' => 'tagId', 'entityIdColumn' => 'authorId', 'owned' => false],
            default => throw new \InvalidArgumentException("Unknown entity type: $entityType"),
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

    // ========================================
    // Translation management
    // ========================================

    /**
     * Link a Tag as translation of $this.
     *
     * @param int|string|self $target Tag, Tag ID (int or numeric string) to link
     * @throws \InvalidArgumentException if $target not found
     * @throws \LogicException if both have groups, or language already in group
     */
    public function linkTranslation(int|string|self $target): void
    {
        // Resolve target
        if (is_numeric($target)) {
            $target = static::query()->andWhere(['id' => (int) $target])->one();
            if ($target === null) {
                throw new \InvalidArgumentException('Target Tag not found');
            }
        } elseif (!$target instanceof self) {
            throw new \InvalidArgumentException('Invalid target type');
        }

        // Validate same level (tags can only be linked within the same tree level)
        if ($this->getLevel() !== $target->getLevel()) {
            throw new \LogicException('Cannot link translations with different tree levels');
        }

        // Validate different languages
        if ($this->languageId === $target->getLanguageId()) {
            throw new \LogicException('Cannot link translations with the same language');
        }

        // Validate not both have groups
        if ($this->translationGroupId !== null && $target->getTranslationGroupId() !== null) {
            throw new \LogicException('Cannot link: both Tags already belong to a TagTranslationGroup');
        }

        // Validate target language not already in group
        if ($this->translationGroupId !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getLanguageId()])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in TagTranslationGroup');
            }
        }
        if ($target->getTranslationGroupId() !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $target->getTranslationGroupId()])
                ->andWhere(['languageId' => $this->languageId])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in TagTranslationGroup');
            }
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();
        try {
            if ($this->translationGroupId !== null) {
                // $this has group, target orphan -> assign target to $this's group
                $target->setTranslationGroupId($this->translationGroupId);
                $target->save();
            } elseif ($target->getTranslationGroupId() !== null) {
                // $this orphan, target has group -> assign $this to target's group
                $this->translationGroupId = $target->getTranslationGroupId();
                $this->save();
            } else {
                // Both orphans -> create group, assign both
                $group = new TagTranslationGroup();
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
     * Unlink from tag translation group.
     *
     * @param int|string|Language|self|null $target
     *        - null: $this removes itself from group
     *        - int: Tag ID to remove
     *        - string: Language ID to remove
     *        - Language: Language to remove
     *        - Tag: Tag to remove
     * @throws \InvalidArgumentException if $target not found or not in group
     */
    public function unlinkTranslation(int|string|Language|self|null $target = null): void
    {
        if ($this->translationGroupId === null) {
            throw new \InvalidArgumentException('Tag is not in a TagTranslationGroup');
        }

        // Resolve target to Tag
        if ($target === null) {
            $tagToRemove = $this;
        } elseif ($target instanceof self) {
            $tagToRemove = $target;
        } elseif ($target instanceof Language) {
            $tagToRemove = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getId()])
                ->one();
        } elseif (is_numeric($target)) {
            $tagToRemove = static::query()
                ->andWhere(['id' => (int) $target])
                ->one();
        } elseif (is_string($target)) {
            $tagToRemove = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target])
                ->one();
        } else {
            throw new \InvalidArgumentException('Invalid target type');
        }

        if ($tagToRemove === null) {
            throw new \InvalidArgumentException('Target Tag not found');
        }

        // Validate same group
        if ($tagToRemove->getTranslationGroupId() !== $this->translationGroupId) {
            throw new \InvalidArgumentException('Target Tag is not in the same TagTranslationGroup');
        }

        $groupId = $this->translationGroupId;

        $db = $this->db();
        $transaction = $db->beginTransaction();
        try {
            // Remove from group
            $tagToRemove->setTranslationGroupId(null);
            $tagToRemove->save();

            // Count remaining in group
            $remainingCount = static::query()
                ->andWhere(['translationGroupId' => $groupId])
                ->count();

            if ($remainingCount <= 1) {
                // Get last member if exists
                $lastMember = static::query()
                    ->andWhere(['translationGroupId' => $groupId])
                    ->one();

                if ($lastMember !== null) {
                    $lastMember->setTranslationGroupId(null);
                    $lastMember->save();
                }

                // Delete the group
                $group = TagTranslationGroup::query()->andWhere(['id' => $groupId])->one();
                if ($group !== null) {
                    $group->delete();
                }
            }

            // Refresh $this if it was not the one removed
            if ($tagToRemove !== $this) {
                $this->refresh();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    // ========================================
    // Override delete for cascade
    // ========================================

    /**
     * Override delete to cascade delete related entities.
     * Deletes: Blocs (orphaned), Xeo, Sitemap, Slug, TagTranslationGroup (if orphaned)
     *
     * Note: We override delete() instead of deleteInternal() because
     * HazeltreeTrait::hazeltreeDeleteInternal() doesn't continue the chain.
     *
     * @return int Number of rows deleted
     */
    public function delete(): int
    {
        // Store IDs before delete
        $slugId = $this->slugId;
        $translationGroupId = $this->translationGroupId;

        // Get all descendant IDs (for bloc cleanup)
        // HazeltreeTrait provides $left and $right properties
        $descendantIds = static::query()
            ->andWhere(['>', 'left', $this->left])
            ->andWhere(['<', 'right', $this->right])
            ->select(['id'])
            ->column();
        $allTagIds = array_merge([$this->getId()], $descendantIds);

        // Get all bloc IDs attached to this tag and descendants
        $blocIds = TagBloc::query()
            ->andWhere(['tagId' => $allTagIds])
            ->select(['blocId'])
            ->column();

        // Call parent::delete() - HazeltreeTrait handles tree deletion
        $deletedCount = parent::delete();

        // Cascade: Delete Slug (which will cascade to Xeo/Sitemap via FK)
        if ($slugId !== null) {
            $slug = Slug::query()->andWhere(['id' => $slugId])->one();
            if ($slug !== null) {
                $slug->delete();
            }
        }

        // Cascade: Delete orphaned Blocs
        // A bloc is orphaned if it's not attached to any other Content or Tag
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

        // Cascade: Delete orphaned TagTranslationGroup
        if ($translationGroupId !== null) {
            $remainingTags = self::query()
                ->andWhere(['translationGroupId' => $translationGroupId])
                ->count();
            if ($remainingTags === 0) {
                $translationGroup = TagTranslationGroup::query()
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
