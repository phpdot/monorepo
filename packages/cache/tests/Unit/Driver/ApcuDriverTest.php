<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit\Driver;

use PHPdot\Cache\Driver\ApcuDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApcuDriverTest extends TestCase
{
    private ApcuDriver $driver;

    protected function setUp(): void
    {
        if (!extension_loaded('apcu') || !ini_get('apc.enable_cli')) {
            self::markTestSkipped('ext-apcu not available or not enabled in CLI');
        }

        apcu_clear_cache();

        $this->driver = new ApcuDriver('test:');
    }

    protected function tearDown(): void
    {
        if (extension_loaded('apcu') && ini_get('apc.enable_cli')) {
            apcu_clear_cache();
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
    public function clearRemovesAllValues(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        self::assertTrue($this->driver->clear());
        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
    }

    #[Test]
    public function prefixIsolation(): void
    {
        $otherDriver = new ApcuDriver('other:');

        $this->driver->set('key', 'from-test');
        $otherDriver->set('key', 'from-other');

        self::assertSame('from-test', $this->driver->get('key'));
        self::assertSame('from-other', $otherDriver->get('key'));
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
}
