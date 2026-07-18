<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionExplainTest extends TestCase
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
            $database->dropCollection('explain_test');
        } catch (\Throwable) {
        }
        $database->createCollection('explain_test');
        $this->collection = $database->collection('explain_test');

        $this->collection->insertMany([
            ['name' => 'Omar', 'status' => 'active'],
            ['name' => 'Alice', 'status' => 'active'],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_explains_a_find_query(): void
    {
        $plan = $this->collection->explain(['status' => 'active']);

        self::assertIsArray($plan);
        self::assertNotEmpty($plan);
    }

    #[Test]
    public function it_explains_with_empty_filter(): void
    {
        $plan = $this->collection->explain();

        self::assertIsArray($plan);
        self::assertNotEmpty($plan);
    }

    #[Test]
    public function it_explains_aggregation(): void
    {
        $plan = $this->collection->explainAggregate([
            ['$match' => ['status' => 'active']],
            ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]],
        ]);

        self::assertIsArray($plan);
        self::assertNotEmpty($plan);
    }

    #[Test]
    public function it_explains_via_find_builder(): void
    {
        $plan = $this->collection->find()
            ->filter(['status' => 'active'])
            ->sort(['name' => 1])
            ->explain();

        self::assertIsArray($plan);
        self::assertNotEmpty($plan);
    }
}
