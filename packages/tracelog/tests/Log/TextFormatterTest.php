<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Formatter\TextFormatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextFormatterTest extends TestCase
{
    #[Test]
    public function formatReturnsHumanReadableString(): void
    {
        $formatter = new TextFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Hello world',
            'channel' => 'app',
            'context' => [],
        ];

        $output = $formatter->format($record);

        self::assertIsString($output);
        self::assertStringEndsWith("\n", $output);
    }

    #[Test]
    public function containsTimestampChannelLevelMessage(): void
    {
        $formatter = new TextFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Test log entry',
            'channel' => 'auth',
            'context' => [],
        ];

        $output = $formatter->format($record);

        self::assertStringContainsString('2026-04-02', $output);
        self::assertStringContainsString('auth', $output);
        self::assertStringContainsString('INFO', $output);
        self::assertStringContainsString('Test log entry', $output);
    }
}
