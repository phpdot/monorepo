<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Integration;

use PHPdot\Pool\Pool;
use PHPdot\Pool\PoolConfig;
use PHPdot\Pool\Tests\Fixtures\FakeConnector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swoole\Timer;

final class PoolSelfInitTest extends TestCase
{
    #[Test]
    public function theFirstBorrowInitialisesThePoolAndInitIsIdempotent(): void
    {
        \Co\run(function (): void {
            $connector = new FakeConnector();
            $pool = new Pool($connector, new PoolConfig(
                minConnections: 3,
                maxConnections: 5,
                idleCheckInterval: 1.0,
                maxIdleTime: 2.0,
            ));

            $connection = $pool->borrow();

            self::assertSame(3, $connector->createCount, 'minimum connections are created on the first borrow');
            self::assertSame(3, $pool->stats()->total);
            self::assertGreaterThanOrEqual(1, self::timerCount(), 'the maintenance timers start with the borrow');

            $pool->release($connection);
            $pool->init();

            self::assertSame(3, $connector->createCount, 'a second init is a no-op');
            self::assertSame(1, self::timerCount(), 'the idle timer is armed exactly once');

            $pool->close();
        });
    }

    private static function timerCount(): int
    {
        return iterator_count(Timer::list());
    }
}
