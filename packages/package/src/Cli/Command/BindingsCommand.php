<?php

declare(strict_types=1);

/**
 * `package:bindings` Command
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

#[AsCommand(name: 'package:bindings', description: 'List every interface → implementation binding across all packages.')]
final class BindingsCommand extends Command
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
            foreach ($info->bindings as $interface => $implementation) {
                $rows[] = [$interface, $implementation, $info->name];
            }
        }

        if ($rows === []) {
            $output->writeln('<comment>No bindings registered.</comment>');

            return Command::SUCCESS;
        }

        usort($rows, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $output->writeln('');
        $output->writeln(sprintf('<info>%d binding%s</info>', count($rows), count($rows) === 1 ? '' : 's'));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Interface', 'Implementation', 'Owner']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln('<comment>Override globally: $builder->add(Iface::class, Impl::class)->singleton();</comment>');
        $output->writeln('<comment>Override scoped to consumer: $builder->when(Consumer::class)->needs(Iface::class)->provide(Impl::class);</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
