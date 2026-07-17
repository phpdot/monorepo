<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Handler\NullHandler;
use PHPdot\TraceLog\Log\LogLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullHandlerTest extends TestCase
{
    #[Test]
    public function handleDoesNotThrow(): void
    {
        $handler = new NullHandler();

        $handler->handle([
            'level' => LogLevel::ERROR,
            'message' => 'This should be silently discarded',
        ]);

        self::assertTrue(true);
    }

    #[Test]
    public function isHandlingReturnsTrueForAllLevels(): void
    {
        $handler = new NullHandler();

        self::assertTrue($handler->isHandling(LogLevel::DEBUG));
        self::assertTrue($handler->isHandling(LogLevel::INFO));
        self::assertTrue($handler->isHandling(LogLevel::NOTICE));
        self::assertTrue($handler->isHandling(LogLevel::WARNING));
        self::assertTrue($handler->isHandling(LogLevel::ERROR));
        self::assertTrue($handler->isHandling(LogLevel::CRITICAL));
        self::assertTrue($handler->isHandling(LogLevel::ALERT));
        self::assertTrue($handler->isHandling(LogLevel::EMERGENCY));
    }
}
