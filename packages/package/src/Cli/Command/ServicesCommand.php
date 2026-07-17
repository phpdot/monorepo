<?php

declare(strict_types=1);

/**
 * `package:services` Command
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

#[AsCommand(name: 'package:services', description: 'List every service across every installed phpdot package.')]
final class ServicesCommand extends Command
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

        $rows = [];

        foreach ($manifest->packages as $info) {
            foreach ($info->services as $class => $scope) {
                $rows[] = [$scope, $class, $info->name];
            }
        }

        if ($rows === []) {
            $output->writeln('<comment>No services registered.</comment>');

            return Command::SUCCESS;
        }

        usort($rows, static fn(array $a, array $b): int => $a[1] <=> $b[1]);

        $output->writeln('');
        $output->writeln(sprintf('<info>%d service%s</info>', count($rows), count($rows) === 1 ? '' : 's'));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Scope', 'Service', 'Owner']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');

        return Command::SUCCESS;
    }
}
