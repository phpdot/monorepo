<?php

declare(strict_types=1);

/**
 * Size between min and max (inclusive): numeric value, string length, or array count.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Between extends Rule
{
    /**
     * __construct.
     *
     * @param int|float $min
     * @param int|float $max
     */
    public function __construct(
        private readonly int|float $min,
        private readonly int|float $max,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        $size = Size::measure($value);

        return $size >= $this->min && $size <= $this->max;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'min' => $this->min, 'max' => $this->max];
    }
}
