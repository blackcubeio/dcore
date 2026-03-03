<?php

declare(strict_types=1);

/**
 * M000000000012CreateContentsTags.php
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
 * Migration to create the contents_tags pivot table
 *
 * Pivot Content ↔ Tag.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000012CreateContentsTags implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%contents_tags}}', [
            'contentId' => ColumnBuilder::bigint()->notNull(),
            'tagId' => ColumnBuilder::bigint()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[contentId]], [[tagId]])',
        ]);

        $b->addForeignKey('{{%contents_tags}}', 'contents_tags__contentId_fk', ['contentId'], '{{%contents}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%contents_tags}}', 'contents_tags__tagId_fk', ['tagId'], '{{%tags}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%contents_tags}}');
    }
}
