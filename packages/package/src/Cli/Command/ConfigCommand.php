<?php

declare(strict_types=1);

/**
 * `package:config <package>` Command
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Package\Cli\Command;

use PHPdot\Package\PackageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'package:config', description: "Show a package's original default config, without touching your own file.")]
final class ConfigCommand extends Command
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
        $this->addArgument('package', InputArgument::REQUIRED, 'Composer package name (e.g. phpdot/database).');
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
        /**
         * @var string $package
         */
        $package = $input->getArgument('package');

        $configs = $this->manager->renderConfig($package);

        if ($configs === []) {
            $output->writeln(sprintf('<comment>Package "%s" owns no config files (or is not installed).</comment>', $package));

            return Command::SUCCESS;
        }

        foreach ($configs as $name => $content) {
            $output->writeln(sprintf('<info># config/%s.php — original defaults</info>', $name));
            $output->writeln('');
            $output->write($content);
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
