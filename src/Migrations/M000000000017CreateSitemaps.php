<?php

declare(strict_types=1);

/**
 * M000000000017CreateSitemaps.php
 *
 * PHP Version 8.3+
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
 * Migration to create the sitemaps table
 *
 * Sitemap par slug.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000017CreateSitemaps implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%sitemaps}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'slugId' => ColumnBuilder::bigint()->notNull()->unique(),
            'frequency' => ColumnBuilder::string(64)->defaultValue('daily'),
            'priority' => ColumnBuilder::float()->defaultValue(0.5),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->addForeignKey('{{%sitemaps}}', 'sitemaps__slugId_fk', ['slugId'], '{{%slugs}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%sitemaps}}');
    }
}
