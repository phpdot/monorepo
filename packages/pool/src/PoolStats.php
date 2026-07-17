<?php

declare(strict_types=1);

/**
 * Pool statistics snapshot. Used for monitoring and health checks.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool;

final readonly class PoolStats
{
    /**
     * Capture a point-in-time snapshot of the pool's counters.
     *
     * @param int $active
     * @param int $idle
     * @param int $total
     * @param int $borrowCount
     * @param int $releaseCount
     * @param int $discardCount
     * @param int $createCount
     * @param int $closeCount
     * @param int $timeoutCount
     * @param int $waitingCount
     */
    public function __construct(
        public int $active,
        public int $idle,
        public int $total,
        public int $borrowCount,
        public int $releaseCount,
        public int $discardCount,
        public int $createCount,
        public int $closeCount,
        public int $timeoutCount,
        public int $waitingCount,
    ) {}
}
