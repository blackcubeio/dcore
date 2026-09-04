<?php

declare(strict_types=1);

/**
 * ElasticSchemaKind.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Enums;

enum ElasticSchemaKind: string
{
    case Common = 'common';     // usable everywhere (blocs, contents, tags) except xeo
    case Page = 'page';         // usable only for contents and tags
    case Bloc = 'bloc';         // usable only for blocs
    case Xeo = 'xeo';           // only for the Xeo part
}
