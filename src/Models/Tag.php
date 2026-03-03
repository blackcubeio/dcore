<?php

declare(strict_types=1);

/**
 * Tag.php
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
use Blackcube\Elastic\ElasticInterface;
use Blackcube\Elastic\ElasticTrait;
use Blackcube\FormModel\Attributes\ParentProperty;
use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
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
#[ParentProperty(name: 'elasticSchemaId', type: 'int', getter: 'getElasticSchemaId', setter: 'setElasticSchemaId')]
class Tag extends BaseTag implements HazeltreeInterface, ElasticInterface
{
    use MagicComposeActiveRecordTrait;
    use HazeltreeTrait;
    use ElasticTrait;
    use BlocManagementTrait;
    use AuthorManagementTrait;

    /**
     * Get Elastic Schema ID.
     * Used for form <-> model mapping.
     */
    public function getElasticSchemaId(): mixed
    {
        return $this->elasticSchemaId;
    }

    /**
     * Set Elastic Schema ID.
     * Used for form <-> model mapping.
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
        return TagBloc::class;
    }

    protected function getBlocPivotFkColumn(): string
    {
        return 'tagId';
    }

    // ========================================
    // AuthorManagementTrait implementation
    // ========================================

    protected function getAuthorPivotClass(): string
    {
        return TagAuthor::class;
    }

    protected function getAuthorPivotFkColumn(): string
    {
        return 'tagId';
    }

    // ========================================
    // Override delete for cascade
    // ========================================

    /**
     * Override delete to cascade delete related entities.
     * Deletes: Blocs (orphaned), Xeo, Sitemap, Slug
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

        return $deletedCount;
    }
}
