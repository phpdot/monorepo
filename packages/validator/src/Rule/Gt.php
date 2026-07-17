<?php

declare(strict_types=1);

/**
 * The value must be greater than a literal number or another field's size.
 *
 * If `$bound` is a string that names a field, the field's size is used.
 * Otherwise the bound is treated as a literal number.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Gt extends Rule
{
    /**
     * __construct.
     *
     * @param int|float|string $bound
     */
    public function __construct(
        private readonly int|float|string $bound,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return Size::measure($value) > Size::measure($context->dereference($this->bound));
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'other' => $this->bound];
    }
}
