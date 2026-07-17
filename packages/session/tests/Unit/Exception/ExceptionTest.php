<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Exception;

use PHPdot\Session\Exception\SessionException;
use PHPdot\Session\Exception\SessionExpiredException;
use PHPdot\Session\Exception\SessionReadException;
use PHPdot\Session\Exception\SessionWriteException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function sessionExceptionExtendsRuntimeException(): void
    {
        $e = new SessionException('test');

        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function sessionExceptionIsNotFinal(): void
    {
        $reflection = new \ReflectionClass(SessionException::class);

        self::assertFalse($reflection->isFinal());
    }

    #[Test]
    public function sessionExpiredExceptionExtends(): void
    {
        $e = new SessionExpiredException('expired', 'session_id_123');

        self::assertInstanceOf(SessionException::class, $e);
        self::assertSame('session_id_123', $e->getSessionId());
        self::assertSame('expired', $e->getMessage());
    }

    #[Test]
    public function sessionExpiredExceptionIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionExpiredException::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function sessionReadExceptionExtends(): void
    {
        $previous = new \RuntimeException('underlying');
        $e = new SessionReadException('read failed', 'id_456', previous: $previous);

        self::assertInstanceOf(SessionException::class, $e);
        self::assertSame('id_456', $e->getSessionId());
        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function sessionReadExceptionIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionReadException::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function sessionWriteExceptionExtends(): void
    {
        $e = new SessionWriteException('write failed', 'id_789');

        self::assertInstanceOf(SessionException::class, $e);
        self::assertSame('id_789', $e->getSessionId());
    }

    #[Test]
    public function sessionWriteExceptionIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionWriteException::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function exceptionPreservesCode(): void
    {
        $e = new SessionExpiredException('msg', 'id', 42);

        self::assertSame(42, $e->getCode());
    }
}
