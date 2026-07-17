<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Fixtures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class NoAttributeCommand extends Command
{
    protected static $defaultName = 'no-attr';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('no attr');

        return self::SUCCESS;
    }
}
