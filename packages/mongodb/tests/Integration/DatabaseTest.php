<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Database\Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    use RequiresMongo;

    private MongoConnection $connection;
    private Database $database;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = new MongoConfig(database: 'phpdot_test');
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $this->database = new Database($this->connection);

        // Clean up
        foreach ($this->database->listCollections() as $col) {
            $this->database->dropCollection($col['name']);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_returns_a_collection(): void
    {
        $collection = $this->database->collection('users');

        self::assertInstanceOf(Collection::class, $collection);
        self::assertSame('users', $collection->getName());
    }

    #[Test]
    public function it_creates_and_drops_collection(): void
    {
        $this->database->createCollection('test_create');

        $names = array_column($this->database->listCollections(), 'name');
        self::assertContains('test_create', $names);

        $this->database->dropCollection('test_create');

        $names = array_column($this->database->listCollections(), 'name');
        self::assertNotContains('test_create', $names);
    }

    #[Test]
    public function it_lists_collections(): void
    {
        $this->database->createCollection('col_a');
        $this->database->createCollection('col_b');

        $collections = $this->database->listCollections();

        self::assertGreaterThanOrEqual(2, count($collections));

        $names = array_column($collections, 'name');
        self::assertContains('col_a', $names);
        self::assertContains('col_b', $names);

        foreach ($collections as $col) {
            self::assertArrayHasKey('name', $col);
            self::assertArrayHasKey('type', $col);
            self::assertArrayHasKey('options', $col);
        }
    }

    #[Test]
    public function it_executes_a_command(): void
    {
        $result = $this->database->command(['ping' => 1]);

        self::assertArrayHasKey('ok', $result);
        self::assertEquals(1, $result['ok']);
    }

    #[Test]
    public function it_runs_a_transaction(): void
    {
        // Transactions require replica set — skip if single
        if (!$this->isReplicaSet()) {
            self::markTestSkipped('Transactions require replica set');
        }

        $this->database->createCollection('tx_test');

        $result = $this->database->transaction(function (Database $db, \MongoDB\Driver\Session $session): string {
            $db->collection('tx_test')->insertOne(['name' => 'Omar'], ['session' => $session]);

            return 'committed';
        });

        self::assertSame('committed', $result);

        $doc = $this->database->collection('tx_test')->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertSame('Omar', $doc->name);
    }

    #[Test]
    public function it_aborts_transaction_on_exception(): void
    {
        if (!$this->isReplicaSet()) {
            self::markTestSkipped('Transactions require replica set');
        }

        $this->database->createCollection('tx_abort');

        try {
            $this->database->transaction(function (Database $db, \MongoDB\Driver\Session $session): void {
                $db->collection('tx_abort')->insertOne(['name' => 'temp'], ['session' => $session]);
                throw new \RuntimeException('Force abort');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $doc = $this->database->collection('tx_abort')->findOne(['name' => 'temp']);
        self::assertNull($doc);
    }

    #[Test]
    public function it_returns_raw_database(): void
    {
        $raw = $this->database->raw();

        self::assertInstanceOf(\MongoDB\Database::class, $raw);
    }

    #[Test]
    public function it_shares_logger_with_collections(): void
    {
        $logger = new \PHPdot\MongoDB\Logging\QueryLogger();
        $database = new Database($this->connection, $logger);

        $database->collection('users')->countDocuments();

        self::assertGreaterThanOrEqual(1, $logger->count());
    }

    private function isReplicaSet(): bool
    {
        try {
            $result = $this->database->command(['hello' => 1]);

            return isset($result['setName']);
        } catch (\Throwable) {
            return false;
        }
    }
}
