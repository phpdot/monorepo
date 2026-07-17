<?php

declare(strict_types=1);

/**
 * `package:paths` Command
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

#[AsCommand(name: 'package:paths', description: 'Show all resolved paths used by phpdot/package.')]
final class PathsCommand extends Command
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
        $manifestPath = $this->manager->manifestPath();
        $defsPath = $this->manager->definitionsPath();

        $rows = [
            ['Project root', $this->manager->basePath(), ''],
            ['Vendor', $this->manager->vendorPath(), $this->presence($this->manager->vendorPath())],
            ['Configs', $this->manager->configPath(), $this->presence($this->manager->configPath())],
            ['Manifest', $manifestPath, $this->fileStatus($manifestPath)],
            ['Definitions', $defsPath, $this->fileStatus($defsPath)],
        ];

        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Path', 'Location', 'Status']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Presence.
     *
     * @param string $path
     *
     * @return string
     */
    private function presence(string $path): string
    {
        return is_dir($path) ? '<info>exists</info>' : '<comment>missing</comment>';
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
            return '<comment>missing</comment>';
        }

        $age = time() - (int) filemtime($path);

        return sprintf('<info>exists</info> (%s)', $this->formatAge($age));
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
