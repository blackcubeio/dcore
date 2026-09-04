<?php

declare(strict_types=1);

/**
 * PublicFieldProbe.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\ExportImport;

use Blackcube\Dcore\Attributes\Exportable;
use Blackcube\Dcore\Attributes\Importable;
use Blackcube\Dcore\Models\Formatters\DateFormatter;
use DateTimeImmutable;

/**
 * Plain object (non-AR) carrying PUBLIC annotated properties — direct read/write —
 * plus a property whose value passes through a Formatter (date ↔ string).
 */
class PublicFieldProbe
{
    #[Exportable]
    #[Importable]
    public string $title = '';

    #[Exportable(format: DateFormatter::class)]
    #[Importable(format: DateFormatter::class)]
    public ?DateTimeImmutable $when = null;
}
