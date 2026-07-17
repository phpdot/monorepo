<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;

#[Singleton]
#[Binds(SampleInterface::class)]
final class SampleLoader implements SampleInterface
{
    public function __construct(
        private readonly SampleConfig $config,
    ) {}
}
