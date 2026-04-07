<?php

declare(strict_types=1);

/**
 * OrderedManagementTrait.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Traits;

/**
 * OrderedManagementTrait - Generic ordered pivot management.
 * Replaces BlocManagementTrait + AuthorManagementTrait.
 * Used by Content and Tag models.
 *
 * Config key 'owned' controls entity deletion on detach:
 * - true (blocs): entity is deleted after pivot removal
 * - false (authors): only pivot is removed, entity stays
 */
trait OrderedManagementTrait
{
    /**
     * Get config for a specific ordered pivot relationship.
     *
     * @param string $entityType Identifier (e.g. 'bloc', 'author')
     * @return array{pivotClass: string, fkColumn: string, entityIdColumn: string, owned: bool}
     */
    abstract protected function getOrderedConfig(string $entityType): array;

    /**
     * Attach an entity at a specific position.
     * If position <= 0, appends at the end.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to attach
     * @param int $position Target position (1-based), 0 = append at end
     */
    protected function attachOrdered(string $type, object $entity, int $position = 0): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        // Check if already attached
        $existing = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->one();

        if ($existing !== null) {
            return;
        }

        $count = $this->getOrderedCount($type);

        // Normalize position
        if ($position <= 0 || $position > $count + 1) {
            $position = $count + 1;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            // Shift existing entries from target position
            if ($position <= $count) {
                $this->shiftOrderedFrom($type, $position, 1);
            }

            // Create pivot
            $pivot = new $pivotClass();
            $fkSetter = 'set' . ucfirst($fkColumn);
            $entityIdSetter = 'set' . ucfirst($entityIdColumn);
            $pivot->$fkSetter($this->getId());
            $pivot->$entityIdSetter($entity->getId());
            $pivot->setOrder($position);
            $pivot->save();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Detach an entity.
     * If owned, the entity itself is deleted after pivot removal.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to detach
     */
    protected function detachOrdered(string $type, object $entity): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $order = $pivot->getOrder();

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            $pivot->delete();

            if ($config['owned']) {
                $entity->delete();
            }

            // Fill the gap
            $this->shiftOrderedFrom($type, $order + 1, -1);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Check if an entity is attached.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to check
     */
    protected function hasOrdered(string $type, object $entity): bool
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        return $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->exists();
    }

    /**
     * Move an entity to a specific position.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to move
     * @param int $position Target position (1-based)
     */
    protected function moveOrdered(string $type, object $entity, int $position): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $count = $this->getOrderedCount($type);

        // Normalize position
        if ($position < 1) {
            $position = 1;
        }
        if ($position > $count) {
            $position = $count;
        }

        if ($currentOrder === $position) {
            return;
        }

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            if ($currentOrder < $position) {
                // Moving down: shift items between (current+1, target) up by -1
                $this->shiftOrderedBetween($type, $currentOrder + 1, $position, -1);
            } else {
                // Moving up: shift items between (target, current-1) down by +1
                $this->shiftOrderedBetween($type, $position, $currentOrder - 1, 1);
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
     * Move entity up one position.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to move up
     */
    protected function moveOrderedUp(string $type, object $entity): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->one();

        if ($pivot === null || $pivot->getOrder() <= 1) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $targetOrder = $currentOrder - 1;

        // Find the entry at target position and swap
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
     * Move entity down one position.
     *
     * @param string $type Entity type identifier
     * @param object $entity The entity to move down
     */
    protected function moveOrderedDown(string $type, object $entity): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];
        $entityIdColumn = $config['entityIdColumn'];

        $pivot = $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId(), $entityIdColumn => $entity->getId()])
            ->one();

        if ($pivot === null) {
            return;
        }

        $currentOrder = $pivot->getOrder();
        $count = $this->getOrderedCount($type);

        if ($currentOrder >= $count) {
            return;
        }

        $targetOrder = $currentOrder + 1;

        // Find the entry at target position and swap
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
     * Reorder all entries to sequential order (1, 2, 3...).
     * Useful after deletions or imports.
     *
     * @param string $type Entity type identifier
     */
    protected function reorderOrdered(string $type): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];

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
     * Get the number of attached entries.
     *
     * @param string $type Entity type identifier
     */
    protected function getOrderedCount(string $type): int
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];

        return (int) $pivotClass::query()
            ->andWhere([$fkColumn => $this->getId()])
            ->count();
    }

    /**
     * Shift entries from a position by delta.
     *
     * @param string $type Entity type identifier
     * @param int $fromPosition Starting position (inclusive)
     * @param int $delta Amount to shift (positive = down, negative = up)
     */
    private function shiftOrderedFrom(string $type, int $fromPosition, int $delta): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];

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
     * Shift entries between positions by delta.
     *
     * @param string $type Entity type identifier
     * @param int $fromPosition Starting position (inclusive)
     * @param int $toPosition Ending position (inclusive)
     * @param int $delta Amount to shift (positive = down, negative = up)
     */
    private function shiftOrderedBetween(string $type, int $fromPosition, int $toPosition, int $delta): void
    {
        $config = $this->getOrderedConfig($type);
        $pivotClass = $config['pivotClass'];
        $fkColumn = $config['fkColumn'];

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
}
