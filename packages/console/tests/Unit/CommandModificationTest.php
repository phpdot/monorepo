<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use InvalidArgumentException;
use PHPdot\Console\Application;
use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPdot\Console\Tests\Fixtures\MathAddCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class CommandModificationTest extends TestCase
{
    // ── alias ──────────────────────────────────────────────

    #[Test]
    public function aliasMakesCommandResolvableUnderBothNames(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app->alias('greet', 'hi');

        $sym = $app->getSymfonyApplication();
        self::assertTrue($sym->has('greet'));
        self::assertTrue($sym->has('hi'));
    }

    #[Test]
    public function aliasOriginalNameStillWorks(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);
        $app->alias('greet', 'hi');

        $output = new BufferedOutput();
        $exit = $app->call('greet', ['name' => 'Alice'], $output);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Hello, Alice', $output->fetch());
    }

    #[Test]
    public function aliasNewNameInvokesSameCommand(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);
        $app->alias('greet', 'hi');

        $output = new BufferedOutput();
        $exit = $app->call('hi', ['name' => 'Bob'], $output);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Hello, Bob', $output->fetch());
    }

    #[Test]
    public function aliasOnUnknownCommandThrows(): void
    {
        $app = new Application();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot alias unknown command "missing"');

        $app->alias('missing', 'm');
    }

    #[Test]
    public function aliasToExistingDifferentCommandThrows(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class, MathAddCommand::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"math:add" already exists');

        $app->alias('greet', 'math:add');
    }

    // ── rename ─────────────────────────────────────────────

    #[Test]
    public function renameRemovesOldAndRegistersNew(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app->rename('greet', 'salute');

        $sym = $app->getSymfonyApplication();
        self::assertFalse($sym->has('greet'));
        self::assertTrue($sym->has('salute'));
    }

    #[Test]
    public function renamedCommandReportsNewName(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);
        $app->rename('greet', 'salute');

        $command = $app->getSymfonyApplication()->find('salute');

        self::assertSame('salute', $command->getName());
    }

    #[Test]
    public function renameOnUnknownCommandThrows(): void
    {
        $app = new Application();

        $this->expectException(InvalidArgumentException::class);

        $app->rename('missing', 'foo');
    }

    #[Test]
    public function renameToExistingNameThrows(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class, MathAddCommand::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"math:add" already exists');

        $app->rename('greet', 'math:add');
    }

    // ── override ───────────────────────────────────────────

    #[Test]
    public function overrideUpdatesDescription(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app->override('greet', description: 'Custom description');

        $command = $app->getSymfonyApplication()->find('greet');
        self::assertSame('Custom description', $command->getDescription());
    }

    #[Test]
    public function overrideUpdatesHelpText(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app->override('greet', help: 'Detailed help text.');

        $command = $app->getSymfonyApplication()->find('greet');
        self::assertSame('Detailed help text.', $command->getHelp());
    }

    #[Test]
    public function overrideAcceptsBothFieldsAtOnce(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app->override('greet', description: 'Desc', help: 'Help');

        $command = $app->getSymfonyApplication()->find('greet');
        self::assertSame('Desc', $command->getDescription());
        self::assertSame('Help', $command->getHelp());
    }

    #[Test]
    public function overrideOnUnknownCommandThrows(): void
    {
        $app = new Application();

        $this->expectException(InvalidArgumentException::class);

        $app->override('missing', description: 'foo');
    }

    // ── combined ───────────────────────────────────────────

    #[Test]
    public function aliasAndOverrideCompose(): void
    {
        $app = new Application();
        $app->register([GreetCommand::class]);

        $app
            ->alias('greet', 'hi')
            ->override('greet', description: 'Updated greeting');

        $original = $app->getSymfonyApplication()->find('greet');
        self::assertSame('Updated greeting', $original->getDescription());

        // Aliased name still resolves
        self::assertTrue($app->getSymfonyApplication()->has('hi'));
    }
}
