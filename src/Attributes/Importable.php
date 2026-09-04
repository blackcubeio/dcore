<?php

declare(strict_types=1);

/**
 * Importable.php
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
 * Attribute to mark an importable member.
 * Posed on a public property, a public setter, or — for inherited members that
 * can't be annotated in the parent — on the class itself (then `property` or
 * `method` designates the target). Mirror of Exportable. Used by MapperService.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Importable
{
    /**
     * @param string|null $name Import key (defaults: property name, or setter name without set prefix)
     * @param class-string<FormatterInterface>|null $format Formatter class — save() applied on import; raw value if null
     * @param string|null $property Target an inherited public property — only when the attribute is on the class
     * @param string|null $method Target an inherited public setter — only when the attribute is on the class
     * @param string|null $description Role of the member, surfaced by MapperService::describe() (info.model) when the field is write-only
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $format = null,
        public readonly ?string $property = null,
        public readonly ?string $method = null,
        public readonly ?string $description = null,
    ) {}
}
