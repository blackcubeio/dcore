<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Models;

use Blackcube\Dcore\Services\DatabaseUpgrade;
use Blackcube\Dcore\Tests\Support\Addons\ProbeAddon;
use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000000CreateAddonProbes;
use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000001AddAddonProbesLabel;
use Blackcube\Dcore\Tests\Support\DatabaseCestTrait;
use Blackcube\Dcore\Tests\Support\ModelsTester;
use Blackcube\Dcore\Tests\Support\Upgrades\RenameProbeMigrations;
use InvalidArgumentException;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Db\Query\Query;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

/**
 * DatabaseUpgrade and MigrationRenameUpgrade against a real MySQL database.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class DatabaseUpgradeCest
{
    use DatabaseCestTrait {
        _before as protected databaseBefore;
    }

    private const PROBE_NAMESPACE = 'Blackcube\\Dcore\\Tests\\Support\\Addons\\ProbeMigrations';

    private const HISTORY_NAMES = [
        RenameProbeMigrations::OLD_CREATE,
        RenameProbeMigrations::OLD_LABEL,
        M800000000000CreateAddonProbes::class,
        M800000000001AddAddonProbesLabel::class,
    ];

    private DatabaseUpgrade $databaseUpgrade;
    private MigrationBuilder $builder;
    private Injector $injector;

    public function _before(ModelsTester $I): void
    {
        $this->databaseBefore($I);
        $this->builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $containerConfig = ContainerConfig::create()
            ->withDefinitions([
                ConnectionInterface::class => $this->db,
            ]);
        $this->injector = new Injector(new Container($containerConfig));
        // Declared twice on purpose: two modules may declare the same upgrade.
        $this->databaseUpgrade = new DatabaseUpgrade($this->db, $this->injector, [
            RenameProbeMigrations::class,
            RenameProbeMigrations::class,
        ]);
        $this->cleanUpgrades();
    }

    /**
     * Leaves no trace of a previous test: the upgrades table and the probe history lines.
     */
    private function cleanUpgrades(): void
    {
        if ($this->db->getTableSchema('upgrades', true) !== null) {
            $this->builder->dropTable('upgrades');
        }
        $history = $this->migrator->getHistory();
        $stale = [];
        foreach (self::HISTORY_NAMES as $name) {
            if (isset($history[$name]) === true) {
                $stale[] = $name;
            }
        }
        if (empty($stale) === false) {
            $this->migrator->updateHistory([], $stale);
        }
    }

    public function testTheUpgradesTableIsCreatedOnFirstUse(ModelsTester $I): void
    {
        $I->assertNull($this->db->getTableSchema('upgrades', true));

        $I->assertEquals([], $this->databaseUpgrade->getApplied());

        $I->assertNotNull($this->db->getTableSchema('upgrades', true));
        $I->assertEquals([RenameProbeMigrations::class], $this->databaseUpgrade->getDeclared(), 'Declared once');
        $I->assertEquals([RenameProbeMigrations::class], $this->databaseUpgrade->getPending());
    }

    public function testRenamesTheHistoryAndRecordsItself(ModelsTester $I): void
    {
        $this->migrator->updateHistory([
            RenameProbeMigrations::OLD_CREATE,
            RenameProbeMigrations::OLD_LABEL,
        ], []);

        $this->databaseUpgrade->apply(RenameProbeMigrations::class);

        $history = $this->migrator->getHistory();
        $I->assertArrayNotHasKey(RenameProbeMigrations::OLD_CREATE, $history);
        $I->assertArrayNotHasKey(RenameProbeMigrations::OLD_LABEL, $history);
        $I->assertArrayHasKey(M800000000000CreateAddonProbes::class, $history);
        $I->assertArrayHasKey(M800000000001AddAddonProbesLabel::class, $history);
        $probeService = new MigrationService($this->db, $this->injector, $this->migrator);
        $probeService->setSourceNamespaces([self::PROBE_NAMESPACE]);
        $I->assertEquals([], $probeService->getNewMigrations(), 'The renamed migrations count as applied');
        $I->assertTrue($this->databaseUpgrade->isApplied(RenameProbeMigrations::class));
        $I->assertEquals([], $this->databaseUpgrade->getPending());

        $applied = $this->databaseUpgrade->getApplied();
        $this->databaseUpgrade->apply(RenameProbeMigrations::class);
        $I->assertEquals($applied, $this->databaseUpgrade->getApplied(), 'A second run changes nothing');
        $I->assertEquals(1, $this->historyCount(M800000000000CreateAddonProbes::class));
    }

    public function testAPartiallyRenamedHistoryIsCompleted(ModelsTester $I): void
    {
        $this->migrator->updateHistory([
            RenameProbeMigrations::OLD_CREATE,
            M800000000001AddAddonProbesLabel::class,
        ], []);

        $this->databaseUpgrade->apply(RenameProbeMigrations::class);

        $history = $this->migrator->getHistory();
        $I->assertArrayNotHasKey(RenameProbeMigrations::OLD_CREATE, $history);
        $I->assertArrayHasKey(M800000000000CreateAddonProbes::class, $history);
        $I->assertArrayHasKey(M800000000001AddAddonProbesLabel::class, $history);
        $I->assertEquals(1, $this->historyCount(M800000000001AddAddonProbesLabel::class), 'A name already new is not written twice');
        $I->assertTrue($this->databaseUpgrade->isApplied(RenameProbeMigrations::class));
    }

    public function testOnlyUpgradesAreApplied(ModelsTester $I): void
    {
        $I->expectThrowable(InvalidArgumentException::class, fn() => $this->databaseUpgrade->apply(ProbeAddon::class));
    }

    private function historyCount(string $name): int
    {
        return (int) (new Query($this->db))
            ->from($this->migrator->getHistoryTable())
            ->andWhere([
                'name' => $name,
            ])
            ->count();
    }
}
