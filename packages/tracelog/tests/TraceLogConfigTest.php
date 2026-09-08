<?php

declare(strict_types=1);

/**
 * TraceLog Config Test
 *
 * Pins the validation that keeps a misconfigured install from failing silently
 * at runtime — the constructor is the only gate, so it must reject every shape
 * the write path would otherwise swallow.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\TraceLog\Tests;

use PHPdot\TraceLog\Exception\TraceLogException;
use PHPdot\TraceLog\TraceLogConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceLogConfigTest extends TestCase
{
    #[Test]
    public function defaultsWorkStandaloneWithoutAnyConfigFile(): void
    {
        $config = new TraceLogConfig();

        self::assertSame('/var/log/app', $config->basePath);
        self::assertSame(100, $config->minLevel);
        self::assertSame('json', $config->defaultFormatter);
        self::assertSame(50, $config->maxChannels);
        self::assertNull($config->encryptionKey);
        self::assertTrue($config->enabled);
    }

    #[Test]
    public function anEmptyBasePathIsRejected(): void
    {
        $this->expectException(TraceLogException::class);
        $this->expectExceptionMessage('base path must not be empty');

        new TraceLogConfig(basePath: '');
    }

    #[Test]
    public function anUnknownFormatterNameIsRejected(): void
    {
        $this->expectException(TraceLogException::class);
        $this->expectExceptionMessage("formatter must be 'json' or 'text'");

        new TraceLogConfig(defaultFormatter: 'line');
    }

    #[Test]
    public function aMinimumLevelOutsideThePsr3RangeIsRejected(): void
    {
        $this->expectException(TraceLogException::class);
        $this->expectExceptionMessage('between 100 and 600');

        new TraceLogConfig(minLevel: 999);
    }

    #[Test]
    public function aZeroChannelBudgetIsRejected(): void
    {
        $this->expectException(TraceLogException::class);
        $this->expectExceptionMessage('at least 1');

        new TraceLogConfig(maxChannels: 0);
    }

    #[Test]
    public function anEmptyKeyIsNormalizedToUnsetNotABootFailure(): void
    {
        $config = new TraceLogConfig(encryptionKey: '');

        self::assertNull(
            $config->encryptionKey,
            'a blank TRACELOG_KEY= line yields an empty string; the contract says that means disabled',
        );
    }
}
