<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\ConfigCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCommandTest extends CommandTestCase
{
    #[Test]
    public function it_reports_when_a_package_owns_no_configs(): void
    {
        $tester = new CommandTester(new ConfigCommand($this->getManager()));
        $tester->execute(['package' => 'phpdot/database']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('owns no config files', $tester->getDisplay());
    }

    #[Test]
    public function it_requires_a_package_argument(): void
    {
        $tester = new CommandTester(new ConfigCommand($this->getManager()));

        $this->expectException(RuntimeException::class);
        $tester->execute([]);
    }
}
