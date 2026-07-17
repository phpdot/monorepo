<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Stubs;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class AlwaysPasses extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return true;
    }
}
