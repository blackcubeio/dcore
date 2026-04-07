<?php

declare(strict_types=1);

/**
 * TagManagementTrait.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Models\Tag;

/**
 * TagManagementTrait - Manage tags attached to Content.
 * Note: Tags are shared and NOT deleted when detached.
 */
trait TagManagementTrait
{
    /**
     * Get the pivot class for tag relationships.
     * @return string ContentTag::class or equivalent
     */
    abstract protected function getTagPivotClass(): string;

    /**
     * Get the FK column name in the pivot table.
     * @return string 'contentId' or equivalent
     */
    abstract protected function getTagPivotFkColumn(): string;

    /**
     * Get setter method name for FK.
     */
    private function getTagPivotSetterMethod(): string
    {
        return 'set' . ucfirst($this->getTagPivotFkColumn());
    }

    /**
     * Attach a tag to this content.
     *
     * @param Tag $tag The tag to attach
     */
    public function attachTag(Tag $tag): void
    {
        $pivotClass = $this->getTagPivotClass();
        $fkColumn = $this->getTagPivotFkColumn();

        // Check if already attached
        $existing = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'tagId' => $tag->getId()])
            ->one();

        if ($existing !== null) {
            return;
        }

        $pivot = new $pivotClass();
        $setterMethod = $this->getTagPivotSetterMethod();
        $pivot->$setterMethod($this->getId());
        $pivot->setTagId($tag->getId());
        $pivot->save();
    }

    /**
     * Detach a tag from this content.
     * Note: The tag itself is NOT deleted (tags are shared).
     *
     * @param Tag $tag The tag to detach
     */
    public function detachTag(Tag $tag): void
    {
        $pivotClass = $this->getTagPivotClass();
        $fkColumn = $this->getTagPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'tagId' => $tag->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $pivot->delete();
    }

    /**
     * Check if a tag is attached to this content.
     *
     * @param Tag $tag The tag to check
     */
    public function hasTag(Tag $tag): bool
    {
        $pivotClass = $this->getTagPivotClass();
        $fkColumn = $this->getTagPivotFkColumn();

        return $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'tagId' => $tag->getId()])
            ->exists();
    }

    /**
     * Sync tags: attach missing, detach removed.
     *
     * @param Tag[] $tags Array of tags that should be attached
     */
    public function syncTags(array $tags): void
    {
        $pivotClass = $this->getTagPivotClass();
        $fkColumn = $this->getTagPivotFkColumn();

        $newTagIds = array_map(fn(Tag $t) => $t->getId(), $tags);

        // Get current tag IDs
        $currentPivots = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->all();

        $currentTagIds = array_map(fn($p) => $p->getTagId(), $currentPivots);

        // Detach removed tags
        $toDetach = array_diff($currentTagIds, $newTagIds);
        foreach ($currentPivots as $pivot) {
            if (in_array($pivot->getTagId(), $toDetach, true)) {
                $pivot->delete();
            }
        }

        // Attach new tags
        $toAttach = array_diff($newTagIds, $currentTagIds);
        foreach ($tags as $tag) {
            if (in_array($tag->getId(), $toAttach, true)) {
                $this->attachTag($tag);
            }
        }
    }

    /**
     * Get the count of attached tags.
     */
    public function getTagCount(): int
    {
        $pivotClass = $this->getTagPivotClass();
        $fkColumn = $this->getTagPivotFkColumn();

        return (int) $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->count();
    }
}
