<?php

declare(strict_types=1);

/**
 * M000000000016CreateSchemasSchemas.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
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
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class M000000000016CreateSchemasSchemas implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%schemas_schemas}}', [
            'regularElasticSchemaId' => ColumnBuilder::integer()->notNull(),
            'xeoElasticSchemaId' => ColumnBuilder::integer()->notNull(),
            'mapping' => ColumnBuilder::text()->notNull(),
            'dateCreate' => ColumnBuilder::datetime()->notNull(),
            'dateUpdate' => ColumnBuilder::datetime(),
            'PRIMARY KEY([[regularElasticSchemaId]], [[xeoElasticSchemaId]])',
        ]);

        $b->addForeignKey('{{%schemas_schemas}}', 'schemas_schemas__regularElasticSchemaId_fk', ['regularElasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');
        $b->addForeignKey('{{%schemas_schemas}}', 'schemas_schemas__xeoElasticSchemaId_fk', ['xeoElasticSchemaId'], '{{%elasticSchemas}}', ['id'], 'CASCADE');

        // Register migration DB connection for ActiveRecord queries
        ConnectionProvider::set($b->getDb());

        $links = [
            'Hero' => 'Hero',
            'Image' => 'Image',
            'Media' => 'Video',
            'FAQ' => 'FAQ',
        ];
        $xeos = ElasticSchema::query()
            ->andWhere([
                'kind' => ElasticSchemaKind::Xeo->value
            ])
        ->andWhere(['in', 'name', array_values($links)])
        ->all();
        $regulars =  ElasticSchema::query()
            ->andWhere([
                'not in', 'kind', [ElasticSchemaKind::Xeo->value]
            ])
            ->andWhere(['in', 'name', array_keys($links)])
            ->all();
        foreach ($regulars as $regular) {
            $linkedXeo = $links[$regular->getName()] ?? null;
            if ($linkedXeo !== null) {
                $xeo = array_values(array_filter($xeos, fn(ElasticSchema $s) => $s->getName() === $linkedXeo));
                if ($xeo[0] instanceof ElasticSchema) {
                    $mappingName = dirname(__DIR__) . '/Schemas/'.strtolower($regular->getName()) . '.mapping.json';
                    if (file_exists($mappingName)) {
                        $b->insert('{{%schemas_schemas}}', [
                            'regularElasticSchemaId' => $regular->getId(),
                            'xeoElasticSchemaId' => $xeo[0]->getId(),
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
