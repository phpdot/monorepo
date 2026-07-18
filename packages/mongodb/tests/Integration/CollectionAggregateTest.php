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

final class CollectionAggregateTest extends TestCase
{
    use RequiresMongo;

    private Database $database;
    private Collection $collection;
    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = self::mongoTestConfig();
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $this->database = new Database($this->connection);

        try {
            $this->database->dropCollection('agg_test');
        } catch (\Throwable) {
        }
        $this->database->createCollection('agg_test');
        $this->collection = $this->database->collection('agg_test');

        $this->collection->insertMany([
            ['category' => 'A', 'amount' => 100, 'status' => 'completed'],
            ['category' => 'A', 'amount' => 200, 'status' => 'completed'],
            ['category' => 'B', 'amount' => 150, 'status' => 'completed'],
            ['category' => 'B', 'amount' => 50, 'status' => 'pending'],
            ['category' => 'C', 'amount' => 300, 'status' => 'completed'],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_runs_raw_aggregation_pipeline(): void
    {
        $cursor = $this->collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$group' => ['_id' => '$category', 'total' => ['$sum' => '$amount']]],
            ['$sort' => ['total' => -1]],
        ]);

        self::assertInstanceOf(Cursor::class, $cursor);

        $docs = $cursor->toArray();
        self::assertCount(3, $docs);

        // A=300 (100+200) and C=300 tie for highest — both are 300
        self::assertSame(300, $docs[0]->total);
        // B=150 is last
        self::assertSame(150, $docs[2]->total);
    }

    #[Test]
    public function it_returns_documents_from_aggregation(): void
    {
        $docs = $this->collection->aggregate([
            ['$match' => ['category' => 'A']],
        ])->toArray();

        self::assertCount(2, $docs);
        foreach ($docs as $doc) {
            self::assertInstanceOf(Document::class, $doc);
            self::assertSame('A', $doc->category);
        }
    }

    #[Test]
    public function it_aggregates_with_project(): void
    {
        $docs = $this->collection->aggregate([
            ['$project' => ['category' => 1, 'doubled' => ['$multiply' => ['$amount', 2]], '_id' => 0]],
            ['$sort' => ['doubled' => 1]],
        ])->toArray();

        self::assertCount(5, $docs);
        self::assertSame(100, $docs[0]->doubled);
        self::assertSame(600, $docs[4]->doubled);
    }

    #[Test]
    public function it_aggregates_with_limit(): void
    {
        $docs = $this->collection->aggregate([
            ['$sort' => ['amount' => -1]],
            ['$limit' => 2],
        ])->toArray();

        self::assertCount(2, $docs);
        self::assertSame(300, $docs[0]->amount);
        self::assertSame(200, $docs[1]->amount);
    }

    #[Test]
    public function it_aggregates_with_skip(): void
    {
        $docs = $this->collection->aggregate([
            ['$sort' => ['amount' => 1]],
            ['$skip' => 3],
        ])->toArray();

        self::assertCount(2, $docs);
    }

    #[Test]
    public function it_aggregates_with_unwind(): void
    {
        $this->collection->insertOne([
            'category' => 'D',
            'tags' => ['fast', 'reliable'],
            'amount' => 0,
            'status' => 'completed',
        ]);

        $docs = $this->collection->aggregate([
            ['$match' => ['category' => 'D']],
            ['$unwind' => '$tags'],
        ])->toArray();

        self::assertCount(2, $docs);
    }

    #[Test]
    public function it_aggregates_with_count(): void
    {
        $docs = $this->collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$count' => 'total'],
        ])->toArray();

        self::assertCount(1, $docs);
        self::assertSame(4, $docs[0]->total);
    }

    #[Test]
    public function it_aggregates_with_add_fields(): void
    {
        $docs = $this->collection->aggregate([
            ['$addFields' => ['tax' => ['$multiply' => ['$amount', 0.1]]]],
            ['$match' => ['category' => 'C']],
        ])->toArray();

        self::assertCount(1, $docs);
        self::assertEqualsWithDelta(30.0, $docs[0]->tax, 0.01);
    }

    #[Test]
    public function it_aggregates_empty_result(): void
    {
        $docs = $this->collection->aggregate([
            ['$match' => ['category' => 'NONEXISTENT']],
        ])->toArray();

        self::assertCount(0, $docs);
    }

    #[Test]
    public function it_runs_multi_stage_pipeline(): void
    {
        $docs = $this->collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$group' => ['_id' => '$category', 'total' => ['$sum' => '$amount'], 'count' => ['$sum' => 1]]],
            ['$match' => ['count' => ['$gte' => 2]]],
            ['$project' => ['_id' => 1, 'total' => 1, 'avg' => ['$divide' => ['$total', '$count']]]],
            ['$sort' => ['avg' => -1]],
        ])->toArray();

        self::assertCount(1, $docs); // Only category A has 2+ completed
        self::assertSame('A', $docs[0]->_id);
        self::assertSame(300, $docs[0]->total);
    }
}
