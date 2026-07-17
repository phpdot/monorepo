<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Exception;

use PHPdot\MongoDB\Exception\AuthenticationException;
use PHPdot\MongoDB\Exception\BulkWriteException;
use PHPdot\MongoDB\Exception\ConnectionException;
use PHPdot\MongoDB\Exception\DuplicateKeyException;
use PHPdot\MongoDB\Exception\MongoException;
use PHPdot\MongoDB\Exception\QueryException;
use PHPdot\MongoDB\Exception\TimeoutException;
use PHPdot\MongoDB\Exception\ValidationException;
use PHPdot\MongoDB\Exception\WriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function all_exceptions_extend_mongo_exception(): void
    {
        $exceptions = [
            new ConnectionException(),
            new AuthenticationException(),
            new QueryException(),
            new WriteException(),
            new DuplicateKeyException(),
            new ValidationException(),
            new BulkWriteException(),
            new TimeoutException(),
        ];

        foreach ($exceptions as $e) {
            self::assertInstanceOf(MongoException::class, $e);
            self::assertInstanceOf(\RuntimeException::class, $e);
        }
    }

    #[Test]
    public function write_exception_subclasses(): void
    {
        self::assertInstanceOf(WriteException::class, new DuplicateKeyException());
        self::assertInstanceOf(WriteException::class, new ValidationException());
        self::assertInstanceOf(WriteException::class, new BulkWriteException());
    }

    #[Test]
    public function connection_exception_default_values(): void
    {
        $e = new ConnectionException();

        self::assertSame('', $e->getMessage());
        self::assertSame('', $e->getHost());
        self::assertSame(0, $e->getCode());
        self::assertNull($e->getPrevious());
    }

    #[Test]
    public function query_exception_default_values(): void
    {
        $e = new QueryException();

        self::assertSame('', $e->getOperation());
        self::assertSame('', $e->getCollection());
    }

    #[Test]
    public function write_exception_default_values(): void
    {
        $e = new WriteException();

        self::assertSame('', $e->getOperation());
        self::assertSame('', $e->getCollection());
    }

    #[Test]
    public function duplicate_key_exception_default_values(): void
    {
        $e = new DuplicateKeyException();

        self::assertSame('', $e->getDuplicateKey());
        self::assertSame('', $e->getCollection());
    }

    #[Test]
    public function timeout_exception_default_values(): void
    {
        $e = new TimeoutException();

        self::assertSame('', $e->getOperation());
        self::assertSame('', $e->getCollection());
    }

    #[Test]
    public function bulk_write_exception_partial_result(): void
    {
        $e = new BulkWriteException('failed', 'bulkWrite', 'users');

        self::assertNull($e->getPartialResult());

        // We can't easily create a BulkWriteResult without a real server,
        // so just verify the setter/getter exists and starts null.
    }

    #[Test]
    public function mongo_exception_with_previous(): void
    {
        $cause = new \RuntimeException('root');
        $e = new MongoException('wrapped', 42, $cause);

        self::assertSame('wrapped', $e->getMessage());
        self::assertSame(42, $e->getCode());
        self::assertSame($cause, $e->getPrevious());
    }

    #[Test]
    public function connection_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(ConnectionException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function authentication_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(AuthenticationException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function query_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(QueryException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function write_exception_is_not_final(): void
    {
        $reflection = new \ReflectionClass(WriteException::class);
        self::assertFalse($reflection->isFinal());
    }

    #[Test]
    public function duplicate_key_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(DuplicateKeyException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function validation_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(ValidationException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function bulk_write_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(BulkWriteException::class);
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function timeout_exception_is_final(): void
    {
        $reflection = new \ReflectionClass(TimeoutException::class);
        self::assertTrue($reflection->isFinal());
    }
}
