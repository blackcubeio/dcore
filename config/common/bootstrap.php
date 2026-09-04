<?php

declare(strict_types=1);

/**
 * bootstrap.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

use Blackcube\Dcore\Helpers\QueryExpressions;
use Psr\Container\ContainerInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

return [
    static function (ContainerInterface $container): void {
        if ($container->has(ConnectionInterface::class) === true) {
            QueryExpressions::register($container->get(ConnectionInterface::class));
        }
    },
];
