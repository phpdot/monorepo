<?php

declare(strict_types=1);

/**
 * Immutable configuration for a MongoDB connection.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Config;

use PHPdot\Container\Attribute\Config;

#[Config('mongodb')]
final readonly class MongoConfig
{
    /**
     * Immutable MongoDB connection settings, resolved into a driver URI.
     *
     * @param string|list<string> $hosts MongoDB host(s)
     * @param int $port Default port
     * @param string $username Authentication username
     * @param string $password Authentication password
     * @param string $database Default database name
     * @param string $deployment Topology: 'single', 'replicaSet', 'sharded'
     * @param string $replicaSet Replica set name
     * @param int $timeoutMs MongoConnection timeout in milliseconds
     * @param string $readPreference Read preference mode
     * @param string|int $writeConcern Write concern level
     * @param string $readConcern Read concern level
     * @param int $maxStalenessSeconds Maximum staleness for secondary reads (-1 = no limit)
     * @param array<string, string> $tags Tag sets for read preference
     * @param bool $retryWrites Enable retryable writes
     * @param bool $retryReads Enable retryable reads
     * @param int $maxRetries Maximum reconnection retries
     * @param string $authSource Authentication database (empty = driver default)
     * @param array<string, mixed> $options Additional URI options
     */
    public function __construct(
        public string|array $hosts = 'localhost',
        public int $port = 27017,
        public string $username = '',
        public string $password = '',
        public string $database = '',
        public string $deployment = 'single',
        public string $replicaSet = '',
        public int $timeoutMs = 1000,
        public string $readPreference = 'primary',
        public string|int $writeConcern = 'majority',
        public string $readConcern = 'local',
        public int $maxStalenessSeconds = -1,
        public array $tags = [],
        public bool $retryWrites = true,
        public bool $retryReads = true,
        public int $maxRetries = 3,
        public string $authSource = '',
        public array $options = [],
    ) {}

    /**
     * Build the MongoDB connection URI string.
     *
     * @return string
     */
    public function buildUri(): string
    {
        $hosts = is_array($this->hosts) ? $this->hosts : [$this->hosts];
        $hostParts = array_map(
            fn(string $host): string => str_contains($host, ':') ? $host : $host . ':' . $this->port,
            $hosts,
        );

        $credentials = '';
        if ($this->username !== '' && $this->password !== '') {
            $credentials = rawurlencode($this->username) . ':' . rawurlencode($this->password) . '@';
        }

        return 'mongodb://' . $credentials . implode(',', $hostParts);
    }

    /**
     * Build the URI options array for the MongoDB\Client constructor.
     *
     * @return array<string, mixed>
     */
    public function buildUriOptions(): array
    {
        $options = $this->options;

        if ($this->authSource !== '') {
            $options['authSource'] = $this->authSource;
        }

        if ($this->replicaSet !== '') {
            $options['replicaSet'] = $this->replicaSet;
        }

        $options['connectTimeoutMS'] = $this->timeoutMs;
        $options['readPreference'] = $this->readPreference;
        $options['w'] = $this->writeConcern;
        $options['readConcernLevel'] = $this->readConcern;
        $options['retryWrites'] = $this->retryWrites;
        $options['retryReads'] = $this->retryReads;

        if ($this->maxStalenessSeconds >= 0) {
            $options['maxStalenessSeconds'] = $this->maxStalenessSeconds;
        }

        return $options;
    }

    /**
     * Get the host string for error messages.
     *
     * @return string
     */
    public function getHostString(): string
    {
        $hosts = is_array($this->hosts) ? $this->hosts : [$this->hosts];

        return implode(',', $hosts);
    }
}
