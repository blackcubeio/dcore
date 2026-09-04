<?php

declare(strict_types=1);

/**
 * CreateExportImportProbes.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\ExportImport;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Test-only migration for the {{%exportImportProbes}} table.
 * Applied directly by the Cest setup (not registered in the dcore Migrations
 * namespace), so it never touches the real schema.
 */
final class CreateExportImportProbes implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%exportImportProbes}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'label' => ColumnBuilder::string()->notNull(),
            'score' => ColumnBuilder::integer()->notNull()->defaultValue(0),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%exportImportProbes}}');
    }
}
