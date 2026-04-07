<?php

declare(strict_types=1);

/**
 * M000000000010CreateMenus.php
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
 * Migration to create the menus table
 *
 * Navigation. Hazeltree limited to 4 levels.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000010CreateMenus implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%menus}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string()->notNull(),
            'hostId' => ColumnBuilder::bigint(),
            'languageId' => ColumnBuilder::string(6)->notNull(),

            // Hazeltree (yii-hazeltree) - limited to 4 levels
            'path' => ColumnBuilder::string()->notNull()->unique(),
            'left' => ColumnBuilder::decimal(25, 22)->notNull(),
            'right' => ColumnBuilder::decimal(25, 22)->notNull(),
            'level' => ColumnBuilder::integer()->notNull(),

            'route' => ColumnBuilder::string(),
            'queryString' => ColumnBuilder::string(),

            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->createIndex('{{%menus}}', 'menus__languageId_hostId_name_idx', ['languageId', 'hostId', 'name'], \Yiisoft\Db\Constant\IndexType::UNIQUE);
        $b->addForeignKey('{{%menus}}', 'menus__hostId_fk', ['hostId'], '{{%hosts}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%menus}}', 'menus__languageId_fk', ['languageId'], '{{%languages}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%menus}}');
    }
}
