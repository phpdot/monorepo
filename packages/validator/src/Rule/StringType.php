<?php

declare(strict_types=1);

/**
 * The value must be a string.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Validator\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\ValidationContext;

final class StringType extends Rule
{
    public function passes(mixed $value, ValidationContext $context): bool
    {
        return is_string($value);
    }
}
