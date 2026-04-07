<?php

declare(strict_types=1);

/**
 * M000000000006CreateTypes.php
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
 * Migration to create the types table
 *
 * PHP route + allowed elasticTypes.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000006CreateTypes implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%types}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string()->notNull()->unique(),
            'handler' => ColumnBuilder::string(),
            'contentAllowed' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'tagAllowed' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%types}}');
    }
}
