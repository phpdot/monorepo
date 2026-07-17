<?php

declare(strict_types=1);

/**
 * Shows metadata for a known package via `bun pm view`.
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
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'bun:view',
    description: 'Show metadata for a package (bun pm view).',
)]
final class ViewCommand extends Command
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
        $this->addArgument('package', InputArgument::REQUIRED, 'Package name to inspect');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * @var string $package
         */
        $package = $input->getArgument('package');

        return $this->bun->view($package);
    }
}
