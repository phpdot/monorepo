<?php

declare(strict_types=1);

/**
 * The value must NOT equal another field's value (strict inequality).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Different extends Rule
{
    /**
     * __construct.
     *
     * @param string $otherField
     */
    public function __construct(
        private readonly string $otherField,
    ) {}

    public function passes(mixed $value, ValidationContext $context): bool
    {
        return $value !== $context->value($this->otherField);
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'other' => $this->otherField];
    }
}
