<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public readonly string $name = '',
    ) {}
}
