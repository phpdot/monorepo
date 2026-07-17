<?php

declare(strict_types=1);

/**
 * The value must be a numeric string with exactly the given number of digits.
 *
 * Useful for fixed-length numeric inputs — phone numbers, OTP codes, ZIP
 * codes — where leading zeros matter and the value should be treated as a
 * string, not a number.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Digits extends Rule
{
    /**
     * __construct.
     *
     * @param int $length
     */
    public function __construct(
        private readonly int $length,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        $string = (string) $value;

        return preg_match('/^\d+$/', $string) === 1 && strlen($string) === $this->length;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'digits' => $this->length];
    }
}
