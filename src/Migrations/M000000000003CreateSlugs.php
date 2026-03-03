<?php

declare(strict_types=1);

/**
 * M000000000003CreateSlugs.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Yiisoft\Db\Constant\IndexType;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the slugs table
 *
 * URLs (host + path) avec support redirect direct.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000003CreateSlugs implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%slugs}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'hostId' => ColumnBuilder::bigint()->notNull()->defaultValue(1),
            'path' => ColumnBuilder::string()->notNull()->defaultValue(''),
            'targetUrl' => ColumnBuilder::string(),
            'httpCode' => ColumnBuilder::integer(),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->createIndex('{{%slugs}}', 'slugs__hostId_path_idx', ['hostId', 'path'], IndexType::UNIQUE);
        $b->addForeignKey('{{%slugs}}', 'slugs__hostId_fk', ['hostId'], '{{%hosts}}', ['id'], 'CASCADE');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%slugs}}');
    }
}
