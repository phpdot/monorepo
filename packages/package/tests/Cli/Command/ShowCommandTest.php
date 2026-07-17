<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\ShowCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowCommandTest extends CommandTestCase
{
    #[Test]
    public function it_shows_full_surface_for_one_package(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ShowCommand($this->getManager()));
        $tester->execute(['package' => 'phpdot/http']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('phpdot/http', $output);
        self::assertStringContainsString('HTTP package.', $output);
        self::assertStringContainsString('Services', $output);
        self::assertStringContainsString('PHPdot\\Http\\ResponseFactory', $output);
        self::assertStringContainsString('Configs', $output);
        self::assertStringContainsString('config/http.php', $output);
        self::assertStringContainsString('Bindings', $output);
        self::assertStringContainsString('Psr\\Http\\Message\\ResponseFactoryInterface', $output);
    }

    #[Test]
    public function it_emits_override_hints_when_bindings_exist(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ShowCommand($this->getManager()));
        $tester->execute(['package' => 'phpdot/http']);

        $output = $tester->getDisplay();
        self::assertStringContainsString('How to override', $output);
        self::assertStringContainsString('$builder->add', $output);
        self::assertStringContainsString('$builder->when', $output);
    }

    #[Test]
    public function it_omits_sections_when_no_bindings(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ShowCommand($this->getManager()));
        $tester->execute(['package' => 'phpdot/console']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('Services', $output);
        self::assertStringNotContainsString('Bindings', $output);
        self::assertStringNotContainsString('How to override', $output);
    }

    #[Test]
    public function it_fails_when_package_not_in_manifest(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new ShowCommand($this->getManager()));
        $tester->execute(['package' => 'nonexistent/pkg']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_manifest_is_missing(): void
    {
        $tester = new CommandTester(new ShowCommand($this->getManager()));
        $tester->execute(['package' => 'phpdot/http']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No manifest found', $tester->getDisplay());
    }
}
