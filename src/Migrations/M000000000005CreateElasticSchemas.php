<?php

declare(strict_types=1);

/**
 * M000000000005CreateElasticSchemas.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Yiisoft\Db\Constant\IndexType;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the elasticSchemas table
 *
 * JSON Schema pour blocs. Compatible avec yii3-elastic.
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000005CreateElasticSchemas implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%elasticSchemas}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string(255)->notNull(),
            'schema' => ColumnBuilder::text(),
            'mdMapping' => ColumnBuilder::text(),
            'view' => ColumnBuilder::string(255),
            'kind' => ColumnBuilder::string(255)->notNull()->defaultValue('common'),
            'builtin' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'hidden' => ColumnBuilder::boolean()->notNull()->defaultValue(false),
            'order' => ColumnBuilder::integer()->notNull()->defaultValue(0),
            'active' => ColumnBuilder::boolean()->notNull()->defaultValue(true),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
        ]);
        $b->createIndex('{{%elasticSchemas}}', 'idx__elasticSchemas__name_kind', ['name', 'kind'], IndexType::UNIQUE);

        // Builtin Organization schema
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Organization',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/organization.xeo.json'),
            'view' => 'organization',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => true,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);

        // Builtin WebSite schema
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'WebSite',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/website.xeo.json'),
            'view' => 'website',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => true,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);

        // Builtin RawData schema (shared by Robots and Sitemap)
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'RawData',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/rawdata.json'),
            'view' => 'rawdata',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => true,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Hero',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/hero.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/hero.md'),
            'view' => 'hero',
            'kind' => ElasticSchemaKind::Page->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 1,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Section',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/section.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/section.md'),
            'view' => 'section',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 1,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Content',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/content.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/content.md'),
            'view' => 'content',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 2,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Image',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/image.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/image.md'),
            'view' => 'image',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 3,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Callout',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/callout.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/callout.md'),
            'view' => 'callout',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 4,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'CTA',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/cta.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/cta.md'),
            'view' => 'cta',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 5,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Media',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/media.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/media.md'),
            'view' => 'media',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 6,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'FAQ',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/faq.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/faq.md'),
            'view' => 'faq',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 7,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Code',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/code.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/code.md'),
            'view' => 'code',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 8,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Contact',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/contact.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/contact.md'),
            'view' => 'contact',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 9,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Feature',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/feature.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/feature.md'),
            'view' => 'feature',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 10,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Stat',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/stat.json'),
            'mdMapping' => file_get_contents(dirname(__DIR__) . '/Schemas/stat.md'),
            'view' => 'stat',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 11,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Error',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/error.json'),
            'view' => 'error',
            'kind' => ElasticSchemaKind::Bloc->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 12,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'WebPage',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/webpage.xeo.json'),
            'view' => 'webpage',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 1,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'FAQ',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/faq.xeo.json'),
            'view' => 'faq',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 2,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Image',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/imageobject.xeo.json'),
            'view' => 'image',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 3,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
        $b->insert('{{%elasticSchemas}}', [
            'name' => 'Video',
            'schema' => file_get_contents(dirname(__DIR__) . '/Schemas/videoobject.xeo.json'),
            'view' => 'video',
            'kind' => ElasticSchemaKind::Xeo->value,
            'builtin' => true,
            'hidden' => false,
            'order' => 4,
            'active' => true,
            'dateCreate' => new Expression('NOW()'),
            'dateUpdate' => new Expression('NOW()'),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%elasticSchemas}}');
    }
}
