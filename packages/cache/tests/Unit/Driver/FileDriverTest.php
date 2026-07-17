<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit\Driver;

use PHPdot\Cache\Driver\FileDriver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileDriverTest extends TestCase
{
    private string $tempDir;
    private FileDriver $driver;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdot_cache_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
        $this->driver = new FileDriver($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
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
    public function deleteRemovesValue(): void
    {
        $this->driver->set('key', 'value');

        self::assertTrue($this->driver->delete('key'));
        self::assertNull($this->driver->get('key'));
    }

    #[Test]
    public function deleteReturnsTrueForNonExistentKey(): void
    {
        self::assertTrue($this->driver->delete('nonexistent'));
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
    public function clearRemovesAllFiles(): void
    {
        $this->driver->set('a', '1');
        $this->driver->set('b', '2');

        self::assertTrue($this->driver->clear());
        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
    }

    #[Test]
    public function ttlExpiryValueGoneAfterExpiry(): void
    {
        $this->driver->set('key', 'value', 1);

        self::assertSame('value', $this->driver->get('key'));

        sleep(2);

        self::assertNull($this->driver->get('key'));
    }

    #[Test]
    public function setWithoutTtlStoresForever(): void
    {
        $this->driver->set('key', 'value', 0);

        self::assertSame('value', $this->driver->get('key'));
    }

    #[Test]
    public function storesComplexDataTypes(): void
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
