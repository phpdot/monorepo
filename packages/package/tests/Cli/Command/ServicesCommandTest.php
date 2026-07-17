<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\ServicesCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ServicesCommandTest extends CommandTestCase
{
    #[Test]
    public function it_lists_every_service_across_packages(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ServicesCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('3 services', $output);
        self::assertStringContainsString('PHPdot\\Http\\ResponseFactory', $output);
        self::assertStringContainsString('PHPdot\\Http\\HttpConfig', $output);
        self::assertStringContainsString('PHPdot\\Console\\ConsoleConfig', $output);
    }

    #[Test]
    public function it_includes_scope_and_owner_columns(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ServicesCommand($this->getManager()));
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('SINGLETON', $output);
        self::assertStringContainsString('phpdot/http', $output);
        self::assertStringContainsString('phpdot/console', $output);
    }

    #[Test]
    public function it_handles_empty_service_list(): void
    {
        $this->writeManifest(<<<'PHP'
            <?php
            return [
                'generated_at' => '2026-04-28T12:00:00+00:00',
                'ownedConfigs' => [],
                'packages' => [],
            ];
            PHP);

        $tester = new CommandTester(new ServicesCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No services registered', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_manifest_is_missing(): void
    {
        $tester = new CommandTester(new ServicesCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No manifest found', $tester->getDisplay());
    }
}
