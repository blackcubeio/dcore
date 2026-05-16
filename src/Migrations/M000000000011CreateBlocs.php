<?php

declare(strict_types=1);

/**
 * M000000000011CreateBlocs.php
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
 * Migration to create the blocs table
 *
 * Instances elastic. Compatible avec yii3-elastic.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000011CreateBlocs implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%blocs}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'elasticSchemaId' => ColumnBuilder::bigint()->notNull(),

            // Elastic (yii3-elastic)
            '_extras' => ColumnBuilder::json(),

            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->addForeignKey('{{%blocs}}', 'blocs__elasticSchemaId_fk', ['elasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%blocs}}');
    }
}
