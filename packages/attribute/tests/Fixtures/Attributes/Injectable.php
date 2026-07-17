<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Injectable
{
    public function __construct(
        public readonly bool $singleton = false,
    ) {}
}
