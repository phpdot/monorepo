<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit;

use PHPdot\Cache\Driver\ArrayDriver;
use PHPdot\Cache\Exception\InvalidArgumentException;
use PHPdot\Cache\Store;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoreTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store(new ArrayDriver());
    }

    #[Test]
    public function getReturnsDefaultWhenKeyDoesNotExist(): void
    {
        self::assertNull($this->store->get('nonexistent'));
        self::assertSame('fallback', $this->store->get('nonexistent', 'fallback'));
    }

    #[Test]
    public function getReturnsValueAfterSet(): void
    {
        $this->store->set('key', 'value');

        self::assertSame('value', $this->store->get('key'));
    }

    #[Test]
    public function setStoresValue(): void
    {
        $result = $this->store->set('key', 'value');

        self::assertTrue($result);
        self::assertSame('value', $this->store->get('key'));
    }

    #[Test]
    public function setWithTtlStoresValueThatExpires(): void
    {
        $this->store->set('key', 'value', 1);

        self::assertSame('value', $this->store->get('key'));

        sleep(2);

        self::assertNull($this->store->get('key'));
    }

    #[Test]
    public function setWithNegativeTtlDeletesKeyAndReturnsTrue(): void
    {
        $this->store->set('key', 'value');

        $result = $this->store->set('key', 'updated', -1);

        self::assertTrue($result);
        self::assertNull($this->store->get('key'));
    }

    #[Test]
    public function setWithDateIntervalTtlWorks(): void
    {
        $interval = new \DateInterval('PT60S');

        $result = $this->store->set('key', 'value', $interval);

        self::assertTrue($result);
        self::assertSame('value', $this->store->get('key'));
    }

    #[Test]
    public function setWithNullTtlStoresForever(): void
    {
        $result = $this->store->set('key', 'value', null);

        self::assertTrue($result);
        self::assertSame('value', $this->store->get('key'));
    }

    #[Test]
    public function deleteRemovesValue(): void
    {
        $this->store->set('key', 'value');

        $result = $this->store->delete('key');

        self::assertTrue($result);
        self::assertNull($this->store->get('key'));
    }

    #[Test]
    public function deleteReturnsTrueForNonExistentKey(): void
    {
        self::assertTrue($this->store->delete('nonexistent'));
    }

    #[Test]
    public function clearRemovesAllValues(): void
    {
        $this->store->set('a', '1');
        $this->store->set('b', '2');

        $result = $this->store->clear();

        self::assertTrue($result);
        self::assertNull($this->store->get('a'));
        self::assertNull($this->store->get('b'));
    }

    #[Test]
    public function hasReturnsFalseForNonExistentKey(): void
    {
        self::assertFalse($this->store->has('nonexistent'));
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $this->store->set('key', 'value');

        self::assertTrue($this->store->has('key'));
    }

    #[Test]
    public function getMultipleReturnsValuesWithDefaultsForMissing(): void
    {
        $this->store->set('a', '1');
        $this->store->set('b', '2');

        $result = $this->store->getMultiple(['a', 'b', 'c'], 'default');

        self::assertSame('1', $result['a']);
        self::assertSame('2', $result['b']);
        self::assertSame('default', $result['c']);
    }

    #[Test]
    public function setMultipleStoresMultipleValues(): void
    {
        $result = $this->store->setMultiple(['a' => '1', 'b' => '2']);

        self::assertTrue($result);
        self::assertSame('1', $this->store->get('a'));
        self::assertSame('2', $this->store->get('b'));
    }

    #[Test]
    public function deleteMultipleRemovesMultipleValues(): void
    {
        $this->store->set('a', '1');
        $this->store->set('b', '2');
        $this->store->set('c', '3');

        $result = $this->store->deleteMultiple(['a', 'b']);

        self::assertTrue($result);
        self::assertNull($this->store->get('a'));
        self::assertNull($this->store->get('b'));
        self::assertSame('3', $this->store->get('c'));
    }

    #[Test]
    public function rememberCallsCallbackOnMissAndCachesResult(): void
    {
        $callCount = 0;
        $result = $this->store->remember('key', 60, function () use (&$callCount): string {
            $callCount++;

            return 'computed';
        });

        self::assertSame('computed', $result);
        self::assertSame(1, $callCount);
        self::assertSame('computed', $this->store->get('key'));
    }

    #[Test]
    public function rememberReturnsCachedValueOnHit(): void
    {
        $this->store->set('key', 'cached');

        $callCount = 0;
        $result = $this->store->remember('key', 60, function () use (&$callCount): string {
            $callCount++;

            return 'computed';
        });

        self::assertSame('cached', $result);
        self::assertSame(0, $callCount);
    }

    #[Test]
    public function rememberForeverCachesForever(): void
    {
        $result = $this->store->rememberForever('key', fn(): string => 'forever');

        self::assertSame('forever', $result);
        self::assertSame('forever', $this->store->get('key'));
    }

    #[Test]
    public function emptyKeyThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('');
    }

    #[Test]
    public function keyWithLeftBraceThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key{bad');
    }

    #[Test]
    public function keyWithRightBraceThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key}bad');
    }

    #[Test]
    public function keyWithLeftParenThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key(bad');
    }

    #[Test]
    public function keyWithRightParenThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key)bad');
    }

    #[Test]
    public function keyWithSlashThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key/bad');
    }

    #[Test]
    public function keyWithBackslashThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key\\bad');
    }

    #[Test]
    public function keyWithAtThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key@bad');
    }

    #[Test]
    public function keyWithColonThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->get('key:bad');
    }

    #[Test]
    public function validKeyWorks(): void
    {
        $this->store->set('alpha-numeric_123.key', 'value');

        self::assertSame('value', $this->store->get('alpha-numeric_123.key'));
    }

    #[Test]
    public function getMultipleValidatesAllKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->getMultiple(['valid', 'in{valid']);
    }

    #[Test]
    public function setMultipleValidatesAllKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->setMultiple(['valid' => '1', 'in{valid' => '2']);
    }

    #[Test]
    public function deleteMultipleValidatesAllKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store->deleteMultiple(['valid', 'in{valid']);
    }
}
