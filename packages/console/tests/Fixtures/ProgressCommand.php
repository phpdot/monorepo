<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'progress:demo', description: 'Demo progress')]
final class ProgressCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->withProgress($output, ['a', 'b', 'c'], function (string $item, int $index): void {
            // Process each item
        });

        return self::SUCCESS;
    }
}
