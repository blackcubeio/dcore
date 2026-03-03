<?php

declare(strict_types=1);

/**
 * M000000000013CreateTagsBlocs.php
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
 * Migration to create the tags_blocs pivot table
 *
 * Ordered pivot table for Tag ↔ Bloc associations.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000013CreateTagsBlocs implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%tags_blocs}}', [
            'tagId' => ColumnBuilder::bigint()->notNull(),
            'blocId' => ColumnBuilder::bigint()->notNull(),
            'order' => ColumnBuilder::integer()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[tagId]], [[blocId]])',
        ]);

        $b->addForeignKey('{{%tags_blocs}}', 'tags_blocs__tagId_fk', ['tagId'], '{{%tags}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%tags_blocs}}', 'tags_blocs__blocId_fk', ['blocId'], '{{%blocs}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%tags_blocs}}');
    }
}
