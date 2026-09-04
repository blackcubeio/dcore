<?php

declare(strict_types=1);

/**
 * M000000000024CreateLlmMenus.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the llmMenus table
 *
 * LLMS data for xEO.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M000000000024CreateLlmMenus implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%llmMenus}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string()->notNull(),
            'description' => ColumnBuilder::text(),

            'contentId' => ColumnBuilder::bigint(),
            'tagId' => ColumnBuilder::bigint(),

            'path' => ColumnBuilder::string()->notNull()->unique(),
            'left' => ColumnBuilder::decimal(25, 22)->notNull(),
            'right' => ColumnBuilder::decimal(25, 22)->notNull(),
            'level' => ColumnBuilder::integer()->notNull(),

            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->addForeignKey('{{%llmMenus}}', 'llmMenus__contentId_fk', ['contentId'], '{{%contents}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%llmMenus}}', 'llmMenus__tagId_fk', ['tagId'], '{{%tags}}', ['id'], 'CASCADE');

        $b->createIndex('{{%llmMenus}}', 'llmMenus__contentId_tagId_name_idx', ['contentId', 'tagId'], \Yiisoft\Db\Constant\IndexType::UNIQUE);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%llmMenus}}');
    }
}
