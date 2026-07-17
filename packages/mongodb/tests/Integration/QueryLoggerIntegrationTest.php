<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Logging\QueryLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryLoggerIntegrationTest extends TestCase
{
    use RequiresMongo;

    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = new MongoConfig(database: 'phpdot_test');
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_logs_find_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->findOne(['status' => 'active']);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('findOne', $logs[0]->operation);
        self::assertSame('log_test', $logs[0]->collection);
        self::assertSame(['status' => 'active'], $logs[0]->filter);
        self::assertGreaterThan(0, $logs[0]->durationMs);
    }

    #[Test]
    public function it_logs_insert_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'test']);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('insertOne', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_update_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'test']);
        $logger->clear();

        $col->updateOne()
            ->filter(['name' => 'test'])
            ->update(['$set' => ['name' => 'updated']])
            ->execute();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('updateOne', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_delete_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'test']);
        $logger->clear();

        $col->deleteOne()
            ->filter(['name' => 'test'])
            ->execute();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('deleteOne', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_count_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->countDocuments(['status' => 'active']);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('countDocuments', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_distinct_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->distinct('status');

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('distinct', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_aggregate_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->aggregate([['$match' => ['status' => 'active']]])->toArray();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('aggregate', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_find_builder_queries(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->find()->filter(['name' => 'test'])->execute()->toArray();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('find', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_count_via_builder(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->find()->filter(['name' => 'test'])->count();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('countDocuments', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_multiple_operations(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'a']);
        $col->insertOne(['name' => 'b']);
        $col->findOne(['name' => 'a']);
        $col->countDocuments();

        self::assertSame(4, $logger->count());
    }

    #[Test]
    public function it_tracks_slow_queries(): void
    {
        $logger = new QueryLogger(slowThresholdMs: 0.001); // very low threshold
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->countDocuments();

        $slow = $logger->getSlow();
        // Most queries should exceed 0.001ms
        self::assertGreaterThanOrEqual(1, count($slow));
    }

    #[Test]
    public function it_logs_estimated_count(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->estimatedDocumentCount();

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('estimatedDocumentCount', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_find_one_and_update(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'test']);
        $logger->clear();

        $col->findOneAndUpdate(['name' => 'test'], ['$set' => ['age' => 1]]);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('findOneAndUpdate', $logs[0]->operation);
    }

    #[Test]
    public function it_logs_find_one_and_delete(): void
    {
        $logger = new QueryLogger();
        $db = new Database($this->connection, $logger);
        $col = $db->collection('log_test');

        $col->insertOne(['name' => 'test']);
        $logger->clear();

        $col->findOneAndDelete(['name' => 'test']);

        $logs = $logger->getAll();
        self::assertCount(1, $logs);
        self::assertSame('findOneAndDelete', $logs[0]->operation);
    }
}
