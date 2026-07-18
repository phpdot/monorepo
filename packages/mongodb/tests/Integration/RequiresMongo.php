<?php

declare(strict_types=1);

/**
 * Skips a test case when no MongoDB server is reachable.
 *
 * The probe fails fast (500ms timeout, no retries) so an absent server skips
 * in well under a second instead of exhausting the client's reconnect backoff.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\MongoConnection;

trait RequiresMongo
{
    protected function skipUnlessMongoAvailable(): void
    {
        try {
            $probe = new MongoConnection(self::mongoTestConfig(timeoutMs: 500, maxRetries: 0));
            $probe->connect();
            $probe->close();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB is not available: ' . $e->getMessage());
        }
    }

    protected static function mongoTestConfig(
        int $timeoutMs = 1000,
        int $maxRetries = 3,
        string $database = 'phpdot_test',
    ): MongoConfig {
        return new MongoConfig(
            hosts: getenv('MONGO_HOST') ?: 'localhost',
            port: (int) (getenv('MONGO_PORT') ?: 27017),
            database: $database,
            timeoutMs: $timeoutMs,
            maxRetries: $maxRetries,
        );
    }
}
