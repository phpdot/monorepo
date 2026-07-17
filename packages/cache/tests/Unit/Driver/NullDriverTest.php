<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit\Driver;

use PHPdot\Cache\Driver\NullDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullDriverTest extends TestCase
{
    private NullDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new NullDriver();
    }

    #[Test]
    public function getReturnsNull(): void
    {
        self::assertNull($this->driver->get('anything'));
    }

    #[Test]
    public function setReturnsTrue(): void
    {
        self::assertTrue($this->driver->set('key', 'value'));
    }

    #[Test]
    public function deleteReturnsTrue(): void
    {
        self::assertTrue($this->driver->delete('key'));
    }

    #[Test]
    public function clearReturnsTrue(): void
    {
        self::assertTrue($this->driver->clear());
    }

    #[Test]
    public function hasReturnsFalse(): void
    {
        self::assertFalse($this->driver->has('anything'));
    }

    #[Test]
    public function getMultipleReturnsEmptyArray(): void
    {
        self::assertSame([], $this->driver->getMultiple(['a', 'b']));
    }

    #[Test]
    public function setMultipleReturnsTrue(): void
    {
        self::assertTrue($this->driver->setMultiple(['a' => '1', 'b' => '2']));
    }

    #[Test]
    public function deleteMultipleReturnsTrue(): void
    {
        self::assertTrue($this->driver->deleteMultiple(['a', 'b']));
    }
}
