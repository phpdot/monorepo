<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Fixtures;

final class RegistryConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 3306,
    ) {}
}
