<?php

declare(strict_types=1);

/**
 * Exportable.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Attributes;

use Attribute;

/**
 * Attribute to mark a property as exportable.
 * Used by ExportService to discover which properties to export.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Exportable
{
    /**
     * @param string|null $name Export key name (defaults to property/method name)
     * @param string|null $format Format for DateTimeImmutable (e.g., 'Y-m-d H:i:s')
     * @param string|null $getter Getter method name (only for properties, ignored on methods)
     * @param bool $base64 If true, treat value as file path and export as base64 data URI
     * @param array<string>|null $fields For relations: limit exported fields to this list (e.g., ['id', 'name'])
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $format = null,
        public readonly ?string $getter = null,
        public readonly bool $base64 = false,
        public readonly ?array $fields = null,
    ) {}
}
