<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\PathsCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PathsCommandTest extends CommandTestCase
{
    #[Test]
    public function it_shows_all_resolved_paths(): void
    {
        $tester = new CommandTester(new PathsCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('Project root', $output);
        self::assertStringContainsString('Vendor', $output);
        self::assertStringContainsString('Configs', $output);
        self::assertStringContainsString('Manifest', $output);
        self::assertStringContainsString('Definitions', $output);
        self::assertStringContainsString($this->basePath, $output);
    }

    #[Test]
    public function it_marks_existing_directories(): void
    {
        $tester = new CommandTester(new PathsCommand($this->getManager()));
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('exists', $output);
    }

    #[Test]
    public function it_marks_missing_files(): void
    {
        $tester = new CommandTester(new PathsCommand($this->getManager()));
        $tester->execute([]);

        // Manifest and Definitions don't exist yet — should show missing
        $output = $tester->getDisplay();
        self::assertStringContainsString('missing', $output);
    }

    #[Test]
    public function it_shows_age_when_manifest_exists(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new PathsCommand($this->getManager()));
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/exists \(\d+[smhd] ago\)/', $output);
    }
}
