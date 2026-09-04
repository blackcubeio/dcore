<?php

declare(strict_types=1);

/**
 * TestMapperService.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\ExportImport;

use Blackcube\Dcore\Services\MapperService;

/**
 * Test subclass exposing the protected exportModel() so an arbitrary probe can be
 * exported directly (the public export() only accepts Content/Tag). Relies on
 * exportModel() being protected — the very reason it was opened up.
 */
class TestMapperService extends MapperService
{
    /**
     * @param object $model The model to export
     * @return array<string, mixed> The exported data
     */
    public function exportProbe(object $model): array
    {
        return $this->exportModel($model);
    }
}
