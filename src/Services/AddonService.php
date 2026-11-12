<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Addons\AddonInterface;
use Blackcube\Dcore\Models\Addon;
use Composer\InstalledVersions;
use InvalidArgumentException;
use RuntimeException;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\Service\MigrationService;
use Yiisoft\Injector\Injector;

/**
 * Registry of the add-ons. The declared ones come from the params
 * ('blackcube/dcore' => 'addons' => [id => class]), the active ones have a row in the
 * addons table. Activation plays the migrations of the add-on, deactivation reverts them.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class AddonService
{
    private ?Migrator $migrator = null;

    /**
     * @param array<string, class-string<AddonInterface>> $addons id => class, from the params
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Injector $injector,
        private readonly array $addons = [],
    ) {}

    /**
     * The declared add-ons, id => class, as the params carry them.
     *
     * @return array<string, class-string<AddonInterface>>
     */
    public function getDeclared(): array
    {
        return $this->addons;
    }

    /**
     * Whether the id is declared and its class implements AddonInterface.
     */
    public function isDeclared(string $id): bool
    {
        return isset($this->addons[$id]) === true
            && is_subclass_of($this->addons[$id], AddonInterface::class) === true;
    }

    /**
     * The class of a declared add-on.
     *
     * @return class-string<AddonInterface>
     */
    public function getClass(string $id): string
    {
        if ($this->isDeclared($id) === false) {
            throw new InvalidArgumentException('Unknown add-on "' . $id . '".');
        }
        return $this->addons[$id];
    }

    /**
     * The name the add-on shows; an orphan row, whose class is gone, shows its id.
     */
    public function getName(string $id): string
    {
        $name = $id;
        if ($this->isDeclared($id) === true) {
            $name = $this->addons[$id]::getName();
        }
        return $name;
    }

    /**
     * Whether the addons table exists: before the dcore migrations are played, nothing is active.
     */
    public function hasRegistry(): bool
    {
        return $this->db->getTableSchema((new Addon())->tableName(), true) !== null;
    }

    /**
     * Declared and present in the registry. A row whose package is gone is active nowhere.
     */
    public function isActive(string $id): bool
    {
        $active = false;
        if ($this->isDeclared($id) === true && $this->hasRegistry() === true) {
            $active = Addon::query()->andWhere(['id' => $id])->one() !== null;
        }
        return $active;
    }

    /**
     * The active add-ons among the declared ones, id => class.
     *
     * @return array<string, class-string<AddonInterface>>
     */
    public function getActive(): array
    {
        $active = [];
        if ($this->hasRegistry() === true) {
            $addonQuery = Addon::query();
            /** @var Addon $addon */
            foreach ($addonQuery->each() as $addon) {
                if ($this->isDeclared($addon->getId()) === true) {
                    $active[$addon->getId()] = $this->addons[$addon->getId()];
                }
            }
        }
        return $active;
    }

    /**
     * The ids of the rows whose add-on is not declared any more (package removed from Composer).
     *
     * @return string[]
     */
    public function getOrphans(): array
    {
        $orphans = [];
        if ($this->hasRegistry() === true) {
            $addonQuery = Addon::query();
            /** @var Addon $addon */
            foreach ($addonQuery->each() as $addon) {
                if ($this->isDeclared($addon->getId()) === false) {
                    $orphans[] = $addon->getId();
                }
            }
        }
        return $orphans;
    }

    /**
     * The installed version of the package of a declared add-on, null when Composer does not know it.
     */
    public function getInstalledVersion(string $id): ?string
    {
        $package = $this->getClass($id)::getPackage();
        $version = null;
        if (InstalledVersions::isInstalled($package) === true) {
            $version = InstalledVersions::getPrettyVersion($package);
        }
        return $version;
    }

    /**
     * Whether the installed package carries migrations the base has not played yet.
     */
    public function hasPendingMigrations(string $id): bool
    {
        $class = $this->getClass($id);
        return count($this->getNewMigrations($class)) > 0;
    }

    /**
     * Plays the migrations of the add-on not applied yet, in order, then records it as active.
     * A failing migration stops the run: the ones already played stay, no row is written,
     * a second run resumes where it stopped.
     */
    public function activate(string $id): void
    {
        $class = $this->getClass($id);
        $this->checkRegistry();
        if ($this->isActive($id) === true) {
            throw new RuntimeException('Add-on "' . $id . '" is already active.');
        }
        $this->migrateUp($class);
        $addon = new Addon();
        $addon->setId($id);
        $addon->setVersion($this->getInstalledVersion($id));
        $addon->save();
    }

    /**
     * Plays the migrations a newer package brought, then records the installed version.
     */
    public function update(string $id): void
    {
        $class = $this->getClass($id);
        $addon = $this->getActiveRow($id);
        $this->migrateUp($class);
        $addon->setVersion($this->getInstalledVersion($id));
        $addon->save();
    }

    /**
     * Reverts the migrations of the add-on, most recent first, then removes its row.
     * Refused before anything is touched when one of them is not revertible.
     */
    public function deactivate(string $id): void
    {
        $class = $this->getClass($id);
        $addon = $this->getActiveRow($id);
        $this->migrateDown($class);
        $addon->delete();
    }

    /**
     * Removes the row of an add-on without touching its tables: for a package gone from Composer.
     */
    public function forget(string $id): void
    {
        $addon = $this->getActiveRow($id);
        $addon->delete();
    }

    private function getActiveRow(string $id): Addon
    {
        $this->checkRegistry();
        $addon = Addon::query()->andWhere(['id' => $id])->one();
        if ($addon === null) {
            throw new RuntimeException('Add-on "' . $id . '" is not active.');
        }
        return $addon;
    }

    private function checkRegistry(): void
    {
        if ($this->hasRegistry() === false) {
            throw new RuntimeException('The addons table is missing: play the dcore migrations first.');
        }
    }

    /**
     * @param class-string<AddonInterface> $class
     * @return list<class-string>
     */
    private function getNewMigrations(string $class): array
    {
        $migrations = [];
        $namespace = $class::getMigrationsNamespace();
        if ($namespace !== null) {
            $migrations = $this->createMigrationService($namespace)->getNewMigrations();
        }
        return $migrations;
    }

    /**
     * @param class-string<AddonInterface> $class
     */
    private function migrateUp(string $class): void
    {
        $namespace = $class::getMigrationsNamespace();
        if ($namespace !== null) {
            $service = $this->createMigrationService($namespace);
            foreach ($service->getNewMigrations() as $migration) {
                $this->getMigrator()->up($service->makeMigration($migration));
            }
        }
    }

    /**
     * @param class-string<AddonInterface> $class
     */
    private function migrateDown(string $class): void
    {
        $namespace = $class::getMigrationsNamespace();
        if ($namespace !== null) {
            $service = $this->createMigrationService($namespace);
            // The history is ordered most recent first: the order of the down.
            $applied = $service->filterMigrations(array_keys($this->getMigrator()->getHistory()), [$namespace]);
            foreach ($applied as $migration) {
                if (is_subclass_of($migration, RevertibleMigrationInterface::class) === false) {
                    throw new RuntimeException('Migration ' . $migration . ' is not revertible: the add-on cannot be deactivated.');
                }
            }
            foreach ($applied as $migration) {
                $this->getMigrator()->down($service->makeRevertibleMigration($migration));
            }
        }
    }

    private function createMigrationService(string $namespace): MigrationService
    {
        $service = new MigrationService($this->db, $this->injector, $this->getMigrator());
        $service->setSourceNamespaces([$namespace]);
        return $service;
    }

    private function getMigrator(): Migrator
    {
        if ($this->migrator === null) {
            $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        }
        return $this->migrator;
    }
}
