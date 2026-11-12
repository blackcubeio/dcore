<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Tests\Support\Upgrades;

use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000000CreateAddonProbes;
use Blackcube\Dcore\Tests\Support\Addons\ProbeMigrations\M800000000001AddAddonProbesLabel;
use Blackcube\Dcore\Upgrades\MigrationRenameUpgrade;

/**
 * Upgrade of the test suite: the probe migrations under their former names.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class RenameProbeMigrations extends MigrationRenameUpgrade
{
    public const OLD_CREATE = 'Blackcube\\Dcore\\Tests\\Support\\Addons\\ProbeMigrations\\M000000000000CreateAddonProbes';
    public const OLD_LABEL = 'Blackcube\\Dcore\\Tests\\Support\\Addons\\ProbeMigrations\\M000000000001AddAddonProbesLabel';

    protected function getRenames(): array
    {
        return [
            self::OLD_CREATE => M800000000000CreateAddonProbes::class,
            self::OLD_LABEL => M800000000001AddAddonProbesLabel::class,
        ];
    }
}
