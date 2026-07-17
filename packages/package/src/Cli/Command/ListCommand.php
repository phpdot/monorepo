<?php

declare(strict_types=1);

/**
 * `package:list` Command
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Cli\Command;

use PHPdot\Package\PackageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'package:list', description: 'List all installed phpdot packages.')]
final class ListCommand extends Command
{
    /**
     * __construct.
     *
     * @param PackageManager $manager
     */
    public function __construct(private readonly PackageManager $manager)
    {
        parent::__construct();
    }

    /**
     * Execute.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $manifest = $this->manager->manifest();

        if ($manifest === null) {
            $output->writeln('<comment>No manifest found. Run `composer install` to generate one.</comment>');

            return Command::FAILURE;
        }

        if ($manifest->packages === []) {
            $output->writeln('<comment>No phpdot packages installed.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%d phpdot package%s installed</info>', count($manifest->packages), count($manifest->packages) === 1 ? '' : 's'));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Package', 'Services', 'Configs', 'Bindings']);

        foreach ($manifest->packages as $info) {
            $table->addRow([
                $info->name,
                (string) count($info->services),
                (string) count($info->configs),
                (string) count($info->bindings),
            ]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln('<comment>Use `package:show <package-name>` for details.</comment>');

        return Command::SUCCESS;
    }
}
