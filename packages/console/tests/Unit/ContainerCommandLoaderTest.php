<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use PHPdot\Console\ContainerCommandLoader;
use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

final class ContainerCommandLoaderTest extends TestCase
{
    #[Test]
    public function hasReturnsTrueForRegisteredCommand(): void
    {
        $loader = new ContainerCommandLoader(
            $this->createStubContainer(),
            ['greet' => GreetCommand::class],
        );

        self::assertTrue($loader->has('greet'));
    }

    #[Test]
    public function hasReturnsFalseForUnregisteredCommand(): void
    {
        $loader = new ContainerCommandLoader(
            $this->createStubContainer(),
            ['greet' => GreetCommand::class],
        );

        self::assertFalse($loader->has('unknown'));
    }

    #[Test]
    public function getResolvesCommandFromContainer(): void
    {
        $loader = new ContainerCommandLoader(
            $this->createStubContainer(),
            ['greet' => GreetCommand::class],
        );

        $command = $loader->get('greet');

        self::assertInstanceOf(GreetCommand::class, $command);
    }

    #[Test]
    public function getThrowsCommandNotFoundExceptionForUnknownCommand(): void
    {
        $loader = new ContainerCommandLoader(
            $this->createStubContainer(),
            ['greet' => GreetCommand::class],
        );

        $this->expectException(CommandNotFoundException::class);

        $loader->get('unknown');
    }

    #[Test]
    public function getNamesReturnsAllCommandNames(): void
    {
        $loader = new ContainerCommandLoader(
            $this->createStubContainer(),
            [
                'greet' => GreetCommand::class,
                'math:add' => \PHPdot\Console\Tests\Fixtures\MathAddCommand::class,
            ],
        );

        $names = $loader->getNames();

        self::assertSame(['greet', 'math:add'], $names);
    }

    private function createStubContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }
}
