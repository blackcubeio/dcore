<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Upgrades;

use Blackcube\Dcore\Interfaces\UpgradeInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\Migrator;

/**
 * Rewrites the migration history after migration classes were renamed in a package: each old
 * name present in the history is replaced by its new name, without running any migration.
 * A module derives it and gives the map of its renames.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
abstract class MigrationRenameUpgrade implements UpgradeInterface
{
    private ?Migrator $migrator = null;

    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Old migration class => new migration class, both fully qualified.
     *
     * @return array<class-string, class-string>
     */
    abstract protected function getRenames(): array;

    /**
     * Only the old names still in the history are rewritten, oldest first, so a run interrupted
     * before its record is completed by the next one.
     */
    public function apply(): void
    {
        $renames = $this->getRenames();
        $history = array_reverse($this->getMigrator()->getHistory(), true);
        $add = [];
        $remove = [];
        foreach ($history as $old => $applyTime) {
            if (isset($renames[$old]) === true && isset($history[$renames[$old]]) === false) {
                $add[] = $renames[$old];
                $remove[] = $old;
            }
        }
        if (empty($add) === false) {
            $this->getMigrator()->updateHistory($add, $remove);
        }
    }

    private function getMigrator(): Migrator
    {
        if ($this->migrator === null) {
            $this->migrator = new Migrator($this->db, new NullMigrationInformer());
        }
        return $this->migrator;
    }
}
