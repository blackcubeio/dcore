<?php

declare(strict_types=1);

/**
 * TestFileProvider.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\ExportImport;

use Blackcube\FileProvider\Interfaces\FileProviderInterface;

/**
 * Real (non-mock) FileProvider for the export/import tests: it handles no path.
 * The probe only carries scalar properties, so FileService is never routed to —
 * canHandle() simply answers false to satisfy FileService's dependency.
 */
class TestFileProvider implements FileProviderInterface
{
    public function canHandle(string $path): bool
    {
        return false;
    }

    public function isTempPath(string $path): bool
    {
        return false;
    }

    public function getTempAlias(): string
    {
        return '@bltmp';
    }
}
