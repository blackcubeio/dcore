<?php

declare(strict_types=1);

/**
 * params.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

return [
    'blackcube/dcore' => [
        'routes' => [
            'sitemap' => 'sitemap.xml',
            'robots' => 'robots.txt',
        ],
        'hosts' => [
            '*' => 1,                  // Fallback host ID when no match
        ],
    ],
    'yiisoft/aliases' => [
        'aliases' => [
            '@dcore' => dirname(__DIR__, 2) . '/src',
        ],
    ],
    'yiisoft/db-migration' => [
        'sourceNamespaces' => [
            'Blackcube\\Dcore\\Migrations',
        ],
    ],
];
