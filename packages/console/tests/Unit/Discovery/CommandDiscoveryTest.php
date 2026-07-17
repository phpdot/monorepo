<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit\Discovery;

use PHPdot\Console\Discovery\CommandDiscovery;
use PHPdot\Console\Tests\Fixtures\DependencyCommand;
use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPdot\Console\Tests\Fixtures\MathAddCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandDiscoveryTest extends TestCase
{
    private CommandDiscovery $discovery;

    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->discovery = new CommandDiscovery();
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures';
    }

    #[Test]
    public function discoversGreetCommand(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        self::assertArrayHasKey('greet', $result);
        self::assertSame(GreetCommand::class, $result['greet']);
    }

    #[Test]
    public function discoversMathAddCommand(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        self::assertArrayHasKey('math:add', $result);
        self::assertSame(MathAddCommand::class, $result['math:add']);
    }

    #[Test]
    public function returnsCommandNameToClassMap(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        foreach ($result as $name => $class) {
            self::assertIsString($name);
            self::assertTrue(class_exists($class), 'Class ' . $class . ' does not exist');
        }
    }

    #[Test]
    public function skipsPlainClass(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        self::assertNotContains(\PHPdot\Console\Tests\Fixtures\PlainClass::class, $result);
    }

    #[Test]
    public function skipsNoAttributeCommand(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        self::assertNotContains(\PHPdot\Console\Tests\Fixtures\NoAttributeCommand::class, $result);
        self::assertArrayNotHasKey('no-attr', $result);
    }

    #[Test]
    public function returnsEmptyForNonExistentDirectory(): void
    {
        $result = $this->discovery->discover(['/nonexistent/path/that/does/not/exist']);

        self::assertSame([], $result);
    }

    #[Test]
    public function returnsSortedByCommandName(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        $names = array_keys($result);
        $sorted = $names;
        sort($sorted);

        self::assertSame($sorted, $names);
    }

    #[Test]
    public function discoversDependencyCommand(): void
    {
        $result = $this->discovery->discover([$this->fixturesDir]);

        self::assertArrayHasKey('dep:test', $result);
        self::assertSame(DependencyCommand::class, $result['dep:test']);
    }
}
