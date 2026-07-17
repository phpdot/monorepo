<?php

declare(strict_types=1);

/**
 * The value must be numeric (int, float, or numeric string).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Numeric extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }
}
