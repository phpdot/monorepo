<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public readonly string $path,
        public readonly array $methods = ['GET'],
        public readonly ?string $name = null,
    ) {}
}
