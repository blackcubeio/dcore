<?php

declare(strict_types=1);

/**
 * M000000000021CreateAuthors.php
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
 * Migration to create the authors table
 *
 * Authors for JSON-LD Article (E-E-A-T).
 * Linked to Contents and Tags via pivot tables.
 *
 * @copyright 2010-2026 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000021CreateAuthors implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%authors}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'firstname' => ColumnBuilder::string()->notNull(),
            'lastname' => ColumnBuilder::string()->notNull(),
            'email' => ColumnBuilder::string(),
            'jobTitle' => ColumnBuilder::string(),
            'worksFor' => ColumnBuilder::text(),
            'knowsAbout' => ColumnBuilder::text(),
            'sameAs' => ColumnBuilder::text(),
            'url' => ColumnBuilder::string(),
            'image' => ColumnBuilder::string(),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%authors}}');
    }
}
