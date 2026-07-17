<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use PHPdot\Console\ConsoleConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsoleConfigTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new ConsoleConfig();

        self::assertSame('PHPdot', $config->name);
        self::assertSame('1.0.0', $config->version);
        self::assertSame('', $config->cachePath);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new ConsoleConfig(
            name: 'MyApp',
            version: '2.5.0',
            cachePath: '/tmp/commands.cache.php',
        );

        self::assertSame('MyApp', $config->name);
        self::assertSame('2.5.0', $config->version);
        self::assertSame('/tmp/commands.cache.php', $config->cachePath);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $config = new ConsoleConfig();

        $reflection = new \ReflectionClass($config);

        self::assertTrue($reflection->isReadOnly());
    }
}
