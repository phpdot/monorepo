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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function mongo_exception_is_base(): void
    {
        $e = new MongoException('test', 42);

        self::assertSame('test', $e->getMessage());
        self::assertSame(42, $e->getCode());
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function connection_exception_carries_host(): void
    {
        $e = new ConnectionException('failed', 'mongo.example.com', 7);

        self::assertSame('failed', $e->getMessage());
        self::assertSame('mongo.example.com', $e->getHost());
        self::assertSame(7, $e->getCode());
        self::assertInstanceOf(MongoException::class, $e);
    }

    #[Test]
    public function authentication_exception_extends_mongo(): void
    {
        $e = new AuthenticationException('auth failed', 18);

        self::assertInstanceOf(MongoException::class, $e);
        self::assertSame('auth failed', $e->getMessage());
    }

    #[Test]
    public function query_exception_carries_context(): void
    {
        $e = new QueryException('query failed', 'find', 'users', 100);

        self::assertSame('find', $e->getOperation());
        self::assertSame('users', $e->getCollection());
        self::assertSame(100, $e->getCode());
        self::assertInstanceOf(MongoException::class, $e);
    }

    #[Test]
    public function write_exception_carries_context(): void
    {
        $e = new WriteException('write failed', 'insertOne', 'users', 200);

        self::assertSame('insertOne', $e->getOperation());
        self::assertSame('users', $e->getCollection());
        self::assertInstanceOf(MongoException::class, $e);
    }

    #[Test]
    public function duplicate_key_exception_carries_key_name(): void
    {
        $e = new DuplicateKeyException('dup key', 'users', 'email_1', 11000);

        self::assertSame('users', $e->getCollection());
        self::assertSame('email_1', $e->getDuplicateKey());
        self::assertSame(11000, $e->getCode());
        self::assertInstanceOf(WriteException::class, $e);
    }

    #[Test]
    public function validation_exception_extends_write(): void
    {
        $e = new ValidationException('validation failed', 'insertOne', 'users', 121);

        self::assertInstanceOf(WriteException::class, $e);
        self::assertSame('users', $e->getCollection());
    }

    #[Test]
    public function bulk_write_exception_carries_partial_result(): void
    {
        $e = new BulkWriteException('bulk failed', 'bulkWrite', 'users');

        self::assertNull($e->getPartialResult());
        self::assertInstanceOf(WriteException::class, $e);
    }

    #[Test]
    public function timeout_exception_carries_context(): void
    {
        $e = new TimeoutException("Operation 'find' timed out on 'users'", 'find', 'users', 50);

        self::assertSame('find', $e->getOperation());
        self::assertSame('users', $e->getCollection());
        self::assertSame(50, $e->getCode());
        self::assertInstanceOf(MongoException::class, $e);
    }

    #[Test]
    public function exceptions_chain_previous(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new ConnectionException('failed', 'localhost', 0, $previous);

        self::assertSame($previous, $e->getPrevious());
    }
}
