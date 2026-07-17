<?php

declare(strict_types=1);

/**
 * `package:show <package>` Command
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Cli\Command;

use PHPdot\Package\PackageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'package:show', description: 'Show services, configs, and bindings for one package.')]
final class ShowCommand extends Command
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
     * Configure.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::REQUIRED, 'Composer package name (e.g. phpdot/http).');
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

        /**
         * @var string $name
         */
        $name = $input->getArgument('package');
        $info = $manifest->packages[$name] ?? null;

        if ($info === null) {
            $output->writeln(sprintf('<error>Package "%s" not found in manifest.</error>', $name));
            $output->writeln('<comment>Run `package:list` to see installed packages.</comment>');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $info->name));

        if ($info->description !== '') {
            $output->writeln($info->description);
        }

        if ($info->url !== '') {
            $output->writeln(sprintf('<comment>%s</comment>', $info->url));
        }

        $output->writeln('');

        $this->renderServices($output, $info->services);
        $this->renderConfigs($output, $info->configs);
        $this->renderBindings($output, $info->bindings);
        $this->renderOverrideHints($output, $info->bindings);

        return Command::SUCCESS;
    }

    /**
     * Render the services section of the show output.
     *
     * @param array<string, string> $services class => scope
     * @param OutputInterface $output
     *
     * @return void
     */
    private function renderServices(OutputInterface $output, array $services): void
    {
        if ($services === []) {
            return;
        }

        $output->writeln('<info>Services</info>');

        $table = new Table($output);
        $table->setHeaders(['Class', 'Scope']);

        foreach ($services as $class => $scope) {
            $table->addRow([$class, $scope]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * Render the configs section of the show output.
     *
     * @param array<string, string> $configs class => config name
     * @param OutputInterface $output
     *
     * @return void
     */
    private function renderConfigs(OutputInterface $output, array $configs): void
    {
        if ($configs === []) {
            return;
        }

        $output->writeln('<info>Configs</info>');

        $table = new Table($output);
        $table->setHeaders(['DTO', 'Config file']);

        foreach ($configs as $class => $name) {
            $table->addRow([$class, sprintf('config/%s.php', $name)]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * Render the bindings section of the show output.
     *
     * @param array<string, string> $bindings interface => implementation
     * @param OutputInterface $output
     *
     * @return void
     */
    private function renderBindings(OutputInterface $output, array $bindings): void
    {
        if ($bindings === []) {
            return;
        }

        $output->writeln('<info>Bindings</info>');

        $table = new Table($output);
        $table->setHeaders(['Interface', 'Default implementation']);

        foreach ($bindings as $interface => $implementation) {
            $table->addRow([$interface, $implementation]);
        }

        $table->render();
        $output->writeln('');
    }

    /**
     * Render the override-hints section of the show output.
     *
     * @param array<string, string> $bindings interface => implementation
     * @param OutputInterface $output
     *
     * @return void
     */
    private function renderOverrideHints(OutputInterface $output, array $bindings): void
    {
        if ($bindings === []) {
            return;
        }

        $first = array_key_first($bindings);
        $short = substr($first, (int) strrpos($first, '\\') + 1);

        $output->writeln('<info>How to override</info>');
        $output->writeln(sprintf('  Globally: <comment>$builder->add(%s::class, fn ($c) => new MyImpl())->singleton();</comment>', $short));
        $output->writeln(sprintf('  Scoped to one consumer: <comment>$builder->when(MyController::class)->needs(%s::class)->provide(MyImpl::class);</comment>', $short));
        $output->writeln('');
    }
}
