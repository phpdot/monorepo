<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Handler;

use PHPdot\Session\Handler\ArrayHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayHandlerTest extends TestCase
{
    private ArrayHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ArrayHandler();
    }

    #[Test]
    public function readReturnsEmptyForNonexistent(): void
    {
        self::assertSame('', $this->handler->read('nonexistent'));
    }

    #[Test]
    public function writeAndReadRoundTrip(): void
    {
        $this->handler->write('id1', '{"key":"value"}', 3600);

        self::assertSame('{"key":"value"}', $this->handler->read('id1'));
    }

    #[Test]
    public function destroyRemovesSession(): void
    {
        $this->handler->write('id1', 'data', 3600);

        $this->handler->destroy('id1');

        self::assertSame('', $this->handler->read('id1'));
    }

    #[Test]
    public function destroyNonexistentIsSafe(): void
    {
        $this->handler->destroy('nonexistent');

        self::assertSame('', $this->handler->read('nonexistent'));
    }

    #[Test]
    public function existsReturnsFalseForNonexistent(): void
    {
        self::assertFalse($this->handler->exists('nonexistent'));
    }

    #[Test]
    public function existsReturnsTrueForExisting(): void
    {
        $this->handler->write('id1', 'data', 3600);

        self::assertTrue($this->handler->exists('id1'));
    }

    #[Test]
    public function overwriteExistingSession(): void
    {
        $this->handler->write('id1', 'old', 3600);
        $this->handler->write('id1', 'new', 3600);

        self::assertSame('new', $this->handler->read('id1'));
    }

    #[Test]
    public function expiredSessionReturnsEmpty(): void
    {
        $this->handler->write('id1', 'data', 3600);

        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);
        $data['id1']['expiry'] = time() - 1;
        $storage->setValue($this->handler, $data);

        self::assertSame('', $this->handler->read('id1'));
    }

    #[Test]
    public function existsReturnsFalseForExpired(): void
    {
        $this->handler->write('id1', 'data', 3600);

        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);
        $data['id1']['expiry'] = time() - 1;
        $storage->setValue($this->handler, $data);

        self::assertFalse($this->handler->exists('id1'));
    }

    #[Test]
    public function gcRemovesExpiredSessions(): void
    {
        $this->handler->write('fresh', 'data', 3600);
        $this->handler->write('expired', 'data', 3600);

        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);
        $data['expired']['expiry'] = time() - 1;
        $storage->setValue($this->handler, $data);

        $count = $this->handler->gc(3600);

        self::assertSame(1, $count);
        self::assertTrue($this->handler->exists('fresh'));
        self::assertFalse($this->handler->exists('expired'));
    }

    #[Test]
    public function gcReturnsCorrectCount(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->handler->write("expired_{$i}", 'data', 3600);
        }

        $reflection = new \ReflectionClass($this->handler);
        $storage = $reflection->getProperty('storage');
        $data = $storage->getValue($this->handler);

        foreach ($data as $id => &$entry) {
            $entry['expiry'] = time() - 1;
        }
        unset($entry);

        $storage->setValue($this->handler, $data);

        self::assertSame(5, $this->handler->gc(3600));
    }

    #[Test]
    public function gcDoesNotRemoveNonExpired(): void
    {
        $this->handler->write('active', 'data', 3600);

        self::assertSame(0, $this->handler->gc(3600));
        self::assertTrue($this->handler->exists('active'));
    }

    #[Test]
    public function multipleSessions(): void
    {
        $this->handler->write('a', 'data_a', 3600);
        $this->handler->write('b', 'data_b', 3600);

        self::assertSame('data_a', $this->handler->read('a'));
        self::assertSame('data_b', $this->handler->read('b'));
    }

    #[Test]
    public function writeWithZeroLifetimeNeverExpires(): void
    {
        $this->handler->write('permanent', 'data', 0);

        self::assertTrue($this->handler->exists('permanent'));
        self::assertSame('data', $this->handler->read('permanent'));
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(ArrayHandler::class);

        self::assertTrue($reflection->isFinal());
    }
}
