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
            $probe = new MongoConnection(new MongoConfig(database: 'phpdot_test', timeoutMs: 500, maxRetries: 0));
            $probe->connect();
            $probe->close();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB is not available: ' . $e->getMessage());
        }
    }
}
