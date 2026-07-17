<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Scoped;

#[Scoped]
final class SampleService
{
    public function __construct(
        private readonly SampleInterface $loader,
        private readonly SampleConfig $config,
    ) {}
}
