<?php

declare(strict_types=1);

/**
 * MigrationsCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Migration;

use Blackcube\Dcore\Tests\Support\MigrationTester;
use Blackcube\Dcore\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

final class MigrationsCest
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';

    private const TABLES = [
        'hosts',
        'languages',
        'contentTranslationGroups',
        'tagTranslationGroups',
        'slugs',
        'elasticSchemas',
        'types',
        'types_elasticSchemas',
        'contents',
        'tags',
        'menus',
        'blocs',
        'contents_blocs',
        'contents_tags',
        'tags_blocs',
        'xeos',
        'xeos_blocs',
        'schemas_schemas',
        'sitemaps',
        'parameters',
        'globalXeos',
        'authors',
        'contents_authors',
        'tags_authors',
        'llmMenus',
    ];

    private ConnectionInterface $db;
    private Migrator $migrator;
    private MigrationService $service;

    public function _before(MigrationTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();

        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        $container = new Container($containerConfig);
        $injector = new Injector($container);

        $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        $this->service = new MigrationService($this->db, $injector, $this->migrator);
        $this->service->setSourceNamespaces([self::NAMESPACE]);

        // Clean state by dropping all tables directly (avoids issues with missing tables)
        $this->db->createCommand("SET FOREIGN_KEY_CHECKS = 0")->execute();
        $allTables = array_merge(self::TABLES, ['yii_migration', 'migration']);
        foreach ($allTables as $table) {
            try {
                $this->db->createCommand("DROP TABLE IF EXISTS `$table`")->execute();
            } catch (\Throwable $e) {
                // Ignore drop errors
            }
        }
        $this->db->createCommand("SET FOREIGN_KEY_CHECKS = 1")->execute();
    }

    public function testDiscoveryFinds25Migrations(MigrationTester $I): void
    {
        $migrations = $this->service->getNewMigrations();

        $I->assertCount(25, $migrations, 'Should discover 25 migrations');
    }

    public function testAllMigrationsUp(MigrationTester $I): void
    {
        $migrations = $this->service->getNewMigrations();

        foreach ($migrations as $class) {
            $migration = $this->service->makeMigration($class);
            $this->migrator->up($migration);
        }

        // Check all tables exist
        foreach (self::TABLES as $table) {
            $schema = $this->db->getTableSchema($table, true);
            $I->assertNotNull($schema, "Table '$table' should exist after up");
        }

        // Check wildcard host inserted
        $count = $this->db->createCommand('SELECT COUNT(*) FROM hosts WHERE id = 1 AND name = "*"')->queryScalar();
        $I->assertEquals(1, $count, 'Wildcard host (id=1, name=*) should exist');
    }

    public function testAllMigrationsUpDown(MigrationTester $I): void
    {
        // UP all
        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $migration = $this->service->makeMigration($class);
            $this->migrator->up($migration);
        }

        // DOWN all (reverse order from history)
        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $migration = $this->service->makeRevertibleMigration($class);
            $this->migrator->down($migration);
        }

        // Check all tables gone
        foreach (self::TABLES as $table) {
            $schema = $this->db->getTableSchema($table, true);
            $I->assertNull($schema, "Table '$table' should not exist after down");
        }
    }

    public function testAllMigrationsDownUpDownUp(MigrationTester $I): void
    {
        // First cycle: DOWN (cleanup)
        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $this->migrator->down($this->service->makeRevertibleMigration($class));
        }
        $I->assertNull($this->db->getTableSchema('hosts', true), 'First down: hosts should not exist');

        // First cycle: UP
        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }
        $I->assertNotNull($this->db->getTableSchema('hosts', true), 'First up: hosts should exist');

        // Second cycle: DOWN
        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $this->migrator->down($this->service->makeRevertibleMigration($class));
        }
        $I->assertNull($this->db->getTableSchema('hosts', true), 'Second down: hosts should not exist');

        // Second cycle: UP (final state: DB ready)
        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }
        $I->assertNotNull($this->db->getTableSchema('hosts', true), 'Second up: hosts should exist');

        // Final state: all tables created, no pending migrations
        $migrations = $this->service->getNewMigrations();
        $I->assertCount(0, $migrations, 'No pending migrations after final up');
    }
}
