<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Handler;

use PHPdot\Session\Handler\NullHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullHandlerTest extends TestCase
{
    private NullHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new NullHandler();
    }

    #[Test]
    public function readReturnsEmptyString(): void
    {
        self::assertSame('', $this->handler->read('any-id'));
    }

    #[Test]
    public function writeDoesNotThrow(): void
    {
        $this->handler->write('any-id', 'data', 3600);

        self::assertSame('', $this->handler->read('any-id'));
    }

    #[Test]
    public function destroyDoesNotThrow(): void
    {
        $this->handler->destroy('any-id');

        self::assertFalse($this->handler->exists('any-id'));
    }

    #[Test]
    public function existsReturnsFalse(): void
    {
        self::assertFalse($this->handler->exists('any-id'));
    }

    #[Test]
    public function gcReturnsZero(): void
    {
        self::assertSame(0, $this->handler->gc(3600));
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(NullHandler::class);

        self::assertTrue($reflection->isFinal());
    }
}
