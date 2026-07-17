<?php

declare(strict_types=1);

namespace PHPdot\ErrorHandler\Tests\Unit\Exception;

use PHPdot\ErrorHandler\Exception\FatalErrorException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FatalErrorExceptionTest extends TestCase
{
    #[Test]
    public function fromLastErrorSetsMessage(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'Call to undefined function foo()',
            'file' => '/app/src/Foo.php',
            'line' => 42,
        ]);

        self::assertSame('Call to undefined function foo()', $exception->getMessage());
    }

    #[Test]
    public function fromLastErrorSetsSeverity(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_PARSE,
            'message' => 'syntax error',
            'file' => '/app/src/Foo.php',
            'line' => 10,
        ]);

        self::assertSame(E_PARSE, $exception->getSeverity());
    }

    #[Test]
    public function fromLastErrorSetsFile(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'test',
            'file' => '/var/www/html/index.php',
            'line' => 1,
        ]);

        self::assertSame('/var/www/html/index.php', $exception->getFile());
    }

    #[Test]
    public function fromLastErrorSetsLine(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'test',
            'file' => '/app/src/Foo.php',
            'line' => 99,
        ]);

        self::assertSame(99, $exception->getLine());
    }

    #[Test]
    public function fromLastErrorSetsCodeToZero(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'test',
            'file' => '/app/src/Foo.php',
            'line' => 1,
        ]);

        self::assertSame(0, $exception->getCode());
    }

    #[Test]
    public function fromLastErrorWithECompileError(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_COMPILE_ERROR,
            'message' => 'compile error',
            'file' => '/app/src/Broken.php',
            'line' => 5,
        ]);

        self::assertSame(E_COMPILE_ERROR, $exception->getSeverity());
        self::assertSame('compile error', $exception->getMessage());
    }

    #[Test]
    public function fromLastErrorWithECoreError(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_CORE_ERROR,
            'message' => 'core error',
            'file' => '/app/src/Core.php',
            'line' => 1,
        ]);

        self::assertSame(E_CORE_ERROR, $exception->getSeverity());
    }

    #[Test]
    public function isFinal(): void
    {
        $ref = new \ReflectionClass(FatalErrorException::class);

        self::assertTrue($ref->isFinal());
    }

    #[Test]
    public function extendsErrorException(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'test',
            'file' => '/app/src/Foo.php',
            'line' => 1,
        ]);

        self::assertInstanceOf(\ErrorException::class, $exception);
    }

    #[Test]
    public function isThrowable(): void
    {
        $exception = FatalErrorException::fromLastError([
            'type' => E_ERROR,
            'message' => 'test',
            'file' => '/app/src/Foo.php',
            'line' => 1,
        ]);

        self::assertInstanceOf(\Throwable::class, $exception);
    }
}
