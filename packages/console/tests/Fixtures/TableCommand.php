<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'table:show', description: 'Show a table')]
final class TableCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->table($output, [
            ['name' => 'Alice', 'age' => '30'],
            ['name' => 'Bob', 'age' => '25'],
        ]);

        return self::SUCCESS;
    }
}
