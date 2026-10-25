<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Enums;

/**
 * Where an elastic schema can be used: common, page, bloc or xeo.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
enum ElasticSchemaKind: string
{
    case Common = 'common';     // usable everywhere (blocs, contents, tags) except xeo
    case Page = 'page';         // usable only for contents and tags
    case Bloc = 'bloc';         // usable only for blocs
    case Xeo = 'xeo';           // only for the Xeo part
}
