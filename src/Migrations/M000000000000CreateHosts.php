<?php

declare(strict_types=1);

/**
 * M000000000000CreateHosts.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the hosts table
 *
 * Domains for dump portability. id=1 reserved for wildcard '*'.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000000CreateHosts implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%hosts}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string()->notNull(),
            'siteName' => ColumnBuilder::string(),
            'siteAlternateName' => ColumnBuilder::string(),
            'siteDescription' => ColumnBuilder::text(),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        // Insert wildcard host (id=1)
        $b->insert('{{%hosts}}', [
            'id' => 1,
            'name' => '*',
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%hosts}}');
    }
}
