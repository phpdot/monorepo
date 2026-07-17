<?php

declare(strict_types=1);

/**
 * The field must be present in the payload, regardless of value.
 *
 * Differs from `Required` in that empty values (null, '', []) are accepted
 * — only the *key's absence* causes failure.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class Present extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return $context->has($context->field());
    }
}
