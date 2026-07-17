<?php

declare(strict_types=1);

/**
 * If the field is present, it must have a non-empty value.
 *
 * Unlike `Required`, this passes when the field is absent. Use to enforce
 * "if you send it, send something real" without making the field mandatory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Filled extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        if (!$context->has($context->field())) {
            return true;
        }

        return !self::isEmpty($value);
    }
}
