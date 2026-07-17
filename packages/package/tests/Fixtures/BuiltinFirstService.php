<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Fixtures;

use PHPdot\Container\Attribute\Singleton;

#[Singleton]
final class BuiltinFirstService
{
    public function __construct(
        public readonly int $timeout,
        public readonly SimpleService $service,
    ) {}
}
