<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class IntersectionService
{
    public function __construct(
        public readonly SampleInterface&SecondInterface $factory,
        public readonly SimpleService $next,
    ) {}
}
