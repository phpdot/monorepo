<?php

declare(strict_types=1);

/**
 * Pool configuration. Immutable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool;

use PHPdot\Container\Attribute\Config;

#[Config('pool')]
final readonly class PoolConfig
{
    /**
     * Immutable pool tuning: sizing, timeouts, idle cleanup, heartbeat, and borrow/return validation.
     *
     * @param int $minConnections Pre-created on init. Pool never shrinks below this. Default: 2.
     * @param int $maxConnections Hard limit per worker. Default: 10.
     * @param float $borrowTimeout Seconds to wait when pool exhausted. Default: 3.0.
     * @param float $maxIdleTime Seconds before idle cleanup. 0.0 = disabled. Default: 300.0.
     * @param float $idleCheckInterval Seconds between idle cleanup runs. Default: 30.0.
     * @param float $heartbeatInterval Seconds between heartbeat checks. 0.0 = disabled. Default: 0.0.
     * @param float $validateOnBorrowAfterIdle TTL gate for the `isAlive()` borrow check.
     *                                         Positive: validate only after that many idle seconds.
     *                                         0.0: validate every borrow. Negative: disabled. Default: 5.0.
     * @param bool $validateOnReturn When true (default), `isAlive()` on release closes
     *                               dead connections instead of re-pooling them, so a connection whose
     *                               command threw (a Redis blip, a killed server) can't be handed back
     *                               out before the idle gate catches it. Set false only when `isAlive()`
     *                               is expensive.
     */
    public function __construct(
        public int $minConnections = 2,
        public int $maxConnections = 10,
        public float $borrowTimeout = 3.0,
        public float $maxIdleTime = 300.0,
        public float $idleCheckInterval = 30.0,
        public float $heartbeatInterval = 0.0,
        public float $validateOnBorrowAfterIdle = 5.0,
        public bool $validateOnReturn = true,
    ) {}
}
