<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CursorIntegrationTest extends TestCase
{
    use RequiresMongo;

    private Collection $collection;
    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = self::mongoTestConfig();
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $database = new Database($this->connection);

        try {
            $database->dropCollection('cursor_test');
        } catch (\Throwable) {
        }
        $database->createCollection('cursor_test');
        $this->collection = $database->collection('cursor_test');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_iterates_with_foreach(): void
    {
        $this->collection->insertMany([
            ['name' => 'A'],
            ['name' => 'B'],
            ['name' => 'C'],
        ]);

        $cursor = $this->collection->find()->execute();
        $names = [];
        foreach ($cursor as $index => $doc) {
            self::assertIsInt($index);
            self::assertInstanceOf(Document::class, $doc);
            $names[] = $doc->name;
        }

        sort($names);
        self::assertSame(['A', 'B', 'C'], $names);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $this->collection->insertMany([
            ['name' => 'X'],
            ['name' => 'Y'],
        ]);

        $docs = $this->collection->find()->execute()->toArray();

        self::assertCount(2, $docs);
        self::assertContainsOnlyInstancesOf(Document::class, $docs);
    }

    #[Test]
    public function it_returns_first_document(): void
    {
        $this->collection->insertMany([
            ['name' => 'First', 'order' => 1],
            ['name' => 'Second', 'order' => 2],
        ]);

        $doc = $this->collection->find()
            ->sort(['order' => 1])
            ->execute()
            ->first();

        self::assertNotNull($doc);
        self::assertSame('First', $doc->name);
    }

    #[Test]
    public function it_returns_null_first_on_empty(): void
    {
        $doc = $this->collection->find()->execute()->first();

        self::assertNull($doc);
    }

    #[Test]
    public function it_lazily_iterates(): void
    {
        $this->collection->insertMany([
            ['n' => 1],
            ['n' => 2],
            ['n' => 3],
        ]);

        $generator = $this->collection->find()
            ->sort(['n' => 1])
            ->execute()
            ->lazy();

        $values = [];
        foreach ($generator as $doc) {
            $values[] = $doc->n;
        }

        self::assertSame([1, 2, 3], $values);
    }

    #[Test]
    public function it_counts_documents_via_cursor(): void
    {
        $this->collection->insertMany([
            ['a' => 1],
            ['a' => 2],
            ['a' => 3],
            ['a' => 4],
            ['a' => 5],
        ]);

        $count = $this->collection->find()->execute()->count();

        self::assertSame(5, $count);
    }

    #[Test]
    public function it_counts_zero_on_empty(): void
    {
        $count = $this->collection->find()->execute()->count();

        self::assertSame(0, $count);
    }

    #[Test]
    public function it_handles_large_result_set(): void
    {
        $docs = [];
        for ($i = 0; $i < 500; $i++) {
            $docs[] = ['index' => $i, 'data' => str_repeat('x', 100)];
        }
        $this->collection->insertMany($docs);

        $results = $this->collection->find()
            ->sort(['index' => 1])
            ->execute()
            ->toArray();

        self::assertCount(500, $results);
        self::assertSame(0, $results[0]->index);
        self::assertSame(499, $results[499]->index);
    }

    #[Test]
    public function it_handles_cursor_with_batch_size(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->collection->insertOne(['i' => $i]);
        }

        $docs = $this->collection->find()
            ->batchSize(5)
            ->execute()
            ->toArray();

        self::assertCount(50, $docs);
    }
}
