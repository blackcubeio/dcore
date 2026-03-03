<?php

declare(strict_types=1);

/**
 * M000000000015CreateXeosBlocs.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the xeos_blocs pivot table
 *
 * Ordered pivot table for Xeo ↔ Bloc associations.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000015CreateXeosBlocs implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%xeos_blocs}}', [
            'xeoId' => ColumnBuilder::bigint()->notNull(),
            'blocId' => ColumnBuilder::bigint()->notNull(),
            'order' => ColumnBuilder::integer()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[xeoId]], [[blocId]])',
        ]);

        $b->addForeignKey('{{%xeos_blocs}}', 'xeos_blocs__xeoId_fk', ['xeoId'], '{{%xeos}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%xeos_blocs}}', 'xeos_blocs__blocId_fk', ['blocId'], '{{%blocs}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%xeos_blocs}}');
    }
}
