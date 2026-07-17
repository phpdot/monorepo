<?php

declare(strict_types=1);

/**
 * Immutable log entry for a single MongoDB query.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Logging;

final readonly class QueryLog
{
    /**
     * Record one executed query: operation, collection, filter, and timing.
     *
     * @param string $operation Operation name (e.g. 'find', 'insertOne', 'aggregate')
     * @param string $collection Collection name
     * @param array<string, mixed> $filter Query filter used
     * @param float $durationMs Execution duration in milliseconds
     * @param bool $slow Whether this query exceeded the slow query threshold
     */
    public function __construct(
        public string $operation,
        public string $collection,
        public array $filter,
        public float $durationMs,
        public bool $slow,
    ) {}
}
