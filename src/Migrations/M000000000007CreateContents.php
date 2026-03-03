<?php

declare(strict_types=1);

/**
 * M000000000007CreateContents.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Constant\IndexType;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the contents table
 *
 * Contents (formerly nodes + composites). Unlimited Hazeltree.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000007CreateContents implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%contents}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string(),
            'slugId' => ColumnBuilder::bigint()->unique(),
            'languageId' => ColumnBuilder::string(6),
            'translationGroupId' => ColumnBuilder::bigint(),
            'typeId' => ColumnBuilder::bigint(),
            'elasticSchemaId' => ColumnBuilder::integer(),

            // Hazeltree (yii-hazeltree)
            'path' => ColumnBuilder::string()->notNull()->unique(),
            'left' => ColumnBuilder::decimal(25, 22)->notNull(),
            'right' => ColumnBuilder::decimal(25, 22)->notNull(),
            'level' => ColumnBuilder::integer()->notNull(),

            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateStart' => ColumnBuilder::datetime(),
            'dateEnd' => ColumnBuilder::datetime(),

            // Elastic (yii-elastic)
            '_extras' => ColumnBuilder::json(),

            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->createIndex('{{%contents}}', 'contents__translationGroupId_languageId_idx', ['translationGroupId', 'languageId'], IndexType::UNIQUE);
        $b->addForeignKey('{{%contents}}', 'contents__slugId_fk', ['slugId'], '{{%slugs}}', ['id'], 'SET NULL');
        $b->addForeignKey('{{%contents}}', 'contents__languageId_fk', ['languageId'], '{{%languages}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%contents}}', 'contents__translationGroupId_fk', ['translationGroupId'], '{{%translationGroups}}', ['id'], 'SET NULL');
        $b->addForeignKey('{{%contents}}', 'contents__typeId_fk', ['typeId'], '{{%types}}', ['id'], 'SET NULL');
        $b->addForeignKey('{{%contents}}', 'contents__elasticSchemaId_fk', ['elasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'SET NULL');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%contents}}');
    }
}
