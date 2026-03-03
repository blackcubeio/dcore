<?php

declare(strict_types=1);

/**
 * DatabaseCestTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * Trait for Cest classes that need database setup/teardown at Cest level (not per-test).
 *
 * Behavior:
 * - Drops all tables and runs migrations ONCE at the start of the Cest
 * - Runs all tests in the Cest
 * - If all tests pass: drops all tables at the end
 * - If any test fails: keeps tables for debugging
 *
 * Usage:
 *   final class MyCest
 *   {
 *       use DatabaseCestTrait;
 *
 *       public function testSomething(ModelsTester $I): void { ... }
 *   }
 */
trait DatabaseCestTrait
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';

    protected ConnectionInterface $db;
    protected Migrator $migrator;
    protected MigrationService $service;

    /** @var array<string, bool> Track setup per Cest class */
    private static array $setupDone = [];

    /** @var array<string, bool> Track failures per Cest class */
    private static array $hasFailed = [];

    /** @var array<string, bool> Track if cleanup registered per Cest class */
    private static array $cleanupRegistered = [];

    public function _before(ModelsTester $I): void
    {
        $className = static::class;

        $this->initializeDatabase();

        // Only setup once per Cest class
        if (!isset(self::$setupDone[$className]) || !self::$setupDone[$className]) {
            $this->dropAllTables();
            $this->runMigrations();
            self::$setupDone[$className] = true;
            self::$hasFailed[$className] = false;

            // Register cleanup to run at the end
            if (!isset(self::$cleanupRegistered[$className]) || !self::$cleanupRegistered[$className]) {
                self::$cleanupRegistered[$className] = true;
                $db = $this->db;
                $migrator = $this->migrator;
                $service = $this->service;

                register_shutdown_function(function () use ($className, $db, $migrator, $service) {
                    // Only cleanup if no failures
                    if (!isset(self::$hasFailed[$className]) || !self::$hasFailed[$className]) {
                        $this->dropAllTablesStatic($db, $migrator, $service);
                    }
                });
            }
        }
    }

    public function _after(ModelsTester $I): void
    {
        // Do nothing - cleanup is handled by shutdown function
    }

    public function _failed(ModelsTester $I): void
    {
        $className = static::class;
        self::$hasFailed[$className] = true;
    }

    private function initializeDatabase(): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();

        ConnectionProvider::set($this->db);

        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        $container = new Container($containerConfig);
        $injector = new Injector($container);

        $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        $this->service = new MigrationService($this->db, $injector, $this->migrator);
        $this->service->setSourceNamespaces([self::NAMESPACE]);
    }

    private function dropAllTables(): void
    {
        // Drop tables directly instead of running down migrations
        // This avoids issues when tables don't exist but history says they do
        $this->db->createCommand("SET FOREIGN_KEY_CHECKS = 0")->execute();
        $tables = [
            'contents_authors', 'tags_authors', 'authors',
            'contents_blocs', 'tags_blocs', 'contents_tags',
            'xeos_blocs', 'xeos', 'schemas_schemas', 'globalXeos',
            'sitemaps', 'blocs', 'contents', 'tags', 'menus',
            'types_elasticSchemas', 'slugs', 'languages', 'types',
            'translationGroups', 'elasticSchemas', 'hosts', 'parameters',
            'yii_migration', 'migration'
        ];
        foreach ($tables as $table) {
            try {
                $this->db->createCommand("DROP TABLE IF EXISTS `$table`")->execute();
            } catch (\Throwable $e) {
                // Ignore drop errors
            }
        }
        $this->db->createCommand("SET FOREIGN_KEY_CHECKS = 1")->execute();
    }

    private function runMigrations(): void
    {
        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }
    }

    private function dropAllTablesStatic(
        ConnectionInterface $db,
        Migrator $migrator,
        MigrationService $service
    ): void {
        // Drop tables directly instead of running down migrations
        $db->createCommand("SET FOREIGN_KEY_CHECKS = 0")->execute();
        $tables = [
            'contents_authors', 'tags_authors', 'authors',
            'contents_blocs', 'tags_blocs', 'contents_tags',
            'xeos_blocs', 'xeos', 'schemas_schemas', 'globalXeos',
            'sitemaps', 'blocs', 'contents', 'tags', 'menus',
            'types_elasticSchemas', 'slugs', 'languages', 'types',
            'translationGroups', 'elasticSchemas', 'hosts', 'parameters',
            'yii_migration', 'migration'
        ];
        foreach ($tables as $table) {
            try {
                $db->createCommand("DROP TABLE IF EXISTS `$table`")->execute();
            } catch (\Throwable $e) {
                // Ignore drop errors
            }
        }
        $db->createCommand("SET FOREIGN_KEY_CHECKS = 1")->execute();
    }
}
