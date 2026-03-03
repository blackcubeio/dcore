<?php

declare(strict_types=1);

/**
 * BlocManagementTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

use Blackcube\Dcore\Models\Bloc;

/**
 * BlocManagementTrait - Manage blocs with ordering.
 * Used by Content and Tag models.
 */
trait BlocManagementTrait
{
    /**
     * Get the pivot class for bloc relationships.
     * @return string ContentBloc::class or TagBloc::class
     */
    abstract protected function getBlocPivotClass(): string;

    /**
     * Get the FK column name in the pivot table.
     * @return string 'contentId' or 'tagId'
     */
    abstract protected function getBlocPivotFkColumn(): string;

    /**
     * Attach a bloc at a specific position.
     * If position <= 0, appends at the end.
     *
     * @param Bloc $bloc The bloc to attach
     * @param int $position Target position (1-based), 0 = append at end
     */
    public function attachBloc(Bloc $bloc, int $position = 0): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        // Check if already attached
        $existing = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'blocId' => $bloc->getId()])
            ->one();

        if ($existing !== null) {
            return;
        }

        $blocCount = $this->getBlocCount();

        // Normalize position
        if ($position <= 0 || $position > $blocCount + 1) {
            $position = $blocCount + 1;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            // Shift existing blocs from target position
            if ($position <= $blocCount) {
                $this->shiftBlocsFrom($position, 1);
            }

            // Create pivot
            $pivot = new $pivotClass();
            $setterMethod = $this->getBlocPivotSetterMethod();
            $pivot->$setterMethod($this->getId());
            $pivot->setBlocId($bloc->getId());
            $pivot->setOrder($position);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Detach a bloc and delete it (blocs are not shared).
     *
     * @param Bloc $bloc The bloc to detach
     */
    public function detachBloc(Bloc $bloc): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'blocId' => $bloc->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $order = $pivot->getOrder();

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            // Delete pivot first
            $pivot->delete();

            // Delete the bloc itself
            $bloc->delete();

            // Fill the gap
            $this->shiftBlocsFrom($order + 1, -1);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Move a bloc to a specific position.
     *
     * @param Bloc $bloc The bloc to move
     * @param int $position Target position (1-based)
     */
    public function moveBloc(Bloc $bloc, int $position): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'blocId' => $bloc->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $blocCount = $this->getBlocCount();

        // Normalize position
        if ($position < 1) {
            $position = 1;
        }
        if ($position > $blocCount) {
            $position = $blocCount;
        }

        if ($currentOrder === $position) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            if ($currentOrder < $position) {
                // Moving down: shift items between (current+1, target) up by -1
                $this->shiftBlocsBetween($currentOrder + 1, $position, -1);
            } else {
                // Moving up: shift items between (target, current-1) down by +1
                $this->shiftBlocsBetween($position, $currentOrder - 1, 1);
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
     * Move bloc up one position.
     *
     * @param Bloc $bloc The bloc to move up
     */
    public function moveBlocUp(Bloc $bloc): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'blocId' => $bloc->getId()])
            ->one();

        if ($pivot === null || $pivot->getOrder() <= 1) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $targetOrder = $currentOrder - 1;

        // Find the bloc at target position and swap
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
     * Move bloc down one position.
     *
     * @param Bloc $bloc The bloc to move down
     */
    public function moveBlocDown(Bloc $bloc): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), 'blocId' => $bloc->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $blocCount = $this->getBlocCount();

        if ($currentOrder >= $blocCount) {
            return;
        }

        $targetOrder = $currentOrder + 1;

        // Find the bloc at target position and swap
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
     * Reorder all blocs to sequential order (1, 2, 3...).
     * Useful after deletions or imports.
     */
    public function reorderBlocs(): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

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
     * Get the number of attached blocs.
     */
    public function getBlocCount(): int
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

        return (int) $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->count();
    }

    /**
     * Shift blocs from a position by delta.
     *
     * @param int $fromPosition Starting position (inclusive)
     * @param int $delta Amount to shift (positive = down, negative = up)
     */
    private function shiftBlocsFrom(int $fromPosition, int $delta): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

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
     * Shift blocs between positions by delta.
     *
     * @param int $fromPosition Starting position (inclusive)
     * @param int $toPosition Ending position (inclusive)
     * @param int $delta Amount to shift (positive = down, negative = up)
     */
    private function shiftBlocsBetween(int $fromPosition, int $toPosition, int $delta): void
    {
        $pivotClass = $this->getBlocPivotClass();
        $fkColumn = $this->getBlocPivotFkColumn();

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
    private function getBlocPivotSetterMethod(): string
    {
        return 'set' . ucfirst($this->getBlocPivotFkColumn());
    }
}
