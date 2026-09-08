<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use PHPdot\Mcp\Exception\ConfigurationException;
use PHPdot\Mcp\Server\McpConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpConfigTest extends TestCase
{
    #[Test]
    public function itCarriesWhatItWasGiven(): void
    {
        $config = new McpConfig(
            name: 'dot',
            version: '0.3.0',
            discoveryDirs: ['/www/app/protected/Apps'],
            sessionPath: '/www/app/protected/runtime/cache/mcp-sessions',
            sessionTtl: 900,
        );

        self::assertSame('dot', $config->name);
        self::assertSame('0.3.0', $config->version);
        self::assertSame(['/www/app/protected/Apps'], $config->discoveryDirs);
        self::assertSame('/www/app/protected/runtime/cache/mcp-sessions', $config->sessionPath);
        self::assertSame(900, $config->sessionTtl);
    }

    #[Test]
    public function theServerIdentityIsNotOptional(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('the server name (mcp.name)');

        new McpConfig(version: '1.0.0', sessionPath: '/tmp/mcp');
    }

    #[Test]
    public function theServerVersionIsNotOptional(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('the server version (mcp.version)');

        new McpConfig(name: 'dot', sessionPath: '/tmp/mcp');
    }

    #[Test]
    public function aSessionMustOutliveAtLeastOneSecond(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('a session ttl of at least one second');

        new McpConfig(name: 'dot', version: '1.0.0', sessionPath: '/tmp/mcp', sessionTtl: 0);
    }
}
