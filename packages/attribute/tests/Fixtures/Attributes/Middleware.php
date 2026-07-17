<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Middleware
{
    public function __construct(
        public readonly string $name,
    ) {}
}
