<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Logging;

use PHPdot\MongoDB\Logging\QueryLog;
use PHPdot\MongoDB\Logging\QueryLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryLoggerTest extends TestCase
{
    #[Test]
    public function it_logs_queries(): void
    {
        $logger = new QueryLogger();
        $logger->log('find', 'users', ['status' => 'active'], 5.0);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('find', $logs[0]->operation);
        self::assertSame('users', $logs[0]->collection);
        self::assertSame(['status' => 'active'], $logs[0]->filter);
        self::assertSame(5.0, $logs[0]->durationMs);
        self::assertFalse($logs[0]->slow);
    }

    #[Test]
    public function it_marks_slow_queries(): void
    {
        $logger = new QueryLogger(slowThresholdMs: 50.0);
        $logger->log('find', 'users', [], 51.0);
        $logger->log('find', 'users', [], 10.0);

        $slow = $logger->getSlow();
        self::assertCount(1, $slow);
        self::assertTrue($slow[0]->slow);
        self::assertSame(51.0, $slow[0]->durationMs);
    }

    #[Test]
    public function it_counts_logged_queries(): void
    {
        $logger = new QueryLogger();
        $logger->log('find', 'users', [], 1.0);
        $logger->log('insertOne', 'users', [], 2.0);
        $logger->log('updateOne', 'users', [], 3.0);

        self::assertSame(3, $logger->count());
    }

    #[Test]
    public function it_clears_logs(): void
    {
        $logger = new QueryLogger();
        $logger->log('find', 'users', [], 1.0);
        $logger->log('find', 'users', [], 2.0);

        $logger->clear();

        self::assertSame(0, $logger->count());
        self::assertSame([], $logger->getAll());
    }

    #[Test]
    public function it_respects_ring_buffer_max_size(): void
    {
        $logger = new QueryLogger(maxEntries: 3);

        $logger->log('op1', 'c1', [], 1.0);
        $logger->log('op2', 'c2', [], 2.0);
        $logger->log('op3', 'c3', [], 3.0);
        $logger->log('op4', 'c4', [], 4.0); // overwrites op1

        self::assertSame(3, $logger->count());

        $logs = $logger->getAll();
        $operations = array_map(static fn (QueryLog $l): string => $l->operation, $logs);
        self::assertContains('op4', $operations);
        self::assertNotContains('op1', $operations);
    }

    #[Test]
    public function it_returns_slow_threshold(): void
    {
        $logger = new QueryLogger(slowThresholdMs: 200.0);

        self::assertSame(200.0, $logger->getSlowThreshold());
    }

    #[Test]
    public function it_marks_query_at_threshold_as_slow(): void
    {
        $logger = new QueryLogger(slowThresholdMs: 100.0);
        $logger->log('find', 'users', [], 100.0);

        $logs = $logger->getAll();
        self::assertTrue($logs[0]->slow);
    }

    #[Test]
    public function it_returns_empty_slow_list_when_none(): void
    {
        $logger = new QueryLogger(slowThresholdMs: 100.0);
        $logger->log('find', 'users', [], 10.0);
        $logger->log('find', 'users', [], 50.0);

        self::assertSame([], $logger->getSlow());
    }
}
