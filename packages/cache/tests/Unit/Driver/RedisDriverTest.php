<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit\Driver;

use PHPdot\Cache\Driver\RedisDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedisDriverTest extends TestCase
{
    private \Redis $redis;
    private RedisDriver $driver;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis not available');
        }

        $this->redis = new \Redis();

        try {
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->select(15);
            $this->redis->flushDb();
        } catch (\RedisException) {
            self::markTestSkipped('Redis server not available');
        }

        $this->driver = new RedisDriver($this->redis, 'test:');
    }

    protected function tearDown(): void
    {
        if (isset($this->redis) && $this->redis->isConnected()) {
            $this->redis->flushDb();
        }
    }

    #[Test]
    public function getReturnsNullForNonExistentKey(): void
    {
        self::assertNull($this->driver->get('nonexistent'));
    }

    #[Test]
    public function setAndGetRoundTrip(): void
    {
        self::assertTrue($this->driver->set('key', 'value'));
        self::assertSame('value', $this->driver->get('key'));
    }

    #[Test]
    public function deleteRemovesKey(): void
    {
        $this->driver->set('key', 'value');

        self::assertTrue($this->driver->delete('key'));
        self::assertNull($this->driver->get('key'));
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $this->driver->set('key', 'value');

        self::assertTrue($this->driver->has('key'));
    }

    #[Test]
    public function hasReturnsFalseForNonExistentKey(): void
    {
        self::assertFalse($this->driver->has('nonexistent'));
    }

    #[Test]
    public function ttlExpiry(): void
    {
        $this->driver->set('key', 'value', 1);

        self::assertSame('value', $this->driver->get('key'));

        sleep(2);

        self::assertNull($this->driver->get('key'));
    }

    #[Test]
    public function prefixIsolation(): void
    {
        $otherDriver = new RedisDriver($this->redis, 'other:');

        $this->driver->set('key', 'from-test');
        $otherDriver->set('key', 'from-other');

        self::assertSame('from-test', $this->driver->get('key'));
        self::assertSame('from-other', $otherDriver->get('key'));
    }

    #[Test]
    public function clearWithPrefixOnlyClearsPrefixedKeys(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        $this->redis->set('unprefixed', 'value');

        self::assertTrue($this->driver->clear());
        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
        self::assertSame('value', $this->redis->get('unprefixed'));
    }

    #[Test]
    public function getMultipleWorks(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        $result = $this->driver->getMultiple(['a', 'b', 'c']);

        self::assertSame('1', $result['a']);
        self::assertSame('2', $result['b']);
        self::assertArrayNotHasKey('c', $result);
    }

    #[Test]
    public function setMultipleWorks(): void
    {
        self::assertTrue($this->driver->setMultiple(['a' => '1', 'b' => '2']));

        self::assertSame('1', $this->driver->get('a'));
        self::assertSame('2', $this->driver->get('b'));
    }

    #[Test]
    public function deleteMultipleWorks(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');
        $this->driver->set('c', '3');

        self::assertTrue($this->driver->deleteMultiple(['a', 'b']));
        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
        self::assertSame('3', $this->driver->get('c'));
    }

    #[Test]
    public function storesComplexData(): void
    {
        $array = ['nested' => ['data' => [1, 2, 3]]];
        $this->driver->set('array', $array);
        self::assertSame($array, $this->driver->get('array'));

        $obj = new \stdClass();
        $obj->name = 'test';
        $obj->items = [1, 2, 3];
        $this->driver->set('object', $obj);
        self::assertEquals($obj, $this->driver->get('object'));
    }
}
