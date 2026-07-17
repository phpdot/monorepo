<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class OptionalConcreteService
{
    public function __construct(
        public readonly SampleInterface $required,
        public readonly SimpleService $optional = new SimpleService(),
    ) {}
}
