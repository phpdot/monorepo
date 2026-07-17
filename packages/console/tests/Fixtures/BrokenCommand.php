<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'broken', description: 'Always fails')]
final class BrokenCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \RuntimeException('This command is broken');
    }
}
