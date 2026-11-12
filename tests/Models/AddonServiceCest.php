<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Migrations\M000000000025CreateAddons;
use Blackcube\Dcore\Models\Addon;
use Blackcube\Dcore\Services\AddonService;
use Blackcube\Dcore\Tests\Support\Addons\BrokenAddon;
use Blackcube\Dcore\Tests\Support\Addons\BrokenMigrations\M800000000000CreateAddonBroken;
use Blackcube\Dcore\Tests\Support\Addons\BrokenMigrations\M800000000001BreakAddonBroken;
use Blackcube\Dcore\Tests\Support\Addons\ProbeAddon;
use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000000CreateAddonProbes;
use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000001AddAddonProbesLabel;
use Blackcube\Dcore\Tests\Support\Addons\SealedAddon;
use Blackcube\Dcore\Tests\Support\Addons\SealedMigrations\M800000000000CreateAddonSeals;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use InvalidArgumentException;
use RuntimeException;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * AddonService against a real MySQL database, with the add-ons of the test suite.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class AddonServiceCest
{
    use DatabaseCestTrait {
        _before as protected databaseBefore;
    }

    private const ADDON_TABLES = [
        'addonProbes',
        'addonSeals',
        'addonBroken',
    ];

    private const ADDON_MIGRATIONS = [
        M800000000000CreateAddonProbes::class,
        M800000000001AddAddonProbesLabel::class,
        M800000000000CreateAddonSeals::class,
        M800000000000CreateAddonBroken::class,
        M800000000001BreakAddonBroken::class,
    ];

    private AddonService $addonService;
    private MigrationBuilder $builder;

    public function _before(ModelsTester $I): void
    {
        $this->databaseBefore($I);
        $this->builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        $injector = new Injector(new Container($containerConfig));
        $this->addonService = new AddonService($this->db, $injector, [
            'probe' => ProbeAddon::class,
            'sealed' => SealedAddon::class,
            'broken' => BrokenAddon::class,
            'ghost' => 'Blackcube\\Dcore\\Tests\\Support\\Addons\\GhostAddon',
        ]);
        $this->cleanAddons();
    }

    /**
     * Leaves no trace of a previous test: add-on tables, their history lines, the registry rows.
     */
    private function cleanAddons(): void
    {
        foreach (self::ADDON_TABLES as $table) {
            if ($this->db->getTableSchema($table, true) !== null) {
                $this->builder->dropTable($table);
            }
        }
        $history = $this->migrator->getHistory();
        $stale = [];
        foreach (self::ADDON_MIGRATIONS as $class) {
            if (isset($history[$class]) === true) {
                $stale[] = $class;
            }
        }
        if (empty($stale) === false) {
            $this->migrator->updateHistory([], $stale);
        }
        $addonQuery = Addon::query();
        /** @var Addon $addon */
        foreach ($addonQuery->each() as $addon) {
            $addon->delete();
        }
        M800000000001BreakAddonBroken::$broken = true;
    }

    public function testDeclared(ModelsTester $I): void
    {
        $I->assertCount(4, $this->addonService->getDeclared());
        $I->assertTrue($this->addonService->isDeclared('probe'));
        $I->assertFalse($this->addonService->isDeclared('ghost'), 'A missing class is not declared');
        $I->assertFalse($this->addonService->isDeclared('nope'));
        $I->assertEquals(ProbeAddon::class, $this->addonService->getClass('probe'));
        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->addonService->getClass('ghost'));
        $I->assertNotNull($this->addonService->getInstalledVersion('probe'), 'dcore is the root package, Composer knows its version');
    }

    public function testNothingIsActiveWithoutRegistry(ModelsTester $I): void
    {
        $this->builder->dropTable('addons');
        $this->migrator->updateHistory([], [
            M000000000025CreateAddons::class,
        ]);

        $I->assertFalse($this->addonService->hasRegistry());
        $I->assertFalse($this->addonService->isActive('probe'));
        $I->assertEquals([], $this->addonService->getActive());
        $I->assertEquals([], $this->addonService->getOrphans());
        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->activate('probe'));
        $I->assertNull($this->db->getTableSchema('addonProbes', true), 'Refused before any migration');

        $this->migrator->up($this->service->makeMigration(M000000000025CreateAddons::class));
        $I->assertTrue($this->addonService->hasRegistry());
    }

    public function testActivateThenDeactivate(ModelsTester $I): void
    {
        $this->addonService->activate('probe');

        $I->assertTrue($this->addonService->isActive('probe'));
        $I->assertEquals(['probe' => ProbeAddon::class], $this->addonService->getActive());
        $schema = $this->db->getTableSchema('addonProbes', true);
        $I->assertNotNull($schema, 'The add-on table exists after activation');
        $I->assertArrayHasKey('label', $schema->getColumns(), 'Both migrations were played');
        $history = $this->migrator->getHistory();
        $I->assertArrayHasKey(M800000000000CreateAddonProbes::class, $history);
        $I->assertArrayHasKey(M800000000001AddAddonProbesLabel::class, $history);
        $row = Addon::query()->andWhere(['id' => 'probe'])->one();
        $I->assertNotNull($row);
        $I->assertNotNull($row->getVersion());
        $I->assertNotNull($row->getDateCreate());
        $I->assertFalse($this->addonService->hasPendingMigrations('probe'));
        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->activate('probe'));

        $this->addonService->deactivate('probe');

        $I->assertFalse($this->addonService->isActive('probe'));
        $I->assertNull($this->db->getTableSchema('addonProbes', true), 'The add-on table is gone after deactivation');
        $history = $this->migrator->getHistory();
        $I->assertArrayNotHasKey(M800000000000CreateAddonProbes::class, $history);
        $I->assertArrayNotHasKey(M800000000001AddAddonProbesLabel::class, $history);
        $I->assertNull(Addon::query()->andWhere(['id' => 'probe'])->one());
        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->deactivate('probe'));

        $this->addonService->activate('probe');
        $I->assertTrue($this->addonService->isActive('probe'), 'Reactivation after a deactivation');
    }

    public function testUpdatePlaysTheMigrationsANewerPackageBrings(ModelsTester $I): void
    {
        $this->addonService->activate('probe');
        // The base of an older package: the second migration is not played.
        $this->builder->dropColumn('addonProbes', 'label');
        $this->migrator->updateHistory([], [
            M800000000001AddAddonProbesLabel::class,
        ]);
        $I->assertTrue($this->addonService->hasPendingMigrations('probe'));

        $this->addonService->update('probe');

        $I->assertFalse($this->addonService->hasPendingMigrations('probe'));
        $I->assertArrayHasKey('label', $this->db->getTableSchema('addonProbes', true)->getColumns());
        $I->assertNotNull(Addon::query()->andWhere(['id' => 'probe'])->one()->getVersion());
    }

    public function testDeactivationIsRefusedWhenAMigrationIsNotRevertible(ModelsTester $I): void
    {
        $this->addonService->activate('sealed');
        $I->assertTrue($this->addonService->isActive('sealed'));

        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->deactivate('sealed'));

        $I->assertTrue($this->addonService->isActive('sealed'), 'Nothing touched');
        $I->assertNotNull($this->db->getTableSchema('addonSeals', true));
        $I->assertArrayHasKey(M800000000000CreateAddonSeals::class, $this->migrator->getHistory());
    }

    public function testAFailingMigrationLeavesNoRowAndTheNextRunResumes(ModelsTester $I): void
    {
        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->activate('broken'));

        $I->assertFalse($this->addonService->isActive('broken'));
        $history = $this->migrator->getHistory();
        $I->assertArrayHasKey(M800000000000CreateAddonBroken::class, $history, 'The migration that passed stays');
        $I->assertArrayNotHasKey(M800000000001BreakAddonBroken::class, $history);

        M800000000001BreakAddonBroken::$broken = false;
        $this->addonService->activate('broken');

        $I->assertTrue($this->addonService->isActive('broken'));
        $I->assertArrayHasKey(M800000000001BreakAddonBroken::class, $this->migrator->getHistory());
    }

    public function testOrphanRowIsForgotten(ModelsTester $I): void
    {
        $gone = new Addon();
        $gone->setId('gone');
        $gone->setVersion('1.0.0');
        $gone->save();

        $I->assertEquals(['gone'], $this->addonService->getOrphans());
        $I->assertFalse($this->addonService->isActive('gone'), 'A row without a declared class is active nowhere');
        $I->assertArrayNotHasKey('gone', $this->addonService->getActive());

        $this->addonService->forget('gone');

        $I->assertEquals([], $this->addonService->getOrphans());
        $I->expectThrowable(RuntimeException::class, fn() => $this->addonService->forget('gone'));
    }
}
