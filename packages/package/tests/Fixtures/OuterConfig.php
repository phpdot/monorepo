<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Config;

#[Config('outer')]
final readonly class OuterConfig
{
    /**
     * @param list<string> $hosts
     */
    public function __construct(
        public array $hosts = [],
        public InnerConfig $inner = new InnerConfig(),
    ) {}
}
