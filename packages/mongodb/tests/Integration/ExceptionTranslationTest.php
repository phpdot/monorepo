<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Exception\DuplicateKeyException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionTranslationTest extends TestCase
{
    use RequiresMongo;

    private Collection $collection;
    private MongoConnection $connection;
    private Database $database;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = self::mongoTestConfig();
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $this->database = new Database($this->connection);

        try {
            $this->database->dropCollection('exc_test');
        } catch (\Throwable) {
        }
        $this->database->createCollection('exc_test');
        $this->collection = $this->database->collection('exc_test');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_throws_duplicate_key_exception(): void
    {
        $this->collection->createIndex(['email' => 1], ['unique' => true]);

        $this->collection->insertOne(['email' => 'omar@test.com']);

        try {
            $this->collection->insertOne(['email' => 'omar@test.com']);
            self::fail('Expected DuplicateKeyException');
        } catch (DuplicateKeyException $e) {
            self::assertSame('exc_test', $e->getCollection());
            self::assertNotEmpty($e->getDuplicateKey());
            self::assertSame(11000, $e->getCode());
        }
    }

    #[Test]
    public function it_throws_duplicate_key_on_update(): void
    {
        $this->collection->createIndex(['email' => 1], ['unique' => true]);

        $this->collection->insertOne(['email' => 'a@test.com', 'name' => 'A']);
        $this->collection->insertOne(['email' => 'b@test.com', 'name' => 'B']);

        try {
            $this->collection->updateOne()
                ->filter(['name' => 'B'])
                ->update(['$set' => ['email' => 'a@test.com']])
                ->execute();
            self::fail('Expected DuplicateKeyException');
        } catch (DuplicateKeyException $e) {
            self::assertSame('exc_test', $e->getCollection());
            self::assertSame(11000, $e->getCode());
        }
    }

    #[Test]
    public function it_throws_duplicate_key_on_bulk_write(): void
    {
        $this->collection->createIndex(['email' => 1], ['unique' => true]);

        try {
            $this->collection->bulkWrite([
                ['insertOne' => [['email' => 'dup@test.com']]],
                ['insertOne' => [['email' => 'dup@test.com']]],
            ]);
            self::fail('Expected exception');
        } catch (\PHPdot\MongoDB\Exception\BulkWriteException $e) {
            self::assertSame('exc_test', $e->getCollection());
        } catch (DuplicateKeyException $e) {
            self::assertSame('exc_test', $e->getCollection());
        }
    }

    #[Test]
    public function it_throws_validation_exception_on_schema_violation(): void
    {
        // Create a collection with a JSON schema validator
        $this->database->dropCollection('validated');
        $this->database->createCollection('validated', [
            'validator' => [
                '$jsonSchema' => [
                    'bsonType' => 'object',
                    'required' => ['name', 'email'],
                    'properties' => [
                        'name' => ['bsonType' => 'string'],
                        'email' => ['bsonType' => 'string'],
                    ],
                ],
            ],
        ]);

        $col = $this->database->collection('validated');

        try {
            // Missing required 'email' field
            $col->insertOne(['name' => 'Omar']);
            self::fail('Expected ValidationException');
        } catch (\PHPdot\MongoDB\Exception\ValidationException $e) {
            self::assertSame('validated', $e->getCollection());
            self::assertSame(121, $e->getCode());
        }
    }

    #[Test]
    public function it_preserves_original_exception_as_previous(): void
    {
        $this->collection->createIndex(['email' => 1], ['unique' => true]);
        $this->collection->insertOne(['email' => 'chain@test.com']);

        try {
            $this->collection->insertOne(['email' => 'chain@test.com']);
            self::fail('Expected DuplicateKeyException');
        } catch (DuplicateKeyException $e) {
            self::assertNotNull($e->getPrevious());
            self::assertInstanceOf(
                \MongoDB\Driver\Exception\RuntimeException::class,
                $e->getPrevious(),
            );
        }
    }

    #[Test]
    public function it_handles_successful_operations_without_exceptions(): void
    {
        // Ensure normal operations don't throw
        $this->collection->insertOne(['name' => 'Safe']);
        $doc = $this->collection->findOne(['name' => 'Safe']);
        self::assertNotNull($doc);

        $this->collection->updateOne()
            ->filter(['name' => 'Safe'])
            ->update(['$set' => ['updated' => true]])
            ->execute();

        $this->collection->deleteOne()
            ->filter(['name' => 'Safe'])
            ->execute();

        self::assertSame(0, $this->collection->countDocuments());
    }
}
