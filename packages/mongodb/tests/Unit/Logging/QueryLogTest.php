<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Logging;

use PHPdot\MongoDB\Logging\QueryLog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryLogTest extends TestCase
{
    #[Test]
    public function it_stores_all_properties(): void
    {
        $log = new QueryLog(
            operation: 'find',
            collection: 'users',
            filter: ['status' => 'active'],
            durationMs: 12.5,
            slow: false,
        );

        self::assertSame('find', $log->operation);
        self::assertSame('users', $log->collection);
        self::assertSame(['status' => 'active'], $log->filter);
        self::assertSame(12.5, $log->durationMs);
        self::assertFalse($log->slow);
    }

    #[Test]
    public function it_stores_slow_flag_true(): void
    {
        $log = new QueryLog(
            operation: 'aggregate',
            collection: 'orders',
            filter: [],
            durationMs: 500.0,
            slow: true,
        );

        self::assertTrue($log->slow);
        self::assertSame(500.0, $log->durationMs);
    }

    #[Test]
    public function it_stores_empty_filter(): void
    {
        $log = new QueryLog(
            operation: 'countDocuments',
            collection: 'items',
            filter: [],
            durationMs: 0.5,
            slow: false,
        );

        self::assertSame([], $log->filter);
    }

    #[Test]
    public function it_stores_complex_filter(): void
    {
        $filter = [
            'status' => 'active',
            'age' => ['$gte' => 18],
            '$or' => [['role' => 'admin'], ['score' => ['$gt' => 90]]],
        ];

        $log = new QueryLog(
            operation: 'find',
            collection: 'users',
            filter: $filter,
            durationMs: 3.2,
            slow: false,
        );

        self::assertSame($filter, $log->filter);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $log = new QueryLog('find', 'users', [], 1.0, false);

        $reflection = new \ReflectionClass($log);
        self::assertTrue($reflection->isReadOnly());
    }
}
