<?php

declare(strict_types=1);

/**
 * Ring buffer query logger with slow query tracking.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Logging;

final class QueryLogger
{
    /**
     * @var array<int, QueryLog>
     */
    private array $logs = [];

    private int $position = 0;

    /**
     * Collect recent query logs, flagging any slower than a threshold.
     *
     * @param int $maxEntries Maximum number of log entries to keep
     * @param float $slowThresholdMs Threshold in milliseconds for marking queries as slow
     */
    public function __construct(
        private readonly int $maxEntries = 100,
        private readonly float $slowThresholdMs = 100.0,
    ) {}

    /**
     * Log a query execution.
     *
     * @param string $operation Operation name
     * @param string $collection Collection name
     * @param array<string, mixed> $filter Query filter
     * @param float $durationMs Execution time in milliseconds
     *
     * @return void
     */
    public function log(string $operation, string $collection, array $filter, float $durationMs): void
    {
        $entry = new QueryLog(
            operation: $operation,
            collection: $collection,
            filter: $filter,
            durationMs: $durationMs,
            slow: $durationMs >= $this->slowThresholdMs,
        );

        if (count($this->logs) < $this->maxEntries) {
            $this->logs[] = $entry;
        } else {
            $this->logs[$this->position] = $entry;
        }

        $this->position = ($this->position + 1) % $this->maxEntries;
    }

    /**
     * Get all logged queries in chronological order.
     *
     * @return list<QueryLog>
     */
    public function getAll(): array
    {
        if (count($this->logs) < $this->maxEntries) {
            return array_values($this->logs);
        }

        return [
            ...array_slice($this->logs, $this->position),
            ...array_slice($this->logs, 0, $this->position),
        ];
    }

    /**
     * Get only slow queries.
     *
     * @return list<QueryLog>
     */
    public function getSlow(): array
    {
        return array_values(array_filter($this->logs, static fn(QueryLog $log): bool => $log->slow));
    }

    /**
     * Get the total number of logged queries.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * Clear all log entries.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->position = 0;
    }

    /**
     * Get the slow query threshold in milliseconds.
     *
     * @return float
     */
    public function getSlowThreshold(): float
    {
        return $this->slowThresholdMs;
    }
}
