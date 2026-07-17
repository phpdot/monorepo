<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use PHPdot\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'math:add', description: 'Add two numbers')]
final class MathAddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('a', InputArgument::REQUIRED, 'First number')
            ->addArgument('b', InputArgument::REQUIRED, 'Second number');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $a */
        $a = $input->getArgument('a');

        /** @var string $b */
        $b = $input->getArgument('b');

        $sum = (int) $a + (int) $b;

        $output->writeln((string) $sum);

        return self::SUCCESS;
    }
}
