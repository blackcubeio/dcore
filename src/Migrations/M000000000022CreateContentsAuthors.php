<?php

declare(strict_types=1);

/**
 * M000000000022CreateContentsAuthors.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the contents_authors pivot table
 *
 * Ordered pivot Content ↔ Author.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000022CreateContentsAuthors implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%contents_authors}}', [
            'contentId' => ColumnBuilder::bigint()->notNull(),
            'authorId' => ColumnBuilder::bigint()->notNull(),
            'order' => ColumnBuilder::integer()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[contentId]], [[authorId]])',
        ]);

        $b->addForeignKey('{{%contents_authors}}', 'contents_authors__contentId_fk', ['contentId'], '{{%contents}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%contents_authors}}', 'contents_authors__authorId_fk', ['authorId'], '{{%authors}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%contents_authors}}');
    }
}
