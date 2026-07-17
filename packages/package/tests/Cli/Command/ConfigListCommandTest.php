<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\ConfigListCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigListCommandTest extends CommandTestCase
{
    #[Test]
    public function it_lists_config_files_with_owner(): void
    {
        $this->writeStandardManifest();
        $this->writeConfigFile('http');
        $this->writeConfigFile('console');

        $tester = new CommandTester(new ConfigListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('config/http.php', $output);
        self::assertStringContainsString('config/console.php', $output);
        self::assertStringContainsString('phpdot/http', $output);
        self::assertStringContainsString('phpdot/console', $output);
    }

    #[Test]
    public function it_marks_present_files(): void
    {
        $this->writeStandardManifest();
        $this->writeConfigFile('http');

        $tester = new CommandTester(new ConfigListCommand($this->getManager()));
        $tester->execute([]);

        self::assertStringContainsString('present', $tester->getDisplay());
    }

    #[Test]
    public function it_marks_missing_files(): void
    {
        $this->writeStandardManifest();
        // No config files written

        $tester = new CommandTester(new ConfigListCommand($this->getManager()));
        $tester->execute([]);

        self::assertStringContainsString('missing', $tester->getDisplay());
    }

    #[Test]
    public function it_handles_empty_config_list(): void
    {
        $this->writeManifest(<<<'PHP'
            <?php
            return [
                'generated_at' => '2026-04-28T12:00:00+00:00',
                'ownedConfigs' => [],
                'packages' => [],
            ];
            PHP);

        $tester = new CommandTester(new ConfigListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No config files registered', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_manifest_is_missing(): void
    {
        $tester = new CommandTester(new ConfigListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No manifest found', $tester->getDisplay());
    }
}
