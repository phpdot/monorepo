<?php

declare(strict_types=1);

/**
 * The value must be a string with no uppercase letters.
 *
 * Strings without alphabetic characters (digits, symbols, empty) pass.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Lowercase extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value) && mb_strtolower($value) === $value;
    }
}
