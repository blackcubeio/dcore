<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the addons table
 *
 * Registry of the active add-ons: a row is an active add-on, its id is the key of the
 * declaration in the params, its version the installed Composer version at the last
 * run of its migrations.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M000000000025CreateAddons implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%addons}}', [
            'id' => ColumnBuilder::string(32)->notNull(),
            'version' => ColumnBuilder::string(32),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[id]])',
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%addons}}');
    }
}
