<?php

declare(strict_types=1);

/**
 * FormatterInterface.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Interfaces;

/**
 * A formatter transforms a value in both directions of the export/import cycle.
 * - load(): used on EXPORT — reads the stored value into the dump (e.g. file link → base64).
 * - save(): used on IMPORT — persists the dumped value (e.g. base64 → write file → link).
 *
 * $value is the value to transform; $parameters carries context (e.g.
 * ['parents' => ['contents', 12]]). A custom behaviour = a dedicated Formatter class.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
interface FormatterInterface
{
    public static function load(mixed $value, array $parameters = []): mixed;

    public static function save(mixed $value, array $parameters = []): mixed;
}
