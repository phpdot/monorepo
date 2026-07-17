<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Config;

#[Config('map')]
final readonly class MapConfig
{
    /**
     * @param string $default Default entry name
     * @param array<string, array<string, mixed>> $connections Named driver-tagged blocks
     */
    public function __construct(
        public string $default = 'primary',
        public array $connections = [
            'primary' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
            ],
        ],
    ) {}
}
