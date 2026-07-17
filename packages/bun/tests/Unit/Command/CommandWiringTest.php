<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Command;

use PHPdot\Bun\Command\InstallCommand;
use PHPdot\Bun\Command\RemoveCommand;
use PHPdot\Bun\Command\RunCommand;
use PHPdot\Bun\Command\ViewCommand;
use PHPdot\Bun\Command\XCommand;
use PHPdot\Bun\Tests\Support\TestBun;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Each command parses its console input and delegates to the Bun service with the right argv.
 */
final class CommandWiringTest extends TestCase
{
    private TestBun $fake;

    protected function setUp(): void
    {
        $this->fake = new TestBun();
    }

    protected function tearDown(): void
    {
        $this->fake->cleanup();
    }

    public function testInstallCommandWithDevFlag(): void
    {
        $tester = new CommandTester(new InstallCommand($this->fake->bun));
        $tester->execute(['packages' => ['lodash', 'axios'], '--dev' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['add', '--dev', 'lodash', 'axios'], $this->fake->lastArgs());
    }

    public function testRemoveCommand(): void
    {
        $tester = new CommandTester(new RemoveCommand($this->fake->bun));
        $tester->execute(['packages' => ['lodash']]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['remove', 'lodash'], $this->fake->lastArgs());
    }

    public function testViewCommand(): void
    {
        $tester = new CommandTester(new ViewCommand($this->fake->bun));
        $tester->execute(['package' => 'react']);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['pm', 'view', 'react'], $this->fake->lastArgs());
    }

    public function testRunCommandForwardsArgs(): void
    {
        $tester = new CommandTester(new RunCommand($this->fake->bun));
        $tester->execute(['script' => 'dev', 'args' => ['--port', '3000']]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['run', 'dev', '--port', '3000'], $this->fake->lastArgs());
    }

    public function testXCommandForwardsArgs(): void
    {
        $tester = new CommandTester(new XCommand($this->fake->bun));
        $tester->execute(['tool' => 'prettier', 'args' => ['src', '--write']]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(['x', 'prettier', 'src', '--write'], $this->fake->lastArgs());
    }
}
