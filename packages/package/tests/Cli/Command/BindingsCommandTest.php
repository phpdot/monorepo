<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Command;

use PHPdot\Package\Cli\Command\BindingsCommand;
use PHPdot\Package\Tests\Cli\Fixture\CommandTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class BindingsCommandTest extends CommandTestCase
{
    #[Test]
    public function it_lists_every_binding_across_packages(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new BindingsCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('1 binding', $output);
        self::assertStringContainsString('Psr\\Http\\Message\\ResponseFactoryInterface', $output);
        self::assertStringContainsString('PHPdot\\Http\\ResponseFactory', $output);
        self::assertStringContainsString('phpdot/http', $output);
    }

    #[Test]
    public function it_emits_override_hints(): void
    {
        $this->writeStandardManifest();

        $tester = new CommandTester(new BindingsCommand($this->getManager()));
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Override globally', $output);
        self::assertStringContainsString('Override scoped to consumer', $output);
    }

    #[Test]
    public function it_handles_empty_binding_list(): void
    {
        $this->writeManifest(<<<'PHP'
            <?php
            return [
                'generated_at' => '2026-04-28T12:00:00+00:00',
                'ownedConfigs' => [],
                'packages' => [],
            ];
            PHP);

        $tester = new CommandTester(new BindingsCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No bindings registered', $tester->getDisplay());
    }

    #[Test]
    public function it_fails_when_manifest_is_missing(): void
    {
        $tester = new CommandTester(new BindingsCommand($this->getManager()));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No manifest found', $tester->getDisplay());
    }
}
