<?php

declare(strict_types=1);

/**
 * Entry point for all collection operations.
 *
 * Every operation goes through runWithReconnect for resilience,
 * exception translation for typed errors, and query logging.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Collection;

use MongoDB\Builder\Pipeline;
use MongoDB\BulkWriteResult;
use MongoDB\ChangeStream;
use MongoDB\DeleteResult;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\Document\Document;
use PHPdot\MongoDB\Exception\BulkWriteException;
use PHPdot\MongoDB\Exception\DuplicateKeyException;
use PHPdot\MongoDB\Exception\MongoException;
use PHPdot\MongoDB\Exception\QueryException;
use PHPdot\MongoDB\Exception\TimeoutException;
use PHPdot\MongoDB\Exception\ValidationException;
use PHPdot\MongoDB\Exception\WriteException;
use PHPdot\MongoDB\Filter\Filter;
use PHPdot\MongoDB\Logging\QueryLogger;
use PHPdot\MongoDB\MongoConnection;

final class Collection
{
    /**
     * Wrap a MongoDB collection with its connection and optional query logger.
     *
     * @param \MongoDB\Collection $collection The underlying MongoDB collection
     * @param MongoConnection $connection MongoConnection for reconnect capability
     * @param QueryLogger|null $logger Optional query logger
     */
    public function __construct(
        private \MongoDB\Collection $collection,
        private readonly MongoConnection $connection,
        private readonly ?QueryLogger $logger = null,
    ) {}

    /**
     * Start building a find query.
     *
     * @return FindQuery
     */
    public function find(): FindQuery
    {
        return new FindQuery($this);
    }

    /**
     * Start building an updateOne query.
     *
     * @return UpdateQuery
     */
    public function updateOne(): UpdateQuery
    {
        return new UpdateQuery($this, many: false);
    }

    /**
     * Start building an updateMany query.
     *
     * @return UpdateQuery
     */
    public function updateMany(): UpdateQuery
    {
        return new UpdateQuery($this, many: true);
    }

    /**
     * Start building a deleteOne query.
     *
     * @return DeleteQuery
     */
    public function deleteOne(): DeleteQuery
    {
        return new DeleteQuery($this, many: false);
    }

    /**
     * Start building a deleteMany query.
     *
     * @return DeleteQuery
     */
    public function deleteMany(): DeleteQuery
    {
        return new DeleteQuery($this, many: true);
    }

    /**
     * Find a single document by filter.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return ?Document
     */
    public function findOne(array $filter = [], array $options = []): ?Document
    {
        /**
         * @var array<string, mixed>|null $result
         */
        $result = $this->executeRead('findOne', $filter, function () use ($filter, $options): mixed {
            return $this->collection->findOne($filter, $options);
        });

        if ($result === null) {
            return null;
        }

        return Document::fromBSON($result);
    }

    /**
     * Execute a find query with the given filter and options. Used by FindQuery.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return Cursor
     */
    public function executeFindQuery(array $filter, array $options): Cursor
    {
        $result = $this->executeRead('find', $filter, function () use ($filter, $options): \MongoDB\Driver\CursorInterface {
            return $this->collection->find($filter, $options);
        });

        return new Cursor($result);
    }

    /**
     * Execute a count query with the given filter and options. Used by FindQuery.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return int
     */
    public function executeCountQuery(array $filter, array $options): int
    {
        return $this->executeRead('countDocuments', $filter, function () use ($filter, $options): int {
            return $this->collection->countDocuments($filter, $options);
        });
    }

    /**
     * Execute an explain for a find query. Used by FindQuery.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function executeFindExplain(array $filter, array $options): array
    {
        return $this->explain($filter, $options);
    }

    /**
     * Execute an update query. Used by UpdateQuery.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     * @param bool $many
     *
     * @return UpdateResult
     */
    public function executeUpdateQuery(array $filter, array $update, array $options, bool $many): UpdateResult
    {
        $operation = $many ? 'updateMany' : 'updateOne';

        return $this->executeWrite($operation, $filter, function () use ($filter, $update, $options, $many): UpdateResult {
            if ($many) {
                return $this->collection->updateMany($filter, $update, $options);
            }

            return $this->collection->updateOne($filter, $update, $options);
        });
    }

    /**
     * Execute a delete query. Used by DeleteQuery.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @param bool $many
     *
     * @return DeleteResult
     */
    public function executeDeleteQuery(array $filter, array $options, bool $many): DeleteResult
    {
        $operation = $many ? 'deleteMany' : 'deleteOne';

        return $this->executeWrite($operation, $filter, function () use ($filter, $options, $many): DeleteResult {
            if ($many) {
                return $this->collection->deleteMany($filter, $options);
            }

            return $this->collection->deleteOne($filter, $options);
        });
    }

    /**
     * Insert a single document.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     *
     * @return InsertOneResult
     */
    public function insertOne(array $document, array $options = []): InsertOneResult
    {
        return $this->executeWrite('insertOne', [], function () use ($document, $options): InsertOneResult {
            return $this->collection->insertOne($document, $options);
        });
    }

    /**
     * Insert multiple documents.
     *
     * @param list<array<string, mixed>> $documents
     * @param array<string, mixed> $options
     *
     * @return InsertManyResult
     */
    public function insertMany(array $documents, array $options = []): InsertManyResult
    {
        return $this->executeWrite('insertMany', [], function () use ($documents, $options): InsertManyResult {
            return $this->collection->insertMany($documents, $options);
        });
    }

    /**
     * Replace a single document.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     *
     * @return UpdateResult
     */
    public function replaceOne(array $filter, array $replacement, array $options = []): UpdateResult
    {
        return $this->executeWrite('replaceOne', $filter, function () use ($filter, $replacement, $options): UpdateResult {
            return $this->collection->replaceOne($filter, $replacement, $options);
        });
    }

    /**
     * Count documents matching the filter.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return int
     */
    public function countDocuments(array $filter = [], array $options = []): int
    {
        return $this->executeRead('countDocuments', $filter, function () use ($filter, $options): int {
            return $this->collection->countDocuments($filter, $options);
        });
    }

    /**
     * Estimated document count (fast, metadata-based).
     *
     * @param array<string, mixed> $options
     *
     * @return int
     */
    public function estimatedDocumentCount(array $options = []): int
    {
        return $this->executeRead('estimatedDocumentCount', [], function () use ($options): int {
            return $this->collection->estimatedDocumentCount($options);
        });
    }

    /**
     * Distinct values for a field.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     * @param string $fieldName
     *
     * @return list<mixed>
     */
    public function distinct(string $fieldName, array $filter = [], array $options = []): array
    {
        return array_values($this->executeRead('distinct', $filter, function () use ($fieldName, $filter, $options): array {
            return $this->collection->distinct($fieldName, $filter, $options);
        }));
    }

    /**
     * Find and update atomically.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $update
     * @param array<string, mixed> $options
     *
     * @return ?Document
     */
    public function findOneAndUpdate(array $filter, array $update, array $options = []): ?Document
    {
        /**
         * @var array<string, mixed>|null $result
         */
        $result = $this->executeWrite('findOneAndUpdate', $filter, function () use ($filter, $update, $options): mixed {
            return $this->collection->findOneAndUpdate($filter, $update, $options);
        });

        if ($result === null) {
            return null;
        }

        return Document::fromBSON($result);
    }

    /**
     * Find and replace atomically.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $options
     *
     * @return ?Document
     */
    public function findOneAndReplace(array $filter, array $replacement, array $options = []): ?Document
    {
        /**
         * @var array<string, mixed>|null $result
         */
        $result = $this->executeWrite('findOneAndReplace', $filter, function () use ($filter, $replacement, $options): mixed {
            return $this->collection->findOneAndReplace($filter, $replacement, $options);
        });

        if ($result === null) {
            return null;
        }

        return Document::fromBSON($result);
    }

    /**
     * Find and delete atomically.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return ?Document
     */
    public function findOneAndDelete(array $filter, array $options = []): ?Document
    {
        /**
         * @var array<string, mixed>|null $result
         */
        $result = $this->executeWrite('findOneAndDelete', $filter, function () use ($filter, $options): mixed {
            return $this->collection->findOneAndDelete($filter, $options);
        });

        if ($result === null) {
            return null;
        }

        return Document::fromBSON($result);
    }

    /**
     * Execute multiple write operations.
     *
     * @param list<array<string, mixed>> $operations
     * @param array<string, mixed> $options
     *
     * @return BulkWriteResult
     */
    public function bulkWrite(array $operations, array $options = []): BulkWriteResult
    {
        return $this->executeWrite('bulkWrite', [], function () use ($operations, $options): BulkWriteResult {
            return $this->collection->bulkWrite($operations, $options);
        });
    }

    /**
     * Execute an aggregation pipeline.
     *
     * @param Pipeline|list<array<string, mixed>> $pipeline
     * @param array<string, mixed> $options
     *
     * @return Cursor
     */
    public function aggregate(Pipeline|array $pipeline, array $options = []): Cursor
    {
        $result = $this->executeRead('aggregate', [], function () use ($pipeline, $options): \MongoDB\Driver\CursorInterface {
            return $this->collection->aggregate($pipeline, $options);
        });

        return new Cursor($result);
    }

    /**
     * Create an index.
     *
     * @param array<string, int|string> $keys
     * @param array<string, mixed> $options
     *
     * @return string
     */
    public function createIndex(array $keys, array $options = []): string
    {
        return $this->runWithReconnect(function () use ($keys, $options): string {
            return $this->collection->createIndex($keys, $options);
        });
    }

    /**
     * Create multiple indexes.
     *
     * @param list<array<string, mixed>> $indexes
     *
     * @return list<string>
     */
    public function createIndexes(array $indexes): array
    {
        return array_values($this->runWithReconnect(function () use ($indexes): array {
            return $this->collection->createIndexes($indexes);
        }));
    }

    /**
     * Drop an index by name.
     *
     * @param string $name
     *
     * @return void
     */
    public function dropIndex(string $name): void
    {
        $this->runWithReconnect(function () use ($name): void {
            $this->collection->dropIndex($name);
        });
    }

    /**
     * Drop all indexes on the collection.
     *
     * @return void
     */
    public function dropIndexes(): void
    {
        $this->runWithReconnect(function (): void {
            $this->collection->dropIndexes();
        });
    }

    /**
     * List all indexes on the collection.
     *
     * @return list<array<string, mixed>>
     */
    public function listIndexes(): array
    {
        return $this->runWithReconnect(function (): array {
            $indexes = [];
            foreach ($this->collection->listIndexes() as $index) {
                /**
                 * @var array<string, mixed> $indexArray
                 */
                $indexArray = (array) $index;
                $indexes[] = $indexArray;
            }

            return $indexes;
        });
    }

    /**
     * Watch for changes on the collection.
     *
     * @param list<array<string, mixed>> $pipeline
     * @param array<string, mixed> $options
     *
     * @return ChangeStream
     */
    public function watch(array $pipeline = [], array $options = []): ChangeStream
    {
        return $this->runWithReconnect(function () use ($pipeline, $options): ChangeStream {
            return $this->collection->watch($pipeline, $options);
        });
    }

    /**
     * Explain a find query.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function explain(array $filter = [], array $options = []): array
    {
        return $this->runWithReconnect(function () use ($filter, $options): array {
            $command = [
                'explain' => [
                    'find' => $this->collection->getCollectionName(),
                    'filter' => (object) $filter,
                    ...$options,
                ],
            ];

            $response = $this->collection->getManager()
                ->executeCommand(
                    $this->collection->getDatabaseName(),
                    new \MongoDB\Driver\Command($command),
                )->toArray()[0] ?? null;

            /**
             * @var array<string, mixed>
             */
            return $response !== null ? (array) $response : [];
        });
    }

    /**
     * Explain an aggregation pipeline.
     *
     * @param Pipeline|list<array<string, mixed>> $pipeline
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function explainAggregate(Pipeline|array $pipeline, array $options = []): array
    {
        return $this->runWithReconnect(function () use ($pipeline, $options): array {
            $pipelineArray = $pipeline instanceof Pipeline ? iterator_to_array($pipeline) : $pipeline;

            $command = [
                'explain' => [
                    'aggregate' => $this->collection->getCollectionName(),
                    'pipeline' => $pipelineArray,
                    'cursor' => (object) [],
                    ...$options,
                ],
            ];

            $response = $this->collection->getManager()
                ->executeCommand(
                    $this->collection->getDatabaseName(),
                    new \MongoDB\Driver\Command($command),
                )->toArray()[0] ?? null;

            /**
             * @var array<string, mixed>
             */
            return $response !== null ? (array) $response : [];
        });
    }

    /**
     * Create a new Filter builder.
     *
     * @return Filter
     */
    public function filter(): Filter
    {
        return Filter::new();
    }

    /**
     * Get the underlying MongoDB\Collection. Escape hatch.
     *
     * @return \MongoDB\Collection
     */
    public function raw(): \MongoDB\Collection
    {
        return $this->collection;
    }

    /**
     * Get the collection name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->collection->getCollectionName();
    }

    /**
     * Get the full namespace (database.collection).
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->collection->getNamespace();
    }

    /**
     * Execute a read operation with reconnect, logging, and exception translation.
     *
     * @template T
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $filter Query filter for logging
     * @param callable(): T $callback
     *
     * @return T
     */
    private function executeRead(string $operation, array $filter, callable $callback): mixed
    {
        $start = hrtime(true);

        try {
            $result = $this->runWithReconnect($callback);
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            $this->logDuration($operation, $filter, $start);
            throw new TimeoutException(
                "Operation '{$operation}' timed out on '{$this->getName()}'",
                $operation,
                $this->getName(),
                $e->getCode(),
                $e,
            );
        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            $this->logDuration($operation, $filter, $start);
            throw new \PHPdot\MongoDB\Exception\ConnectionException(
                "MongoConnection lost during '{$operation}' on '{$this->getName()}'",
                $this->connection->getConfig()->getHostString(),
                $e->getCode(),
                $e,
            );
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            $this->logDuration($operation, $filter, $start);
            throw new QueryException(
                $e->getMessage(),
                $operation,
                $this->getName(),
                $e->getCode(),
                $e,
            );
        }

        $this->logDuration($operation, $filter, $start);

        return $result;
    }

    /**
     * Execute a write operation with reconnect, logging, and exception translation.
     *
     * @template T
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $filter Query filter for logging
     * @param callable(): T $callback
     *
     * @return T
     */
    private function executeWrite(string $operation, array $filter, callable $callback): mixed
    {
        $start = hrtime(true);

        try {
            $result = $this->runWithReconnect($callback);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $this->logDuration($operation, $filter, $start);
            $code = $e->getCode();
            if ($code === 11000 || $code === 11001) {
                throw new DuplicateKeyException(
                    $e->getMessage(),
                    $this->getName(),
                    $this->extractDuplicateKeyName($e->getMessage()),
                    $code,
                    $e,
                );
            }
            if ($code === 121) {
                throw new \PHPdot\MongoDB\Exception\ValidationException(
                    $e->getMessage(),
                    $operation,
                    $this->getName(),
                    $code,
                    $e,
                );
            }
            $translated = new BulkWriteException(
                $e->getMessage(),
                $operation,
                $this->getName(),
                $code,
                $e,
            );
            throw $translated;
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            $this->logDuration($operation, $filter, $start);
            throw new TimeoutException(
                "Operation '{$operation}' timed out on '{$this->getName()}'",
                $operation,
                $this->getName(),
                $e->getCode(),
                $e,
            );
        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            $this->logDuration($operation, $filter, $start);
            throw new \PHPdot\MongoDB\Exception\ConnectionException(
                "MongoConnection lost during '{$operation}' on '{$this->getName()}'",
                $this->connection->getConfig()->getHostString(),
                $e->getCode(),
                $e,
            );
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            $this->logDuration($operation, $filter, $start);
            throw $this->translateWriteException($e, $operation);
        }

        $this->logDuration($operation, $filter, $start);

        return $result;
    }

    /**
     * Translate a MongoDB write exception to a typed PHPdot exception.
     *
     * @param \MongoDB\Driver\Exception\RuntimeException $e
     * @param string $operation
     *
     * @return MongoException
     */
    private function translateWriteException(\MongoDB\Driver\Exception\RuntimeException $e, string $operation): MongoException
    {
        $code = $e->getCode();

        if ($code === 11000 || $code === 11001) {
            $keyName = $this->extractDuplicateKeyName($e->getMessage());

            return new DuplicateKeyException(
                $e->getMessage(),
                $this->getName(),
                $keyName,
                $code,
                $e,
            );
        }

        if ($code === 121) {
            return new ValidationException(
                $e->getMessage(),
                $operation,
                $this->getName(),
                $code,
                $e,
            );
        }

        if ($code === 50) {
            return new TimeoutException(
                "Operation '{$operation}' timed out on '{$this->getName()}'",
                $operation,
                $this->getName(),
                $code,
                $e,
            );
        }

        return new WriteException(
            $e->getMessage(),
            $operation,
            $this->getName(),
            $code,
            $e,
        );
    }

    /**
     * Extract the duplicate key index name from a MongoDB error message.
     *
     * @param string $message
     *
     * @return string
     */
    private function extractDuplicateKeyName(string $message): string
    {
        if (preg_match('/index:\s+(\S+)/', $message, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Execute an operation with automatic reconnect on connection failure.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function runWithReconnect(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            $this->connection->reconnect();
            $this->refreshCollection();

            return $operation();
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            if ($this->isConnectionError($e)) {
                $this->connection->reconnect();
                $this->refreshCollection();

                return $operation();
            }

            throw $e;
        }
    }

    /**
     * Determine if a runtime exception is a connection-level error.
     *
     * @param \MongoDB\Driver\Exception\RuntimeException $e
     *
     * @return bool
     */
    private function isConnectionError(\MongoDB\Driver\Exception\RuntimeException $e): bool
    {
        $connectionCodes = [
            6,
            7,
            89,
            9001,
            10107,
            11600,
            11602,
            13435,
            13436,
        ];

        return in_array($e->getCode(), $connectionCodes, true);
    }

    /**
     * Log the duration of an operation.
     *
     * @param string $operation Operation name
     * @param array<string, mixed> $filter Query filter
     * @param int $start hrtime(true) start value
     *
     * @return void
     */
    private function logDuration(string $operation, array $filter, int $start): void
    {
        $durationMs = (hrtime(true) - $start) / 1_000_000;
        $this->logger?->log($operation, $this->getName(), $filter, $durationMs);
    }

    /**
     * Refresh the internal collection reference after reconnect.
     *
     * @return void
     */
    private function refreshCollection(): void
    {
        $this->collection = $this->connection->getDatabase()
            ->selectCollection($this->collection->getCollectionName());
    }
}
