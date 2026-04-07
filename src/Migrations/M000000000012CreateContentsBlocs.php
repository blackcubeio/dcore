<?php

declare(strict_types=1);

/**
 * M000000000012CreateContentsBlocs.php
 *
 * PHP Version 8.1
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
 * Migration to create the contents_blocs pivot table
 *
 * Ordered pivot table for Content ↔ Bloc associations.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000012CreateContentsBlocs implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%contents_blocs}}', [
            'contentId' => ColumnBuilder::bigint()->notNull(),
            'blocId' => ColumnBuilder::bigint()->notNull(),
            'order' => ColumnBuilder::integer()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[contentId]], [[blocId]])',
        ]);

        $b->addForeignKey('{{%contents_blocs}}', 'contents_blocs__contentId_fk', ['contentId'], '{{%contents}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%contents_blocs}}', 'contents_blocs__blocId_fk', ['blocId'], '{{%blocs}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%contents_blocs}}');
    }
}
