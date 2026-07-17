<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dep:test', description: 'Command with dependency')]
final class DependencyCommand extends Command
{
    public function __construct(
        private readonly string $prefix,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->prefix . ': Hello!');

        return self::SUCCESS;
    }
}
