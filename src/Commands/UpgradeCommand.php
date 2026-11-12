<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Dcore\Commands;

use Blackcube\Dcore\Services\DatabaseUpgrade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiisoft\Yii\Console\ExitCode;

/**
 * Console command applying the database upgrades the modules declare: an upgrade already
 * recorded prints "ok", a pending one is applied and recorded.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
#[AsCommand(
    name: 'upgrade',
    description: 'Apply the database upgrades the modules declare',
)]
class UpgradeCommand extends Command
{
    public function __construct(
        private readonly DatabaseUpgrade $databaseUpgrade,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $consoleOutput): int
    {
        $io = new SymfonyStyle($input, $consoleOutput);
        $declared = $this->databaseUpgrade->getDeclared();
        $applied = $this->databaseUpgrade->getApplied();

        if (empty($declared) === true) {
            $io->writeln('No upgrade declared.');
        }
        foreach ($declared as $class) {
            if (isset($applied[$class]) === true) {
                $io->writeln($class . ': ok');
            } else {
                $this->databaseUpgrade->apply($class);
                $io->writeln($class . ': <info>applied</info>');
            }
        }

        return ExitCode::OK;
    }
}
