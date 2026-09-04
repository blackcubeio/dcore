<?php

declare(strict_types=1);

/**
 * ResetQueryCacheOnWrite.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Attributes;

use Attribute;
use Yiisoft\ActiveRecord\Event\AfterDelete;
use Yiisoft\ActiveRecord\Event\AfterInsert;
use Yiisoft\ActiveRecord\Event\AfterUpdate;
use Yiisoft\ActiveRecord\Event\AfterUpsert;
use Yiisoft\ActiveRecord\Event\Handler\AttributeHandlerProvider;
use Yiisoft\Cache\Dependency\Dependency;

/**
 * Resets the reusable cache dependencies after every write of the record.
 *
 * The per-table ReusableDependency (MAX(dateUpdate), see QueryCache) is
 * evaluated once per request and reused; without this reset, a cached Entity
 * query re-read AFTER a write in the SAME request keeps serving the pre-write
 * result (stale read reproduced by QueryCacheCest). Posed explicitly on every
 * dcore final Model, next to the DateTime event handlers.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ResetQueryCacheOnWrite extends AttributeHandlerProvider
{
    public function getEventHandlers(): array
    {
        return [
            AfterInsert::class => $this->afterWrite(...),
            AfterUpdate::class => $this->afterWrite(...),
            AfterUpsert::class => $this->afterWrite(...),
            AfterDelete::class => $this->afterWrite(...),
        ];
    }

    private function afterWrite(AfterInsert|AfterUpdate|AfterUpsert|AfterDelete $event): void
    {
        Dependency::resetReusableData();
    }
}
