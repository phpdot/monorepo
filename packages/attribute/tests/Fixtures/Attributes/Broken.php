<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Broken
{
    public function __construct()
    {
        throw new \RuntimeException('This attribute always throws');
    }
}
