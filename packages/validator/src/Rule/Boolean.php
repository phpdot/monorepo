<?php

declare(strict_types=1);

/**
 * The value must be a boolean (true, false, 0, 1, '0', '1', 'true', 'false').
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Boolean extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['0', '1', 'true', 'false'], true);
        }

        return false;
    }
}
