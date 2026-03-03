<?php

declare(strict_types=1);

/**
 * M000000000008CreateTags.php
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
 * Migration to create the tags table
 *
 * Taxonomy (formerly categories + tags). Hazeltree limited to 2-3 levels.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000008CreateTags implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%tags}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string()->notNull(),
            'slugId' => ColumnBuilder::bigint()->unique(),
            'typeId' => ColumnBuilder::bigint(),
            'elasticSchemaId' => ColumnBuilder::integer(),

            // Hazeltree (yii-hazeltree) - limited to 2-3 levels
            'path' => ColumnBuilder::string()->notNull()->unique(),
            'left' => ColumnBuilder::decimal(25, 22)->notNull(),
            'right' => ColumnBuilder::decimal(25, 22)->notNull(),
            'level' => ColumnBuilder::integer()->notNull(),

            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),

            // Elastic (yii-elastic)
            '_extras' => ColumnBuilder::json(),

            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->addForeignKey('{{%tags}}', 'tags__slugId_fk', ['slugId'], '{{%slugs}}', ['id'], 'SET NULL');
        $b->addForeignKey('{{%tags}}', 'tags__typeId_fk', ['typeId'], '{{%types}}', ['id'], 'SET NULL');
        $b->addForeignKey('{{%tags}}', 'tags__elasticSchemaId_fk', ['elasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'SET NULL');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%tags}}');
    }
}
