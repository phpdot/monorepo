<?php

declare(strict_types=1);

/**
 * The field must be present in the payload and have a non-empty value.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Required extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return $context->has($context->field()) && !self::isEmpty($value);
    }
}
