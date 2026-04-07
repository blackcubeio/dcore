<?php

declare(strict_types=1);

/**
 * M000000000015CreateXeos.php
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
 * Migration to create the xeos table
 *
 * XEO par slug.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000015CreateXeos implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%xeos}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'slugId' => ColumnBuilder::bigint()->notNull()->unique(),
            'canonicalSlugId' => ColumnBuilder::bigint(),
            'title' => ColumnBuilder::string(),
            'image' => ColumnBuilder::string(),
            'description' => ColumnBuilder::text(),
            'noindex' => ColumnBuilder::boolean()->defaultValue(false),
            'nofollow' => ColumnBuilder::boolean()->defaultValue(false),
            'og' => ColumnBuilder::boolean()->defaultValue(false),
            'ogType' => ColumnBuilder::string(),
            'twitter' => ColumnBuilder::boolean()->defaultValue(false),
            'twitterCard' => ColumnBuilder::string(),
            'jsonldType' => ColumnBuilder::string(50)->defaultValue('WebPage'),
            'speakable' => ColumnBuilder::boolean()->defaultValue(false),
            'keywords' => ColumnBuilder::text(),
            'accessibleForFree' => ColumnBuilder::boolean()->defaultValue(true),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);

        $b->addForeignKey('{{%xeos}}', 'xeos__slugId_fk', ['slugId'], '{{%slugs}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%xeos}}', 'xeos__canonicalSlugId_fk', ['canonicalSlugId'], '{{%slugs}}', ['id'], 'SET NULL');
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%xeos}}');
    }
}
