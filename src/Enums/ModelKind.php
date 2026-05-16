<?php

declare(strict_types=1);

/**
 * ModelKind.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Enums;

enum ModelKind: string
{
    case Model = 'model';
    case Entity = 'entity';
}
