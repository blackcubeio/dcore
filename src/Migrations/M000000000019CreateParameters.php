<?php

declare(strict_types=1);

/**
 * M000000000019CreateParameters.php
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
 * Migration to create the parameters table
 *
 * Generic key/value store.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000019CreateParameters implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%parameters}}', [
            'domain' => ColumnBuilder::string(64)->notNull(),
            'name' => ColumnBuilder::string(64)->notNull(),
            'value' => ColumnBuilder::text(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[domain]], [[name]])',
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%parameters}}');
    }
}
