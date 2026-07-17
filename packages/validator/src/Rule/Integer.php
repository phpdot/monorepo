<?php

declare(strict_types=1);

/**
 * The value must be an integer (or a numeric string with no fractional part).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Integer extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_string($value) && $value !== '') {
            return preg_match('/^-?\d+$/', $value) === 1;
        }

        return false;
    }
}
