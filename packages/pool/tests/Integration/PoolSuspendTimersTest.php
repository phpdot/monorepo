<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Integration;

use PHPdot\Pool\Pool;
use PHPdot\Pool\PoolConfig;
use PHPdot\Pool\Tests\Fixtures\FakeConnector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

final class PoolSuspendTimersTest extends TestCase
{
    #[Test]
    public function it_keeps_the_pool_borrowable_after_suspending_timers(): void
    {
        // suspendTimers() is the graceful worker-exit path: unlike close() it must
        // NOT flip the pool closed, so in-flight and waiting borrow() calls still
        // succeed instead of being kicked with a PoolClosedException.
        \Co\run(function (): void {
            $pool = new Pool(new FakeConnector());
            $pool->init();

            $pool->suspendTimers();

            self::assertFalse($pool->isClosed());

            $conn = $pool->borrow();
            $pool->release($conn);

            $pool->close();
        });
    }

    #[Test]
    public function it_stops_idle_cleanup_after_suspending_timers(): void
    {
        \Co\run(function (): void {
            $connector = new FakeConnector();
            $pool = new Pool($connector, new PoolConfig(
                minConnections: 1,
                maxConnections: 5,
                maxIdleTime: 0.1,
                idleCheckInterval: 0.1,
                heartbeatInterval: 0.0,
            ));
            $pool->init();

            $conns = [];
            for ($i = 0; $i < 3; $i++) {
                $conns[] = $pool->borrow();
            }
            foreach ($conns as $c) {
                $pool->release($c);
            }

            self::assertGreaterThanOrEqual(3, $pool->stats()->total);

            $pool->suspendTimers();

            // Idle timer is stopped: connections are NOT reaped past maxIdleTime.
            Coroutine::sleep(0.4);

            self::assertGreaterThanOrEqual(3, $pool->stats()->total);
            self::assertSame(0, $connector->closeCount);
            self::assertFalse($pool->isClosed());

            $pool->close();
        });
    }
}
