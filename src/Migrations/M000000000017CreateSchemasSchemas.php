<?php

declare(strict_types=1);

/**
 * M000000000017CreateSchemasSchemas.php
 *
 * PHP Version 8.4
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Migrations;

use Blackcube\Dcore\Enums\ElasticSchemaKind;
use Blackcube\Dcore\Models\ElasticSchema;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the schemas_schemas pivot table
 *
 * Ordered pivot table for elasticSchema ↔ elasticSchema associations.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M000000000017CreateSchemasSchemas implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%schemas_schemas}}', [
            'regularElasticSchemaId' => ColumnBuilder::bigint()->notNull(),
            'xeoElasticSchemaId' => ColumnBuilder::bigint()->notNull(),
            'mapping' => ColumnBuilder::text()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[regularElasticSchemaId]], [[xeoElasticSchemaId]])',
        ]);

        $b->addForeignKey('{{%schemas_schemas}}', 'schemas_schemas__regularElasticSchemaId_fk', ['regularElasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%schemas_schemas}}', 'schemas_schemas__xeoElasticSchemaId_fk', ['xeoElasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');

        ConnectionProvider::set($b->getDb());

        $links = [
            'Hero' => 'WebPage',
            'Image' => 'Image',
            'Media' => 'Video',
            'FAQ' => 'FAQ',
        ];
        $xeosQuery = ElasticSchema::query()
            ->andWhere([
                'kind' => ElasticSchemaKind::Xeo->value
            ])
            ->andWhere(['in', 'name', array_values($links)]);

        $xeosByName = [];
        /** @var ElasticSchema $xeo */
        foreach ($xeosQuery->each() as $xeo) {
            $xeosByName[$xeo->getName()] = $xeo;
        }

        $regularsQuery = ElasticSchema::query()
            ->andWhere([
                'not in', 'kind', [ElasticSchemaKind::Xeo->value]
            ])
            ->andWhere(['in', 'name', array_keys($links)]);

        /** @var ElasticSchema $regular */
        foreach ($regularsQuery->each() as $regular) {
            $linkedXeo = $links[$regular->getName()] ?? null;
            if ($linkedXeo !== null) {
                $xeo = $xeosByName[$linkedXeo] ?? null;
                if ($xeo instanceof ElasticSchema) {
                    $mappingName = dirname(__DIR__).'/Schemas/'.strtolower($regular->getName()).'.mapping.json';
                    if (file_exists($mappingName) === true) {
                        $b->insert('{{%schemas_schemas}}', [
                            'regularElasticSchemaId' => $regular->getId(),
                            'xeoElasticSchemaId' => $xeo->getId(),
                            'mapping' => file_get_contents($mappingName),
                            'dateCreate' => new Expression('NOW()'),
                            'dateUpdate' => new Expression('NOW()'),

                            ]
                        );
                    }
                }
            }
        }
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%schemas_schemas}}');
    }
}
