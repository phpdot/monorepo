<?php

declare(strict_types=1);

/**
 * The value must be a numeric string whose digit count falls in the inclusive
 * range `[min, max]`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class DigitsBetween extends Rule
{
    /**
     * __construct.
     *
     * @param int $min
     * @param int $max
     */
    public function __construct(
        private readonly int $min,
        private readonly int $max,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        $string = (string) $value;

        if (preg_match('/^\d+$/', $string) !== 1) {
            return false;
        }

        $length = strlen($string);

        return $length >= $this->min && $length <= $this->max;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'min' => $this->min, 'max' => $this->max];
    }
}
