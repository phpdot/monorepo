<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class OptionalDependencyService
{
    public function __construct(
        public readonly SimpleService $service,
        public readonly ?SampleInterface $optional = null,
    ) {}
}
