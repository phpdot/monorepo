<?php

declare(strict_types=1);

/**
 * Resilient MongoDB connection with auto-reconnect and exponential backoff.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB;

use MongoDB\Client;
use MongoDB\Database;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\Exception\AuthenticationException;
use PHPdot\MongoDB\Exception\ConnectionException;

final class MongoConnection
{
    private ?Client $client = null;

    private bool $connected = false;

    /**
     * Hold the settings for a single, not-yet-opened MongoDB connection.
     *
     * @param MongoConfig $config MongoConnection configuration
     */
    public function __construct(
        private readonly MongoConfig $config,
    ) {}

    /**
     * Connect to MongoDB with exponential backoff retries.
     *
     * @throws ConnectionException If connection fails after all retries
     * @throws AuthenticationException If authentication fails
     *
     * @return void
     */
    public function connect(): void
    {
        $lastException = null;
        $maxRetries = $this->config->maxRetries;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                $delay = 100 * (2 ** ($attempt - 1));
                usleep($delay * 1000);
            }

            try {
                $this->client = new Client(
                    $this->config->buildUri(),
                    $this->config->buildUriOptions(),
                );

                $this->client->getManager()->selectServer();
                $this->connected = true;

                return;
            } catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
                throw new AuthenticationException(
                    'Authentication failed: ' . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
                $lastException = $e;
            } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
                $lastException = $e;
            }
        }

        throw new ConnectionException(
            'Failed to connect after ' . ($maxRetries + 1) . ' attempts: ' . ($lastException?->getMessage() ?? 'unknown error'),
            $this->config->getHostString(),
            $lastException?->getCode() ?? 0,
            $lastException,
        );
    }

    /**
     * Close the connection.
     *
     * @return void
     */
    public function close(): void
    {
        $this->client = null;
        $this->connected = false;
    }

    /**
     * Check if the connection is established (local flag, no server ping).
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Ping the server to verify the connection is alive.
     *
     * @return bool
     */
    public function ping(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            $this->client->getManager()->selectServer();

            return true;
        } catch (\MongoDB\Driver\Exception\Exception) {
            $this->connected = false;

            return false;
        }
    }

    /**
     * Ensure the connection is established. Checks local flag only.
     *
     * @throws ConnectionException If not connected
     *
     * @return void
     */
    public function ensureConnected(): void
    {
        if (!$this->connected || $this->client === null) {
            throw new ConnectionException(
                'Not connected to MongoDB',
                $this->config->getHostString(),
            );
        }
    }

    /**
     * Close and re-establish the connection.
     *
     * @throws ConnectionException If reconnection fails
     *
     * @return void
     */
    public function reconnect(): void
    {
        $this->close();
        $this->connect();
    }

    /**
     * Get the underlying MongoDB\Client.
     *
     * @throws ConnectionException If not connected
     *
     * @return Client
     */
    public function getClient(): Client
    {
        $this->ensureConnected();
        assert($this->client !== null);

        return $this->client;
    }

    /**
     * Get the MongoDB\Database for the configured database name.
     *
     * @throws ConnectionException If not connected or no database configured
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        if ($this->config->database === '') {
            throw new ConnectionException(
                'No database configured',
                $this->config->getHostString(),
            );
        }

        return $this->getClient()->selectDatabase($this->config->database);
    }

    /**
     * Get the connection configuration.
     *
     * @return MongoConfig
     */
    public function getConfig(): MongoConfig
    {
        return $this->config;
    }
}
