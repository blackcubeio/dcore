<?php

declare(strict_types=1);

/**
 * M000000000001CreateLanguages.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the languages table
 *
 * Available languages. 'ml' = multilingual opt-in, 'main' = main language vs regional variant.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M000000000001CreateLanguages implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%languages}}', [
            'id' => ColumnBuilder::string(6)->notNull(),
            'name' => ColumnBuilder::string()->notNull(),
            'main' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[id]])',
        ]);
        $b->insertBatch('{{%languages}}', [
            [
                'en',
                'English',
                true,
                false,
                new Expression('NOW()'),
                new Expression('NOW()'),
             ],
            [
                'fr',
                'Français',
                true,
                true,
                new Expression('NOW()'),
                new Expression('NOW()'),
            ],
            [
                'es',
                'Español',
                true,
                false,
                new Expression('NOW()'),
                new Expression('NOW()'),
            ],
            [
                'it',
                'Italiano',
                true,
                false,
                new Expression('NOW()'),
                new Expression('NOW()'),
            ],
            [
                'de',
                'Deutsh',
                true,
                false,
                new Expression('NOW()'),
                new Expression('NOW()'),
            ],
        ], [
            'id',
            'name',
            'main',
            'active',
            'dateCreate',
            'dateUpdate',
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%languages}}');
    }
}
