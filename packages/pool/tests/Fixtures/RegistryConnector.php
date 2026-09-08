<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Fixtures;

use PHPdot\Contracts\Pool\ConnectorInterface;

final class RegistryConnector implements ConnectorInterface
{
    /**
     * @var list<RegistryConfig> One entry per connection built, carrying the config it was built from
     */
    public array $built = [];

    /**
     * @var list<FakeConnection> The connections this connector closed
     */
    public array $closed = [];

    public function __construct(
        private readonly RegistryConfig $config,
    ) {}

    public function connect(): object
    {
        $this->built[] = $this->config;

        return new FakeConnection();
    }

    public function isAlive(object $connection): bool
    {
        return true;
    }

    public function close(object $connection): void
    {
        if ($connection instanceof FakeConnection) {
            $connection->closed = true;
            $this->closed[] = $connection;
        }
    }
}
