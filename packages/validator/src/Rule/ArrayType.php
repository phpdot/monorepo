<?php

declare(strict_types=1);

/**
 * The value must be an array.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class ArrayType extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_array($value);
    }
}
