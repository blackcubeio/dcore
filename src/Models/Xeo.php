<?php

declare(strict_types=1);

/**
 * Xeo.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models;

use Blackcube\ActiveRecord\PopulatePropertyTrait;
use Blackcube\Dcore\Attributes\ResetQueryCacheOnWrite;
use Blackcube\Dcore\Traits\OrderedManagementTrait;
use Blackcube\Dcore\Traits\ScopedQueryTrait;
use Yiisoft\ActiveRecord\Event\Handler\DefaultDateTimeOnInsert;
use Yiisoft\ActiveRecord\Event\Handler\SetDateTimeOnUpdate;

/**
 * Xeo model - XEO by slug with elastic schema support.
 */
#[DefaultDateTimeOnInsert(null, 'dateCreate')]
#[DefaultDateTimeOnInsert(null, 'dateUpdate')]
#[SetDateTimeOnUpdate(null, 'dateUpdate')]
#[ResetQueryCacheOnWrite]
class Xeo extends BaseXeo
{
    use ScopedQueryTrait;
    use PopulatePropertyTrait;
    use OrderedManagementTrait;

    protected function getOrderedConfig(string $entityType): array
    {
        return match ($entityType) {
            'bloc' => ['pivotClass' => XeoBloc::class, 'fkColumn' => 'xeoId', 'entityIdColumn' => 'blocId', 'owned' => true],
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
}
