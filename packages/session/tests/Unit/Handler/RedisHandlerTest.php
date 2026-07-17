<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Handler;

use PHPdot\Session\Exception\SessionReadException;
use PHPdot\Session\Exception\SessionWriteException;
use PHPdot\Session\Handler\RedisHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedisHandlerTest extends TestCase
{
    #[Test]
    public function readReturnsEmptyWhenRedisFalse(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willReturn(false);

        $handler = new RedisHandler($redis);

        self::assertSame('', $handler->read('id1'));
    }

    #[Test]
    public function readReturnsStoredString(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->with('session:id1')->willReturn('{"key":"value"}');

        $handler = new RedisHandler($redis);

        self::assertSame('{"key":"value"}', $handler->read('id1'));
    }

    #[Test]
    public function writeCallsSetexWithLifetime(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('setex')
            ->with('session:id1', 3600, 'data');

        $handler = new RedisHandler($redis);
        $handler->write('id1', 'data', 3600);
    }

    #[Test]
    public function writeCallsSetWhenLifetimeIsZero(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('set')
            ->with('session:id1', 'data');

        $handler = new RedisHandler($redis);
        $handler->write('id1', 'data', 0);
    }

    #[Test]
    public function destroyCallsDel(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('del')
            ->with('session:id1');

        $handler = new RedisHandler($redis);
        $handler->destroy('id1');
    }

    #[Test]
    public function existsReturnsTrueWhenKeyExists(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('exists')->with('session:id1')->willReturn(1);

        $handler = new RedisHandler($redis);

        self::assertTrue($handler->exists('id1'));
    }

    #[Test]
    public function existsReturnsFalseWhenKeyMissing(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('exists')->with('session:id1')->willReturn(0);

        $handler = new RedisHandler($redis);

        self::assertFalse($handler->exists('id1'));
    }

    #[Test]
    public function readWrapsRedisException(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->willThrowException(new \RedisException('Connection lost'));

        $handler = new RedisHandler($redis);

        $this->expectException(SessionReadException::class);
        $this->expectExceptionMessage('Failed to read session from Redis');

        $handler->read('id1');
    }

    #[Test]
    public function writeWrapsRedisException(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('setex')->willThrowException(new \RedisException('Connection lost'));

        $handler = new RedisHandler($redis);

        $this->expectException(SessionWriteException::class);
        $this->expectExceptionMessage('Failed to write session to Redis');

        $handler->write('id1', 'data', 3600);
    }

    #[Test]
    public function destroyWrapsRedisException(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('del')->willThrowException(new \RedisException('Connection lost'));

        $handler = new RedisHandler($redis);

        $this->expectException(SessionWriteException::class);
        $this->expectExceptionMessage('Failed to destroy session in Redis');

        $handler->destroy('id1');
    }

    #[Test]
    public function existsWrapsRedisException(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('exists')->willThrowException(new \RedisException('Connection lost'));

        $handler = new RedisHandler($redis);

        $this->expectException(SessionReadException::class);

        $handler->exists('id1');
    }

    #[Test]
    public function gcReturnsZero(): void
    {
        $redis = $this->createMock(\Redis::class);
        $handler = new RedisHandler($redis);

        self::assertSame(0, $handler->gc(3600));
    }

    #[Test]
    public function customPrefix(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects(self::once())
            ->method('get')
            ->with('myapp:id1')
            ->willReturn('data');

        $handler = new RedisHandler($redis, 'myapp:');

        self::assertSame('data', $handler->read('id1'));
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(RedisHandler::class);

        self::assertTrue($reflection->isFinal());
    }
}
