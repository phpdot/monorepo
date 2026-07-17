<?php

declare(strict_types=1);

/**
 * `package:configs` Command
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

#[AsCommand(name: 'package:configs', description: 'List every config file owned by a phpdot package.')]
final class ConfigListCommand extends Command
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
        $configPath = $this->manager->configPath();
        $basePath = $this->manager->basePath();

        foreach ($manifest->packages as $info) {
            foreach ($info->configs as $configName) {
                $absolute = $configPath . '/' . $configName . '.php';
                $relative = ltrim(str_replace($basePath, '', $absolute), '/');

                $rows[] = [
                    $relative,
                    $info->name,
                    $this->fileStatus($absolute),
                ];
            }
        }

        if ($rows === []) {
            $output->writeln('<comment>No config files registered.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Config file', 'Owner', 'Status']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * File status.
     *
     * @param string $path
     *
     * @return string
     */
    private function fileStatus(string $path): string
    {
        if (!is_file($path)) {
            return '<error>missing</error>';
        }

        $age = time() - (int) filemtime($path);

        return sprintf('<info>present</info> (modified %s)', $this->formatAge($age));
    }

    /**
     * Format age.
     *
     * @param int $seconds
     *
     * @return string
     */
    private function formatAge(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's ago';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ago';
        }

        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . 'h ago';
        }

        return intdiv($seconds, 86400) . 'd ago';
    }
}
