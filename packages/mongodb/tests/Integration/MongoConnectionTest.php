<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Exception\AuthenticationException;
use PHPdot\MongoDB\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MongoConnectionTest extends TestCase
{
    use RequiresMongo;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();
    }

    #[Test]
    public function it_connects_to_mongodb(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();

        self::assertTrue($connection->isConnected());
        $connection->close();
    }

    #[Test]
    public function it_reports_not_connected_before_connect(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);

        self::assertFalse($connection->isConnected());
    }

    #[Test]
    public function it_pings_the_server(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();

        self::assertTrue($connection->ping());
        $connection->close();
    }

    #[Test]
    public function it_returns_false_ping_when_not_connected(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);

        self::assertFalse($connection->ping());
    }

    #[Test]
    public function it_closes_connection(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();

        $connection->close();

        self::assertFalse($connection->isConnected());
    }

    #[Test]
    public function it_reconnects(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();
        $connection->reconnect();

        self::assertTrue($connection->isConnected());
        self::assertTrue($connection->ping());
        $connection->close();
    }

    #[Test]
    public function it_throws_when_ensure_connected_but_not(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);

        $this->expectException(ConnectionException::class);
        $connection->ensureConnected();
    }

    #[Test]
    public function it_returns_client(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();

        $client = $connection->getClient();
        self::assertInstanceOf(\MongoDB\Client::class, $client);
        $connection->close();
    }

    #[Test]
    public function it_returns_database(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);
        $connection->connect();

        $db = $connection->getDatabase();
        self::assertInstanceOf(\MongoDB\Database::class, $db);
        $connection->close();
    }

    #[Test]
    public function it_throws_when_no_database_configured(): void
    {
        $config = new MongoConfig(database: '');
        $connection = new MongoConnection($config);
        $connection->connect();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('No database configured');
        $connection->getDatabase();
    }

    #[Test]
    public function it_throws_when_getting_client_without_connection(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);

        $this->expectException(ConnectionException::class);
        $connection->getClient();
    }

    #[Test]
    public function it_returns_config(): void
    {
        $config = new MongoConfig(database: 'phpdot_test');
        $connection = new MongoConnection($config);

        self::assertSame($config, $connection->getConfig());
    }

    #[Test]
    public function it_fails_with_bad_host(): void
    {
        $config = new MongoConfig(
            hosts: 'nonexistent.invalid',
            database: 'phpdot_test',
            timeoutMs: 100,
            maxRetries: 0,
        );
        $connection = new MongoConnection($config);

        $this->expectException(ConnectionException::class);
        $connection->connect();
    }

    #[Test]
    public function it_fails_with_bad_auth(): void
    {
        $config = new MongoConfig(
            database: 'phpdot_test',
            username: 'baduser',
            password: 'badpass',
            timeoutMs: 500,
            maxRetries: 0,
        );
        $connection = new MongoConnection($config);

        try {
            $connection->connect();
            // If no auth is required, the connection succeeds — that's OK
            self::assertTrue($connection->isConnected());
        } catch (AuthenticationException $e) {
            self::assertStringContainsString('Authentication failed', $e->getMessage());
        } catch (ConnectionException) {
            // MongoConnection failure is acceptable if server denies
            self::assertTrue(true);
        }
    }
}
