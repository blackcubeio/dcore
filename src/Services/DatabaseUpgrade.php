<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Services;

use Blackcube\Dcore\Interfaces\UpgradeInterface;
use Blackcube\Dcore\Models\Upgrade;
use InvalidArgumentException;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Schema\Column\ColumnBuilder;
use Yiisoft\Injector\Injector;

/**
 * Applies the database upgrades the modules declare in their params
 * ('blackcube/dcore' => 'upgrades' => [class]) and keeps the applied ones in the upgrades table.
 * The service creates the table itself when it is missing: `upgrade` runs before `migrate:up`.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class DatabaseUpgrade
{
    private bool $checkTable = true;

    /**
     * @param list<class-string<UpgradeInterface>> $upgrades from the params, in declaration order
     */
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Injector $injector,
        private readonly array $upgrades = [],
    ) {}

    /**
     * The declared upgrades, a class once, in declaration order.
     *
     * @return list<class-string<UpgradeInterface>>
     */
    public function getDeclared(): array
    {
        return array_values(array_unique($this->upgrades));
    }

    /**
     * The applied upgrades, class => timestamp, oldest first.
     *
     * @return array<class-string, int>
     */
    public function getApplied(): array
    {
        $this->checkTable();
        $applied = [];
        $upgradeQuery = Upgrade::query()->orderBy(['applyTime' => SORT_ASC]);
        /** @var Upgrade $upgrade */
        foreach ($upgradeQuery->each() as $upgrade) {
            $applied[$upgrade->getName()] = $upgrade->getApplyTime();
        }
        return $applied;
    }

    /**
     * The declared upgrades not applied yet, in declaration order.
     *
     * @return list<class-string<UpgradeInterface>>
     */
    public function getPending(): array
    {
        $applied = $this->getApplied();
        $pending = [];
        foreach ($this->getDeclared() as $class) {
            if (isset($applied[$class]) === false) {
                $pending[] = $class;
            }
        }
        return $pending;
    }

    public function isApplied(string $class): bool
    {
        $this->checkTable();
        return Upgrade::query()->andWhere(['name' => $class])->one() !== null;
    }

    /**
     * Applies an upgrade then records it. Nothing happens when it is recorded already.
     */
    public function apply(string $class): void
    {
        if (is_subclass_of($class, UpgradeInterface::class) === false) {
            throw new InvalidArgumentException($class . ' is not an upgrade.');
        }
        if ($this->isApplied($class) === false) {
            /** @var UpgradeInterface $upgrade */
            $upgrade = $this->injector->make($class);
            $upgrade->apply();
            $record = new Upgrade();
            $record->setName($class);
            $record->setApplyTime(time());
            $record->save();
        }
    }

    private function checkTable(): void
    {
        if ($this->checkTable === true) {
            $tableName = (new Upgrade())->tableName();
            if ($this->db->getTableSchema($tableName, true) === null) {
                $b = new MigrationBuilder($this->db, new NullMigrationInformer());
                $b->createTable($tableName, [
                    'name' => ColumnBuilder::string(180)->notNull(),
                    'applyTime' => ColumnBuilder::integer()->notNull(),
                    'PRIMARY KEY([[name]])',
                ]);
            }
            $this->checkTable = false;
        }
    }
}
