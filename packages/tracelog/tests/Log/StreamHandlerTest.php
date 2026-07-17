<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Formatter\JsonFormatter;
use PHPdot\TraceLog\Log\Handler\StreamHandler;
use PHPdot\TraceLog\Log\LogLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamHandlerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tracelog_stream_' . uniqid();
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    #[Test]
    public function writesToFile(): void
    {
        $path = $this->tmpDir . '/test.log';
        $handler = new StreamHandler($path, new JsonFormatter(), LogLevel::DEBUG);

        $handler->handle([
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => LogLevel::INFO,
            'level_name' => 'INFO',
            'message' => 'Test write',
            'channel' => 'app',
            'context' => [],
        ]);

        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);
        self::assertStringContainsString('Test write', $content);
    }

    #[Test]
    public function createsDirectoryIfNeeded(): void
    {
        $path = $this->tmpDir . '/test.log';

        self::assertDirectoryDoesNotExist($this->tmpDir);

        $handler = new StreamHandler($path, new JsonFormatter(), LogLevel::DEBUG);
        $handler->handle([
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => LogLevel::INFO,
            'level_name' => 'INFO',
            'message' => 'Dir creation test',
            'channel' => 'app',
            'context' => [],
        ]);

        self::assertDirectoryExists($this->tmpDir);
        self::assertFileExists($path);
    }

    #[Test]
    public function isHandlingRespectsMinLevel(): void
    {
        $handler = new StreamHandler('/dev/null', new JsonFormatter(), LogLevel::WARNING);

        self::assertFalse($handler->isHandling(LogLevel::DEBUG));
        self::assertFalse($handler->isHandling(LogLevel::INFO));
        self::assertTrue($handler->isHandling(LogLevel::WARNING));
        self::assertTrue($handler->isHandling(LogLevel::ERROR));
        self::assertTrue($handler->isHandling(LogLevel::EMERGENCY));
    }

    #[Test]
    public function silentOnPermissionError(): void
    {
        $handler = new StreamHandler('/proc/nonexistent/test.log', new JsonFormatter(), LogLevel::DEBUG);

        // Should not throw
        $handler->handle([
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => LogLevel::INFO,
            'level_name' => 'INFO',
            'message' => 'Should not throw',
            'channel' => 'app',
            'context' => [],
        ]);

        self::assertTrue(true);
    }

    #[Test]
    public function doesNotWriteBelowMinLevel(): void
    {
        mkdir($this->tmpDir, 0o755, true);
        $path = $this->tmpDir . '/test.log';
        $handler = new StreamHandler($path, new JsonFormatter(), LogLevel::ERROR);

        $handler->handle([
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => LogLevel::DEBUG,
            'level_name' => 'DEBUG',
            'message' => 'Should be ignored',
            'channel' => 'app',
            'context' => [],
        ]);

        self::assertFileDoesNotExist($path);
    }
}
