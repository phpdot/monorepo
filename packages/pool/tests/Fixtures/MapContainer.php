<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Fixtures;

use Psr\Container\ContainerInterface;

final class MapContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private readonly array $services = [],
    ) {}

    public function get(string $id): object
    {
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
