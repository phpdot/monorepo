<?php

declare(strict_types=1);

/**
 * Installs one or more packages via `bun add`. Auto-creates package.json + lockfile.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Command;

use PHPdot\Bun\Bun;
use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bun:install',
    description: 'Install one or more packages (bun add).',
)]
final class InstallCommand extends Command
{
    /**
     * Inject the Bun service the command drives.
     *
     * @param Bun $bun
     */
    public function __construct(
        private readonly Bun $bun,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Package name(s) to install')
            ->addOption('dev', 'D', InputOption::VALUE_NONE, 'Install as devDependencies');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var list<string> $packages
         */
        $packages = $input->getArgument('packages');

        return $this->bun->install($packages, (bool) $input->getOption('dev'));
    }
}
