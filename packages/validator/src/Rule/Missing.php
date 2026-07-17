<?php

declare(strict_types=1);

/**
 * The field must not be present in the payload at all.
 *
 * Stricter than `Prohibited`: even an empty value (`null`, `''`, `[]`)
 * counts as present and fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Missing extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return !$context->has($context->field());
    }
}
