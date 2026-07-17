<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Database\Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionIndexTest extends TestCase
{
    use RequiresMongo;

    private Collection $collection;
    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = new MongoConfig(database: 'phpdot_test');
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $database = new Database($this->connection);

        try {
            $database->dropCollection('index_test');
        } catch (\Throwable) {
        }
        $database->createCollection('index_test');
        $this->collection = $database->collection('index_test');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_creates_a_single_field_index(): void
    {
        $name = $this->collection->createIndex(['email' => 1]);

        self::assertSame('email_1', $name);
    }

    #[Test]
    public function it_creates_a_compound_index(): void
    {
        $name = $this->collection->createIndex(['status' => 1, 'created_at' => -1]);

        self::assertSame('status_1_created_at_-1', $name);
    }

    #[Test]
    public function it_creates_a_unique_index(): void
    {
        $name = $this->collection->createIndex(
            ['email' => 1],
            ['unique' => true],
        );

        self::assertSame('email_1', $name);
    }

    #[Test]
    public function it_creates_a_named_index(): void
    {
        $name = $this->collection->createIndex(
            ['email' => 1],
            ['name' => 'custom_email_idx'],
        );

        self::assertSame('custom_email_idx', $name);
    }

    #[Test]
    public function it_creates_multiple_indexes(): void
    {
        $names = $this->collection->createIndexes([
            ['key' => ['name' => 1]],
            ['key' => ['age' => -1]],
            ['key' => ['status' => 1, 'name' => 1]],
        ]);

        self::assertCount(3, $names);
        self::assertSame('name_1', $names[0]);
        self::assertSame('age_-1', $names[1]);
        self::assertSame('status_1_name_1', $names[2]);
    }

    #[Test]
    public function it_lists_indexes(): void
    {
        $this->collection->createIndex(['email' => 1]);
        $this->collection->createIndex(['status' => 1]);

        $indexes = $this->collection->listIndexes();

        // At minimum: _id_ index + 2 created
        self::assertGreaterThanOrEqual(3, count($indexes));
    }

    #[Test]
    public function it_drops_an_index_by_name(): void
    {
        $this->collection->createIndex(['email' => 1]);

        $indexesBefore = count($this->collection->listIndexes());

        $this->collection->dropIndex('email_1');

        $indexesAfter = count($this->collection->listIndexes());
        self::assertSame($indexesBefore - 1, $indexesAfter);
    }

    #[Test]
    public function it_drops_all_indexes(): void
    {
        $this->collection->createIndex(['email' => 1]);
        $this->collection->createIndex(['status' => 1]);

        $this->collection->dropIndexes();

        // Only _id_ index remains
        $indexes = $this->collection->listIndexes();
        self::assertCount(1, $indexes);
    }

    #[Test]
    public function it_creates_text_index(): void
    {
        $name = $this->collection->createIndex(['content' => 'text']);

        self::assertIsString($name);
    }

    #[Test]
    public function it_creates_ttl_index(): void
    {
        $name = $this->collection->createIndex(
            ['expires_at' => 1],
            ['expireAfterSeconds' => 3600],
        );

        self::assertSame('expires_at_1', $name);
    }

    #[Test]
    public function it_creates_sparse_index(): void
    {
        $name = $this->collection->createIndex(
            ['optional_field' => 1],
            ['sparse' => true],
        );

        self::assertSame('optional_field_1', $name);
    }
}
