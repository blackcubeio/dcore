<?php

declare(strict_types=1);

/**
 * Content.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\Dcore\Traits\AuthorManagementTrait;
use Blackcube\Dcore\Traits\BlocManagementTrait;
use Blackcube\Dcore\Traits\TagManagementTrait;
use Blackcube\Elastic\ElasticInterface;
use Blackcube\Elastic\ElasticTrait;
use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
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
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
class Content extends BaseContent implements HazeltreeInterface, ElasticInterface
{
    use MagicComposeActiveRecordTrait;
    use HazeltreeTrait;
    use ElasticTrait;
    use BlocManagementTrait;
    use TagManagementTrait;
    use AuthorManagementTrait;

    /**
     * Get Elastic Schema ID.
     * used for form <-> model mapping.
     * @return mixed
     */
    public function getElasticSchemaId(): mixed
    {
        return $this->elasticSchemaId;
    }

    /**
     * Set Elastic Schema ID.
     * used for form <-> model mapping.
     * @param mixed $elasticSchemaId
     */
    public function setElasticSchemaId(mixed $elasticSchemaId): void
    {
        $this->elasticSchemaId = $elasticSchemaId;
    }

    /**
     * Override ElasticTrait::query() to use ScopedQuery with Hazeltree support.
     */
    public static function query(ActiveRecordInterface|Closure|string|null $modelClass = null): ActiveQueryInterface
    {
        return new ScopedQuery($modelClass ?? static::class);
    }

    // ========================================
    // BlocManagementTrait implementation
    // ========================================

    protected function getBlocPivotClass(): string
    {
        return ContentBloc::class;
    }

    protected function getBlocPivotFkColumn(): string
    {
        return 'contentId';
    }

    // ========================================
    // TagManagementTrait implementation
    // ========================================

    protected function getTagPivotClass(): string
    {
        return ContentTag::class;
    }

    protected function getTagPivotFkColumn(): string
    {
        return 'contentId';
    }

    // ========================================
    // AuthorManagementTrait implementation
    // ========================================

    protected function getAuthorPivotClass(): string
    {
        return ContentAuthor::class;
    }

    protected function getAuthorPivotFkColumn(): string
    {
        return 'contentId';
    }

    // ========================================
    // Translation management
    // ========================================

    /**
     * Link a Content as translation of $this.
     *
     * @param int|string|self $target Content, Content ID (int or numeric string) to link
     * @throws \InvalidArgumentException if $target not found
     * @throws \LogicException if both have groups, or language already in group
     */
    public function linkTranslation(int|string|self $target): void
    {
        // Resolve target
        if (is_numeric($target)) {
            $target = static::query()->andWhere(['id' => (int) $target])->one();
            if ($target === null) {
                throw new \InvalidArgumentException('Target Content not found');
            }
        } elseif (!$target instanceof self) {
            throw new \InvalidArgumentException('Invalid target type');
        }

        // Validate different languages
        if ($this->languageId === $target->getLanguageId()) {
            throw new \LogicException('Cannot link translations with the same language');
        }

        // Validate not both have groups
        if ($this->translationGroupId !== null && $target->getTranslationGroupId() !== null) {
            throw new \LogicException('Cannot link: both Contents already belong to a TranslationGroup');
        }

        // Validate target language not already in group
        if ($this->translationGroupId !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getLanguageId()])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in TranslationGroup');
            }
        }
        if ($target->getTranslationGroupId() !== null) {
            $existingLanguage = static::query()
                ->andWhere(['translationGroupId' => $target->getTranslationGroupId()])
                ->andWhere(['languageId' => $this->languageId])
                ->one();
            if ($existingLanguage !== null) {
                throw new \LogicException('Language already exists in TranslationGroup');
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
                $group = new TranslationGroup();
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
            throw new \InvalidArgumentException('Content is not in a TranslationGroup');
        }

        // Resolve target to Content
        if ($target === null) {
            $contentToRemove = $this;
        } elseif ($target instanceof self) {
            $contentToRemove = $target;
        } elseif ($target instanceof Language) {
            $contentToRemove = static::query()
                ->andWhere(['translationGroupId' => $this->translationGroupId])
                ->andWhere(['languageId' => $target->getId()])
                ->one();
        } elseif (is_numeric($target)) {
            $contentToRemove = static::query()
                ->andWhere(['id' => (int) $target])
                ->one();
        } elseif (is_string($target)) {
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

        // Validate same group
        if ($contentToRemove->getTranslationGroupId() !== $this->translationGroupId) {
            throw new \InvalidArgumentException('Target Content is not in the same TranslationGroup');
        }

        $groupId = $this->translationGroupId;

        $db = $this->db();
        $transaction = $db->beginTransaction();
        try {
            // Remove from group
            $contentToRemove->setTranslationGroupId(null);
            $contentToRemove->save();

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
                $group = TranslationGroup::query()->andWhere(['id' => $groupId])->one();
                if ($group !== null) {
                    $group->delete();
                }
            }

            // Refresh $this if it was not the one removed
            if ($contentToRemove !== $this) {
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
     * Deletes: Blocs (orphaned), Xeo, Sitemap, Slug, TranslationGroup (if orphaned)
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
        $allContentIds = array_merge([$this->getId()], $descendantIds);

        // Get all bloc IDs attached to this content and descendants
        $blocIds = ContentBloc::query()
            ->andWhere(['contentId' => $allContentIds])
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

        // Cascade: Delete orphaned TranslationGroup
        if ($translationGroupId !== null) {
            $remainingContents = self::query()
                ->andWhere(['translationGroupId' => $translationGroupId])
                ->count();
            if ($remainingContents === 0) {
                $translationGroup = TranslationGroup::query()
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
