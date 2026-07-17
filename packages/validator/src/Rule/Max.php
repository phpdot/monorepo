<?php

declare(strict_types=1);

/**
 * Maximum size: numeric value, string length, or array count.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Max extends Rule
{
    /**
     * __construct.
     *
     * @param int|float $max
     */
    public function __construct(
        private readonly int|float $max,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return Size::measure($value) <= $this->max;
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'max' => $this->max];
    }
}
