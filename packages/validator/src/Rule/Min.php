<?php

declare(strict_types=1);

/**
 * Minimum size: numeric value, string length, or array count.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Min extends Rule
{
    /**
     * __construct.
     *
     * @param int|float $min
     */
    public function __construct(
        private readonly int|float $min,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return Size::measure($value) >= $this->min;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'min' => $this->min];
    }
}
