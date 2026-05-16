<?php

declare(strict_types=1);

/**
 * M000000000020CreateGlobalXeos.php
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
 * Migration to create the globalXeos table.
 *
 * XEO Level 1 — site-wide configuration per host.
 * PK composite (hostId, kind) — one globalXeo per host+kind.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000020CreateGlobalXeos implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%globalXeos}}', [
            'hostId' => ColumnBuilder::bigint()->notNull(),
            'name' => ColumnBuilder::string()->notNull(),
            'kind' => ColumnBuilder::string()->notNull(),
            'elasticSchemaId' => ColumnBuilder::bigint()->notNull(),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            '_extras' => ColumnBuilder::json(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[hostId]], [[kind]])',
        ]);

        $b->addForeignKey('{{%globalXeos}}', 'globalXeos__hostId_fk', ['hostId'], '{{%hosts}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%globalXeos}}', 'globalXeos__elasticSchemaId_fk', ['elasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%globalXeos}}');
    }
}
