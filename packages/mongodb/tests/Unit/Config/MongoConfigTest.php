<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Config;

use PHPdot\MongoDB\Config\MongoConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MongoConfigTest extends TestCase
{
    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $config = new MongoConfig();

        self::assertSame('localhost', $config->hosts);
        self::assertSame(27017, $config->port);
        self::assertSame('', $config->username);
        self::assertSame('', $config->password);
        self::assertSame('', $config->database);
        self::assertSame('single', $config->deployment);
        self::assertSame('', $config->replicaSet);
        self::assertSame(1000, $config->timeoutMs);
        self::assertSame('primary', $config->readPreference);
        self::assertSame('majority', $config->writeConcern);
        self::assertSame('local', $config->readConcern);
        self::assertSame(-1, $config->maxStalenessSeconds);
        self::assertSame([], $config->tags);
        self::assertTrue($config->retryWrites);
        self::assertTrue($config->retryReads);
        self::assertSame(3, $config->maxRetries);
        self::assertSame([], $config->options);
    }

    #[Test]
    public function it_builds_uri_for_single_host(): void
    {
        $config = new MongoConfig(hosts: 'mongo.example.com', port: 27018);

        self::assertSame('mongodb://mongo.example.com:27018', $config->buildUri());
    }

    #[Test]
    public function it_builds_uri_for_multiple_hosts(): void
    {
        $config = new MongoConfig(
            hosts: ['mongo1.example.com:27017', 'mongo2.example.com:27018'],
        );

        self::assertSame('mongodb://mongo1.example.com:27017,mongo2.example.com:27018', $config->buildUri());
    }

    #[Test]
    public function it_builds_uri_with_credentials(): void
    {
        $config = new MongoConfig(
            hosts: 'localhost',
            username: 'admin',
            password: 'p@ss w0rd!',
        );

        self::assertSame('mongodb://admin:p%40ss%20w0rd%21@localhost:27017', $config->buildUri());
    }

    #[Test]
    public function it_builds_uri_options(): void
    {
        $config = new MongoConfig(
            database: 'mydb',
            replicaSet: 'rs0',
            timeoutMs: 5000,
            readPreference: 'secondary',
            writeConcern: 1,
            readConcern: 'majority',
            maxStalenessSeconds: 120,
            authSource: 'admin',
        );

        $options = $config->buildUriOptions();

        self::assertSame('admin', $options['authSource']);
        self::assertSame('rs0', $options['replicaSet']);
        self::assertSame(5000, $options['connectTimeoutMS']);
        self::assertSame('secondary', $options['readPreference']);
        self::assertSame(1, $options['w']);
        self::assertSame('majority', $options['readConcernLevel']);
        self::assertTrue($options['retryWrites']);
        self::assertTrue($options['retryReads']);
        self::assertSame(120, $options['maxStalenessSeconds']);
    }

    #[Test]
    public function it_omits_max_staleness_when_negative(): void
    {
        $config = new MongoConfig(maxStalenessSeconds: -1);
        $options = $config->buildUriOptions();

        self::assertArrayNotHasKey('maxStalenessSeconds', $options);
    }

    #[Test]
    public function it_omits_replica_set_when_empty(): void
    {
        $config = new MongoConfig();
        $options = $config->buildUriOptions();

        self::assertArrayNotHasKey('replicaSet', $options);
    }

    #[Test]
    public function it_omits_auth_source_when_empty(): void
    {
        $config = new MongoConfig();
        $options = $config->buildUriOptions();

        self::assertArrayNotHasKey('authSource', $options);
    }

    #[Test]
    public function it_merges_custom_options(): void
    {
        $config = new MongoConfig(
            options: ['appname' => 'myapp', 'compressors' => 'zstd'],
        );

        $options = $config->buildUriOptions();

        self::assertSame('myapp', $options['appname']);
        self::assertSame('zstd', $options['compressors']);
    }

    #[Test]
    public function it_returns_host_string(): void
    {
        $config = new MongoConfig(hosts: ['host1', 'host2']);

        self::assertSame('host1,host2', $config->getHostString());
    }

    #[Test]
    public function it_returns_host_string_for_single_host(): void
    {
        $config = new MongoConfig(hosts: 'localhost');

        self::assertSame('localhost', $config->getHostString());
    }

    #[Test]
    public function it_adds_default_port_to_hosts_without_port(): void
    {
        $config = new MongoConfig(
            hosts: ['mongo1.example.com', 'mongo2.example.com'],
            port: 27017,
        );

        self::assertSame('mongodb://mongo1.example.com:27017,mongo2.example.com:27017', $config->buildUri());
    }

    #[Test]
    public function it_preserves_explicit_port_in_host(): void
    {
        $config = new MongoConfig(
            hosts: ['mongo1.example.com:27018'],
            port: 27017,
        );

        self::assertSame('mongodb://mongo1.example.com:27018', $config->buildUri());
    }
}
