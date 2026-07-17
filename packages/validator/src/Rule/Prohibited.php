<?php

declare(strict_types=1);

/**
 * The field must not be present, or, if present, must be empty.
 *
 * Symmetric to `Required`: where `Required` demands a non-empty value,
 * `Prohibited` demands the absence of one.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Prohibited extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!$context->has($context->field())) {
            return true;
        }

        return self::isEmpty($value);
    }
}
