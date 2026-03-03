<?php

declare(strict_types=1);

/**
 * M000000000022CreateTagsAuthors.php
 *
 * PHP Version 8.3+
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
 * Migration to create the tags_authors pivot table
 *
 * Ordered pivot Tag ↔ Author.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000022CreateTagsAuthors implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%tags_authors}}', [
            'tagId' => ColumnBuilder::bigint()->notNull(),
            'authorId' => ColumnBuilder::bigint()->notNull(),
            'order' => ColumnBuilder::integer()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[tagId]], [[authorId]])',
        ]);

        $b->addForeignKey('{{%tags_authors}}', 'tags_authors__tagId_fk', ['tagId'], '{{%tags}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%tags_authors}}', 'tags_authors__authorId_fk', ['authorId'], '{{%authors}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%tags_authors}}');
    }
}
