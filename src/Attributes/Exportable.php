<?php

declare(strict_types=1);

/**
 * Exportable.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Attributes;

use Attribute;
use Blackcube\Dcore\Interfaces\FormatterInterface;

/**
 * Attribute to mark an exportable member.
 * Posed on a public property, a public method, or — for inherited members that
 * can't be annotated in the parent — on the class itself (then `property` or
 * `method` designates the target). Used by MapperService.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Exportable
{
    /**
     * @param string|null $name Export key (defaults: property name, or method name without get/set/is prefix)
     * @param class-string<FormatterInterface>|null $format Formatter class — load() applied on export; raw value if null
     * @param array<string>|null $fields For a relation: whitelist of fields/relations to export (default: all)
     * @param string|null $property Target an inherited public property — only when the attribute is on the class
     * @param string|null $method Target an inherited public method — only when the attribute is on the class
     * @param string|null $description Role of the member, surfaced by MapperService::describe() (info.model)
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $format = null,
        public readonly ?array $fields = null,
        public readonly ?string $property = null,
        public readonly ?string $method = null,
        public readonly ?string $description = null,
    ) {}
}
