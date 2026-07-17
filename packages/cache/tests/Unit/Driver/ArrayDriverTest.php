<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit\Driver;

use PHPdot\Cache\Driver\ArrayDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayDriverTest extends TestCase
{
    private ArrayDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new ArrayDriver();
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
    public function clearRemovesEverything(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        self::assertTrue($this->driver->clear());
        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
    }

    #[Test]
    public function ttlExpiryValueExistsImmediately(): void
    {
        $this->driver->set('key', 'value', 1);

        self::assertSame('value', $this->driver->get('key'));
    }

    #[Test]
    public function ttlExpiryValueGoneAfterExpiry(): void
    {
        $this->driver->set('key', 'value', 1);

        sleep(2);

        self::assertNull($this->driver->get('key'));
    }

    #[Test]
    public function hasReturnsFalseForExpiredKey(): void
    {
        $this->driver->set('key', 'value', 1);

        sleep(2);

        self::assertFalse($this->driver->has('key'));
    }

    #[Test]
    public function lruEvictionWhenMaxItemsReached(): void
    {
        $driver = new ArrayDriver(maxItems: 3);

        $driver->set('a', '1');
        $driver->set('b', '2');
        $driver->set('c', '3');
        $driver->set('d', '4');

        self::assertNull($driver->get('a'));
        self::assertSame('2', $driver->get('b'));
        self::assertSame('3', $driver->get('c'));
        self::assertSame('4', $driver->get('d'));
    }

    #[Test]
    public function lruEvictionZeroMeansUnlimited(): void
    {
        $driver = new ArrayDriver(maxItems: 0);

        for ($i = 0; $i < 100; $i++) {
            $driver->set("key{$i}", "value{$i}");
        }

        self::assertSame('value0', $driver->get('key0'));
        self::assertSame('value99', $driver->get('key99'));
    }

    #[Test]
    public function getMultipleReturnsOnlyExistingKeys(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        $result = $this->driver->getMultiple(['a', 'b', 'c']);

        self::assertSame('1', $result['a']);
        self::assertSame('2', $result['b']);
        self::assertArrayNotHasKey('c', $result);
    }

    #[Test]
    public function hasReturnsTrueForKeyStoredAsNull(): void
    {
        $this->driver->set('nullable', null);

        self::assertTrue($this->driver->has('nullable'));
    }

    #[Test]
    public function getMultiplePreservesStoredNullValues(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', null);

        $result = $this->driver->getMultiple(['a', 'b', 'missing']);

        self::assertSame('1', $result['a']);
        self::assertArrayHasKey('b', $result);
        self::assertNull($result['b']);
        self::assertArrayNotHasKey('missing', $result);
    }

    #[Test]
    public function setMultipleStoresAllValues(): void
    {
        self::assertTrue($this->driver->setMultiple(['a' => '1', 'b' => '2']));

        self::assertSame('1', $this->driver->get('a'));
        self::assertSame('2', $this->driver->get('b'));
    }

    #[Test]
    public function deleteMultipleRemovesSpecifiedKeys(): void
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
    public function storesMixedTypes(): void
    {
        $this->driver->set('string', 'hello');
        $this->driver->set('int', 42);
        $this->driver->set('array', [1, 2, 3]);
        $obj = new \stdClass();
        $obj->name = 'test';
        $this->driver->set('object', $obj);
        $this->driver->set('null', null);

        self::assertSame('hello', $this->driver->get('string'));
        self::assertSame(42, $this->driver->get('int'));
        self::assertSame([1, 2, 3], $this->driver->get('array'));
        self::assertEquals($obj, $this->driver->get('object'));
        self::assertNull($this->driver->get('null'));
    }

    #[Test]
    public function deleteReturnsTrueForNonExistentKey(): void
    {
        self::assertTrue($this->driver->delete('nonexistent'));
    }

    #[Test]
    public function setWithoutTtlStoresForever(): void
    {
        $this->driver->set('key', 'value', 0);

        self::assertSame('value', $this->driver->get('key'));
    }

    #[Test]
    public function lruEvictionPromotesAccessedItems(): void
    {
        $driver = new ArrayDriver(maxItems: 3);

        $driver->set('a', 1);
        $driver->set('b', 2);
        $driver->set('c', 3);

        // Access 'a' to promote it to most recently used
        $driver->get('a');

        // Adding 'd' should evict 'b' (least recently used), not 'a'
        $driver->set('d', 4);

        self::assertNull($driver->get('b'));
        self::assertSame(1, $driver->get('a'));
        self::assertSame(3, $driver->get('c'));
        self::assertSame(4, $driver->get('d'));
    }
}
