<?php

declare(strict_types=1);

/**
 * TagQueryScopesCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2025 Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Models\Tag;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Dcore\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * Tests for ScopedQuery scopes on Tag: active(), available(), atDate(), publishable().
 * Note: Tag has no dateStart/dateEnd, so date-related tests are adapted.
 */
final class TagQueryScopesCest
{
    private const NAMESPACE = 'Blackcube\\Dcore\\Migrations';

    private ConnectionInterface $db;
    private Migrator $migrator;
    private MigrationService $service;

    public function _before(ModelsTester $I): void
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

        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $migration = $this->service->makeRevertibleMigration($class);
            $this->migrator->down($migration);
        }

        $migrations = $this->service->getNewMigrations();
        foreach ($migrations as $class) {
            $this->migrator->up($this->service->makeMigration($class));
        }

        Tag::clearSchemaCache();
    }

    public function _after(ModelsTester $I): void
    {
        $history = array_keys($this->migrator->getHistory());
        foreach ($history as $class) {
            $migration = $this->service->makeRevertibleMigration($class);
            $this->migrator->down($migration);
        }
    }

    // ========================================
    // Test 1 — active() filtre par champ actif
    // ========================================

    public function testActiveFiltersOnlyActiveRecords(ModelsTester $I): void
    {
        $active = new Tag();
        $active->setName('Active');
        $active->setActive(true);
        $active->save();

        $inactive = new Tag();
        $inactive->setName('Inactive');
        $inactive->setActive(false);
        $inactive->save();

        $results = Tag::query()->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Active', $results[0]->getName());
    }

    // ========================================
    // Test 2 — active() ne regarde pas les ancêtres
    // ========================================

    public function testActiveDoesNotCheckAncestors(ModelsTester $I): void
    {
        $parent = new Tag();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $results = Tag::query()->active()->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Child Active', $results[0]->getName());
    }

    // ========================================
    // Test 3 — available() = active() pour Tag (pas de dates)
    // ========================================

    public function testAvailableEqualsActiveForTag(ModelsTester $I): void
    {
        $active = new Tag();
        $active->setName('Active');
        $active->setActive(true);
        $active->save();

        $inactive = new Tag();
        $inactive->setName('Inactive');
        $inactive->setActive(false);
        $inactive->save();

        $results = Tag::query()->available()->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Active', $results[0]->getName());
    }

    // ========================================
    // Test 4 — atDate() ne fait rien pour Tag
    // ========================================

    public function testAtDateHasNoEffectForTag(ModelsTester $I): void
    {
        $tag1 = new Tag();
        $tag1->setName('Tag 1');
        $tag1->setActive(true);
        $tag1->save();

        $tag2 = new Tag();
        $tag2->setName('Tag 2');
        $tag2->setActive(true);
        $tag2->save();

        $results = Tag::query()->atDate('2025-06-15')->all();

        $I->assertCount(2, $results);
    }

    // ========================================
    // Test 5 — publishable() élément actif
    // ========================================

    public function testPublishableSingleActive(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Publishable');
        $tag->setActive(true);
        $tag->save();

        $results = Tag::query()->publishable()->all();

        $I->assertCount(1, $results);
        $I->assertEquals('Publishable', $results[0]->getName());
    }

    // ========================================
    // Test 6 — publishable() élément inactif = non
    // ========================================

    public function testPublishableInactiveExcluded(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Inactive');
        $tag->setActive(false);
        $tag->save();

        $results = Tag::query()->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 7 — publishable() parent inactif bloque enfant
    // ========================================

    public function testPublishableInactiveParentBlocksChild(ModelsTester $I): void
    {
        $parent = new Tag();
        $parent->setName('Parent Inactive');
        $parent->setActive(false);
        $parent->save();

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $results = Tag::query()->publishable()->all();

        $I->assertCount(0, $results);
    }

    // ========================================
    // Test 8 — publishable() chaîne complète valide
    // ========================================

    public function testPublishableValidChain(ModelsTester $I): void
    {
        $grandparent = new Tag();
        $grandparent->setName('Grandparent');
        $grandparent->setActive(true);
        $grandparent->save();

        $parent = new Tag();
        $parent->setName('Parent');
        $parent->setActive(true);
        $parent->saveInto($grandparent);

        $child = new Tag();
        $child->setName('Child');
        $child->setActive(true);
        $child->saveInto($parent);

        $results = Tag::query()->publishable()->all();

        $I->assertCount(3, $results);
    }

    // ========================================
    // Test 9 — publishable() racine sans ancêtres
    // ========================================

    public function testPublishableRootNoAncestors(ModelsTester $I): void
    {
        $tag = new Tag();
        $tag->setName('Root');
        $tag->setActive(true);
        $tag->save();

        $results = Tag::query()->publishable()->all();

        $I->assertCount(1, $results);
    }

    // ========================================
    // Test 10 — publishable() grand-parent inactif bloque
    // ========================================

    public function testPublishableInactiveGrandparentBlocks(ModelsTester $I): void
    {
        $grandparent = new Tag();
        $grandparent->setName('Grandparent Inactive');
        $grandparent->setActive(false);
        $grandparent->save();

        $parent = new Tag();
        $parent->setName('Parent Active');
        $parent->setActive(true);
        $parent->saveInto($grandparent);

        $child = new Tag();
        $child->setName('Child Active');
        $child->setActive(true);
        $child->saveInto($parent);

        $results = Tag::query()->publishable()->all();

        $I->assertCount(0, $results);
    }
}
