<?php

declare(strict_types=1);

/**
 * M000000000007CreateTypesElasticSchemas.php
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
 * Migration to create the types_elasticSchemas pivot table
 *
 * Pivot table for allowed Type ↔ ElasticSchema associations.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000007CreateTypesElasticSchemas implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%types_elasticSchemas}}', [
            'typeId' => ColumnBuilder::bigint()->notNull(),
            'elasticSchemaId' => ColumnBuilder::bigint()->notNull(),
            'allowed' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[typeId]], [[elasticSchemaId]])',
        ]);

        $b->addForeignKey('{{%types_elasticSchemas}}', 'types_elasticSchemas__typeId_fk', ['typeId'], '{{%types}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%types_elasticSchemas}}', 'types_elasticSchemas__elasticSchemaId_fk', ['elasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%types_elasticSchemas}}');
    }
}
