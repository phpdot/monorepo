<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'greet', description: 'Greet someone')]
final class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Who to greet', 'World');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name */
        $name = $input->getArgument('name');

        $this->info($output, 'Hello, ' . $name . '!');

        return self::SUCCESS;
    }
}
