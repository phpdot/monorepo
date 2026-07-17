<?php

declare(strict_types=1);

/**
 * Database wrapper providing collection access, transactions, and admin operations.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Database;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\Logging\QueryLogger;
use PHPdot\MongoDB\MongoConnection;

final class Database
{
    /**
     * Wrap a MongoDB database with its connection and optional query logger.
     *
     * @param MongoConnection $connection The MongoDB connection
     * @param QueryLogger|null $logger Optional query logger shared with collections
     */
    public function __construct(
        private readonly MongoConnection $connection,
        private readonly ?QueryLogger $logger = null,
    ) {}

    /**
     * Get a collection by name.
     *
     * @param string $name
     *
     * @return Collection
     */
    public function collection(string $name): Collection
    {
        $mongoCollection = $this->connection->getDatabase()->selectCollection($name);

        return new Collection($mongoCollection, $this->connection, $this->logger);
    }

    /**
     * Execute a callback within a transaction.
     *
     * @template T
     *
     * @param callable(self, \MongoDB\Driver\Session): T $callback
     * @param array<string, mixed> $options Transaction options
     *
     * @return T
     */
    public function transaction(callable $callback, array $options = []): mixed
    {
        $session = $this->connection->getClient()->startSession();
        $session->startTransaction($options);

        try {
            $result = $callback($this, $session);
            $session->commitTransaction();

            return $result;
        } catch (\Throwable $e) {
            $session->abortTransaction();
            throw $e;
        }
    }

    /**
     * Execute a database command.
     *
     * @param array<string, mixed> $command
     *
     * @return array<string, mixed>
     */
    public function command(array $command): array
    {
        $cursor = $this->connection->getDatabase()->command($command);

        /**
         * @var array<string, mixed> $result
         */
        $result = (array) ($cursor->toArray()[0] ?? []);

        return $result;
    }

    /**
     * Create a new collection.
     *
     * @param array<string, mixed> $options
     * @param string $name
     *
     * @return void
     */
    public function createCollection(string $name, array $options = []): void
    {
        $this->connection->getDatabase()->createCollection($name, $options);
    }

    /**
     * Drop a collection.
     *
     * @param string $name
     *
     * @return void
     */
    public function dropCollection(string $name): void
    {
        $this->connection->getDatabase()->dropCollection($name);
    }

    /**
     * List all collections in the database.
     *
     * @param array<string, mixed> $options
     *
     * @return list<array<string, mixed>>
     */
    public function listCollections(array $options = []): array
    {
        $collections = [];
        foreach ($this->connection->getDatabase()->listCollections($options) as $info) {
            $collections[] = [
                'name' => $info->getName(),
                'type' => $info->getType(),
                'options' => $info->getOptions(),
            ];
        }

        return $collections;
    }

    /**
     * Rename a collection.
     *
     * @param string $from
     * @param string $to
     *
     * @return void
     */
    public function renameCollection(string $from, string $to): void
    {
        $dbName = $this->connection->getConfig()->database;

        $this->connection->getClient()->getManager()->executeCommand(
            'admin',
            new \MongoDB\Driver\Command([
                'renameCollection' => $dbName . '.' . $from,
                'to' => $dbName . '.' . $to,
            ]),
        );
    }

    /**
     * Get the underlying MongoDB\Database. Escape hatch.
     *
     * @return \MongoDB\Database
     */
    public function raw(): \MongoDB\Database
    {
        return $this->connection->getDatabase();
    }
}
