<?php

declare(strict_types=1);

/**
 * Adapts `phpdot/mongodb`'s `MongoConnection` to `phpdot/pool`'s connector
 * contract.
 *
 * Lets any pool (e.g., `phpdot/pool`) hold and manage `MongoConnection`
 * instances: `connect()` builds a fresh connection and ensures it's open;
 * `isAlive()` reports the underlying driver state via ping; `close()` shuts
 * the connection down.
 *
 * The connector itself depends only on `PHPdot\Contracts\Pool\ConnectorInterface`
 * — it does not require `phpdot/pool` at runtime, so `phpdot/mongodb` stays
 * usable in non-pooled contexts (FPM, CLI, scripts) without pulling pooling
 * code along.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB;

use PHPdot\Contracts\Pool\ConnectorInterface;
use PHPdot\MongoDB\Config\MongoConfig;
use Throwable;

final class MongoConnector implements ConnectorInterface
{
    /**
     * Wire the connector to the config every new connection is built from.
     *
     * @param MongoConfig $config Connection settings for each MongoConnection this connector builds
     */
    public function __construct(
        private readonly MongoConfig $config,
    ) {}

    /**
     * Build a fresh `MongoConnection`, ensuring the underlying driver client
     * is initialised before handing it back.
     */
    public function connect(): object
    {
        $connection = new MongoConnection($this->config);
        $connection->connect();

        return $connection;
    }

    /**
     * Ping the connection. Returns `false` on any error.
     */
    public function isAlive(object $connection): bool
    {
        if (!$connection instanceof MongoConnection) {
            return false;
        }

        try {
            return $connection->ping();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Close the connection. Never throws.
     */
    public function close(object $connection): void
    {
        if (!$connection instanceof MongoConnection) {
            return;
        }

        try {
            $connection->close();
        } catch (Throwable) {
        }
    }
}
