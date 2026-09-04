<?php

declare(strict_types=1);

/**
 * DateFormatter.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Models\Formatters;

use Blackcube\Dcore\Interfaces\FormatterInterface;
use DateTimeImmutable;

/**
 * Formats a DateTimeImmutable to/from the 'Y-m-d H:i:s' string used in dumps.
 * Pure (no side effect): load = export (date → string), save = import (string → date).
 */
class DateFormatter implements FormatterInterface
{
    public static function load(mixed $value, array $parameters = []): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            $result = $value->format('Y-m-d H:i:s');
        } else {
            $result = $value;
        }

        return $result;
    }

    public static function save(mixed $value, array $parameters = []): mixed
    {
        if (is_string($value) === true && $value !== '') {
            $result = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value) ?: new DateTimeImmutable($value);
        } else {
            $result = $value;
        }

        return $result;
    }
}
