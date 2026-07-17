<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit;

use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MongoConnectionTest extends TestCase
{
    #[Test]
    public function it_starts_disconnected(): void
    {
        $connection = new MongoConnection(new MongoConfig());

        self::assertFalse($connection->isConnected());
    }

    #[Test]
    public function it_returns_config(): void
    {
        $config = new MongoConfig(database: 'mydb');
        $connection = new MongoConnection($config);

        self::assertSame($config, $connection->getConfig());
        self::assertSame('mydb', $connection->getConfig()->database);
    }

    #[Test]
    public function it_throws_ensure_connected_when_not_connected(): void
    {
        $connection = new MongoConnection(new MongoConfig());

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Not connected');
        $connection->ensureConnected();
    }

    #[Test]
    public function it_throws_get_client_when_not_connected(): void
    {
        $connection = new MongoConnection(new MongoConfig());

        $this->expectException(ConnectionException::class);
        $connection->getClient();
    }

    #[Test]
    public function it_throws_get_database_when_not_connected(): void
    {
        $connection = new MongoConnection(new MongoConfig(database: 'test'));

        $this->expectException(ConnectionException::class);
        $connection->getDatabase();
    }

    #[Test]
    public function it_returns_false_ping_when_not_connected(): void
    {
        $connection = new MongoConnection(new MongoConfig());

        self::assertFalse($connection->ping());
    }

    #[Test]
    public function it_close_is_idempotent(): void
    {
        $connection = new MongoConnection(new MongoConfig());

        // Close when not connected — should not throw
        $connection->close();
        $connection->close();

        self::assertFalse($connection->isConnected());
    }
}
