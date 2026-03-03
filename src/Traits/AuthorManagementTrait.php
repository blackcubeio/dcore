<?php

declare(strict_types=1);

/**
 * AuthorManagementTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Models\Author;

/**
 * AuthorManagementTrait - Manage authors with ordering.
 * Used by Content and Tag models.
 * Note: Authors are shared and NOT deleted when detached.
 */
trait AuthorManagementTrait
{
    /**
     * Get the pivot class for author relationships.
     * @return string ContentAuthor::class or TagAuthor::class
     */
    abstract protected function getAuthorPivotClass(): string;

    /**
     * Get the FK column name in the pivot table.
     * @return string 'contentId' or 'tagId'
     */
    abstract protected function getAuthorPivotFkColumn(): string;

    /**
     * Attach an author at a specific position.
     * If position <= 0, appends at the end.
     *
     * @param Author $author The author to attach
     * @param int $position Target position (1-based), 0 = append at end
     */
    public function attachAuthor(Author $author, int $position = 0): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        // Check if already attached
        $existing = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->one();

        if ($existing !== null) {
            return;
        }

        $authorCount = $this->getAuthorCount();

        // Normalize position
        if ($position <= 0 || $position > $authorCount + 1) {
            $position = $authorCount + 1;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            // Shift existing authors from target position
            if ($position <= $authorCount) {
                $this->shiftAuthorsFrom($position, 1);
            }

            // Create pivot
            $pivot = new $pivotClass();
            $setterMethod = $this->getAuthorPivotSetterMethod();
            $pivot->$setterMethod($this->getId());
            $pivot->setAuthorId($author->getId());
            $pivot->setOrder($position);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Detach an author.
     * Note: The author itself is NOT deleted (authors are shared).
     *
     * @param Author $author The author to detach
     */
    public function detachAuthor(Author $author): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $order = $pivot->getOrder();

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            $pivot->delete();

            // Fill the gap
            $this->shiftAuthorsFrom($order + 1, -1);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Check if an author is attached.
     *
     * @param Author $author The author to check
     */
    public function hasAuthor(Author $author): bool
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        return $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->exists();
    }

    /**
     * Move an author to a specific position.
     *
     * @param Author $author The author to move
     * @param int $position Target position (1-based)
     */
    public function moveAuthor(Author $author, int $position): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $authorCount = $this->getAuthorCount();

        // Normalize position
        if ($position < 1) {
            $position = 1;
        }
        if ($position > $authorCount) {
            $position = $authorCount;
        }

        if ($currentOrder === $position) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            if ($currentOrder < $position) {
                $this->shiftAuthorsBetween($currentOrder + 1, $position, -1);
            } else {
                $this->shiftAuthorsBetween($position, $currentOrder - 1, 1);
            }

            $pivot->setOrder($position);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Move author up one position.
     *
     * @param Author $author The author to move up
     */
    public function moveAuthorUp(Author $author): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->one();

        if ($pivot === null || $pivot->getOrder() <= 1) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $targetOrder = $currentOrder - 1;

        $otherPivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'order' => $targetOrder])
            ->one();

        if ($otherPivot === null) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            $otherPivot->setOrder($currentOrder);
            $otherPivot->save();

            $pivot->setOrder($targetOrder);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Move author down one position.
     *
     * @param Author $author The author to move down
     */
    public function moveAuthorDown(Author $author): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'authorId' => $author->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $authorCount = $this->getAuthorCount();

        if ($currentOrder >= $authorCount) {
            return;
        }

        $targetOrder = $currentOrder + 1;

        $otherPivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'order' => $targetOrder])
            ->one();

        if ($otherPivot === null) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            $otherPivot->setOrder($currentOrder);
            $otherPivot->save();

            $pivot->setOrder($targetOrder);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Reorder all authors to sequential order (1, 2, 3...).
     */
    public function reorderAuthors(): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivots = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->orderBy(['order' => SORT_ASC])
            ->all();

        if (empty($pivots)) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            $order = 1;
            foreach ($pivots as $pivot) {
                if ($pivot->getOrder() !== $order) {
                    $pivot->setOrder($order);
                    $pivot->save();
                }
                $order++;
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Get the number of attached authors.
     */
    public function getAuthorCount(): int
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        return (int) $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->count();
    }

    /**
     * Shift authors from a position by delta.
     */
    private function shiftAuthorsFrom(int $fromPosition, int $delta): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivots = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->andWhere(['>=', 'order', $fromPosition])
            ->orderBy(['order' => $delta > 0 ? SORT_DESC : SORT_ASC])
            ->all();

        foreach ($pivots as $pivot) {
            $pivot->setOrder($pivot->getOrder() + $delta);
            $pivot->save();
        }
    }

    /**
     * Shift authors between positions by delta.
     */
    private function shiftAuthorsBetween(int $fromPosition, int $toPosition, int $delta): void
    {
        $pivotClass = $this->getAuthorPivotClass();
        $fkColumn = $this->getAuthorPivotFkColumn();

        $pivots = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->andWhere(['>=', 'order', $fromPosition])
            ->andWhere(['<=', 'order', $toPosition])
            ->orderBy(['order' => $delta > 0 ? SORT_DESC : SORT_ASC])
            ->all();

        foreach ($pivots as $pivot) {
            $pivot->setOrder($pivot->getOrder() + $delta);
            $pivot->save();
        }
    }

    /**
     * Get setter method name for FK.
     */
    private function getAuthorPivotSetterMethod(): string
    {
        return 'set' . ucfirst($this->getAuthorPivotFkColumn());
    }
}
