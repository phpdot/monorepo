<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\LogLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogLevelTest extends TestCase
{
    #[Test]
    public function nameReturnsCorrectStringForEachLevel(): void
    {
        self::assertSame('DEBUG', LogLevel::name(LogLevel::DEBUG));
        self::assertSame('INFO', LogLevel::name(LogLevel::INFO));
        self::assertSame('NOTICE', LogLevel::name(LogLevel::NOTICE));
        self::assertSame('WARNING', LogLevel::name(LogLevel::WARNING));
        self::assertSame('ERROR', LogLevel::name(LogLevel::ERROR));
        self::assertSame('CRITICAL', LogLevel::name(LogLevel::CRITICAL));
        self::assertSame('ALERT', LogLevel::name(LogLevel::ALERT));
        self::assertSame('EMERGENCY', LogLevel::name(LogLevel::EMERGENCY));
    }

    #[Test]
    public function nameReturnsUnknownForInvalidLevel(): void
    {
        self::assertSame('UNKNOWN', LogLevel::name(999));
    }

    #[Test]
    public function fromPsrConvertsStringToIntForAllLevels(): void
    {
        self::assertSame(LogLevel::DEBUG, LogLevel::fromPsr('debug'));
        self::assertSame(LogLevel::INFO, LogLevel::fromPsr('info'));
        self::assertSame(LogLevel::NOTICE, LogLevel::fromPsr('notice'));
        self::assertSame(LogLevel::WARNING, LogLevel::fromPsr('warning'));
        self::assertSame(LogLevel::ERROR, LogLevel::fromPsr('error'));
        self::assertSame(LogLevel::CRITICAL, LogLevel::fromPsr('critical'));
        self::assertSame(LogLevel::ALERT, LogLevel::fromPsr('alert'));
        self::assertSame(LogLevel::EMERGENCY, LogLevel::fromPsr('emergency'));
    }

    #[Test]
    public function fromPsrIsCaseInsensitive(): void
    {
        self::assertSame(LogLevel::ERROR, LogLevel::fromPsr('ERROR'));
        self::assertSame(LogLevel::INFO, LogLevel::fromPsr('Info'));
    }

    #[Test]
    public function fromPsrDefaultsToDebugForInvalidLevelName(): void
    {
        self::assertSame(LogLevel::DEBUG, LogLevel::fromPsr('invalid'));
        self::assertSame(LogLevel::DEBUG, LogLevel::fromPsr(''));
    }
}
