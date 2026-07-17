<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Config;

#[Config('sample')]
final readonly class SampleConfig
{
    /**
     * @param string $name Application name
     * @param int $port Server port number
     * @param bool $debug Enable debug mode
     * @param list<string> $tags Resource tags
     */
    public function __construct(
        public string $name = 'default',
        public int $port = 3000,
        public bool $debug = false,
        public array $tags = [],
    ) {}
}
