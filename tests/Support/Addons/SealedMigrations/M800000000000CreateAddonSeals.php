<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Addons\SealedMigrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\MigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration without down(): the sealed add-on cannot be deactivated.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class M800000000000CreateAddonSeals implements MigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%addonSeals}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
        ]);
    }
}
