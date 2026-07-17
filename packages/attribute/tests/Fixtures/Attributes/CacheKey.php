<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class CacheKey
{
    public function __construct(
        public readonly string $prefix = '',
    ) {}
}
