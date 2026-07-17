<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Validated
{
    public function __construct() {}
}
