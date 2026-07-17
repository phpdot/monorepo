<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\ListCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListCommandTest extends CommandTestCase
{
    #[Test]
    public function it_lists_all_installed_packages(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('2 phpdot packages installed', $output);
        self::assertStringContainsString('phpdot/http', $output);
        self::assertStringContainsString('phpdot/console', $output);
    }

    #[Test]
    public function it_shows_service_config_and_binding_counts(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ListCommand($this->getManager()));
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/phpdot\/http\s+\|\s+2\s+\|\s+1\s+\|\s+1/', $output);
        self::assertMatchesRegularExpression('/phpdot\/console\s+\|\s+1\s+\|\s+1\s+\|\s+0/', $output);
    }

    #[Test]
    public function it_fails_when_manifest_is_missing(): void
    {
        $tester = new CommandTester(new ListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No manifest found', $tester->getDisplay());
    }

    #[Test]
    public function it_handles_empty_package_list(): void
    {
        $this->writeManifest(<<<'PHP'
            <?php
            return [
                'generated_at' => '2026-04-28T12:00:00+00:00',
                'ownedConfigs' => [],
                'packages' => [],
            ];
            PHP);

        $tester = new CommandTester(new ListCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No phpdot packages installed', $tester->getDisplay());
    }
}
