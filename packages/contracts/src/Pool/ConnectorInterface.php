<?php

declare(strict_types=1);

/**
 * Connector contract — defines how a pool creates, checks, and destroys
 * pooled objects.
 *
 * Lives in `phpdot/contracts` so a producer (e.g., `phpdot/database-mysql`)
 * can implement it without depending on `phpdot/pool`, and a consumer
 * (`phpdot/pool`) can resolve it without depending on the producer.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Contracts\Pool;

interface ConnectorInterface
{
    /**
     * Create a new connection object, fully initialized and ready to use.
     *
     * @throws \Throwable If the connection cannot be established
     *
     * @return object
     */
    public function connect(): object;

    /**
     * Check if a connection is still usable.
     *
     * Should be lightweight. A single round-trip ping (e.g., `SELECT 1`) is
     * acceptable and is the recommended implementation — for typical drivers,
     * a server-side liveness check is the only way to detect connections
     * killed by `wait_timeout`, firewall idle drops, or server restarts.
     *
     * Pools call this from heartbeat timers, on borrow when the popped item
     * has been idle past a TTL, and on release when configured.
     *
     * Implementations must not throw — return `false` on any error.
     *
     * @param object $connection
     *
     * @return bool
     */
    public function isAlive(object $connection): bool;

    /**
     * Close and destroy a connection object permanently.
     *
     * Must not throw. If the connection is already dead, ignore silently.
     *
     * @param object $connection
     *
     * @return void
     */
    public function close(object $connection): void;
}
