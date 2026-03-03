<?php

declare(strict_types=1);

/**
 * ElasticSchemaKind.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Enums;

enum ElasticSchemaKind: string
{
    case Common = 'common';     // utilisables partout (blocs, contents, tags) sauf xeo
    case Page = 'page';         // utilisable uniquement pour les contenus et tags
    case Bloc = 'bloc';         // utilisable uniquement pour les blocs
    case Xeo = 'xeo';           // que pour la partie Xeo
}
