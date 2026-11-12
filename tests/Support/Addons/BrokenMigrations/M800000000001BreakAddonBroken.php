<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Addons\BrokenMigrations;

use RuntimeException;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Second migration of the broken add-on: throws while $broken is true, so a test can see an
 * activation stop then resume.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M800000000001BreakAddonBroken implements RevertibleMigrationInterface
{
    public static bool $broken = true;

    public function up(MigrationBuilder $b): void
    {
        if (static::$broken === true) {
            throw new RuntimeException('Broken on purpose.');
        }
        $b->addColumn('{{%addonBroken}}', 'label', ColumnBuilder::string());
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropColumn('{{%addonBroken}}', 'label');
    }
}
