<?php

declare(strict_types=1);

/**
 * The field must equal a sibling field named `{field}_confirmation`.
 *
 * Example: `password` must match `password_confirmation`.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Confirmed extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        $confirmationField = $context->field() . '_confirmation';

        return $value === $context->value($confirmationField);
    }

    public function params(ValidationContext $context): array
    {
        return [...parent::params($context), 'other' => $context->field() . '_confirmation'];
    }
}
