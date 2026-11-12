<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Addons\BrokenMigrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * First migration of the broken add-on: the one that passes.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M800000000000CreateAddonBroken implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%addonBroken}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%addonBroken}}');
    }
}
