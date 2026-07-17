<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Formatter\JsonFormatter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonFormatterTest extends TestCase
{
    #[Test]
    public function formatReturnsValidJson(): void
    {
        $formatter = new JsonFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Test message',
            'channel' => 'app',
            'context' => [],
        ];

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
    }

    #[Test]
    public function containsRequiredFields(): void
    {
        $formatter = new JsonFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Test message',
            'channel' => 'app',
            'context' => ['foo' => 'bar'],
        ];

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('timestamp', $decoded);
        self::assertArrayHasKey('level', $decoded);
        self::assertArrayHasKey('level_name', $decoded);
        self::assertArrayHasKey('message', $decoded);
        self::assertArrayHasKey('channel', $decoded);
    }

    #[Test]
    public function traceFieldsPromotedToTopLevel(): void
    {
        $formatter = new JsonFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Test message',
            'trace_id' => 'abc123',
            'span_id' => 'def456',
            'channel' => 'app',
            'tags' => ['env' => 'test'],
            'context' => [],
        ];

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertSame('abc123', $decoded['trace_id']);
        self::assertSame('def456', $decoded['span_id']);
        self::assertSame(['env' => 'test'], $decoded['tags']);
    }

    #[Test]
    public function contextNestedUnderContextKey(): void
    {
        $formatter = new JsonFormatter();

        $record = [
            'timestamp' => '2026-04-02T12:00:00.000000+00:00',
            'level' => 200,
            'level_name' => 'INFO',
            'message' => 'Test message',
            'channel' => 'app',
            'context' => ['user_id' => 42],
        ];

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('context', $decoded);
        self::assertSame(['user_id' => 42], $decoded['context']);
    }
}
